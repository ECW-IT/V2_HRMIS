# Task 03 — Audit log

**Depends on:** 02. **Read first:** `AGENTS.md`.

## Goal

Every create, update and delete on a business table is recorded automatically: who, when, what changed.

## Files

- Create: `app/Support/Audit/Auditable.php`, `app/Support/Audit/AuditLogger.php`, `app/Models/AuditLog.php`, migration `create_audit_logs_table`.
- Modify: `tests/Support/Probe/ScopedProbe.php` (use `Auditable`, `protected array $auditMasked = ['account_no'];`), `Modules/Organisation/app/Models/Company.php` (use `Auditable`).
- Modify: `Modules/AccessControl` — add the audit viewer screen (copy from `ecw_reporting_app`), route `access-control.audit.index`, permission `audit.view`.
- Test: `tests/Feature/AuditTest.php`.

## Schema — `audit_logs`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK users, null | null for console and import |
| `company_id` | unsigned bigint null | copied from the model when it has one; no FK |
| `auditable_type` | string(150) | model class |
| `auditable_id` | unsigned bigint | |
| `event` | string(10) | `CREATED`, `UPDATED`, `DELETED` |
| `old_values` | json null | |
| `new_values` | json null | |
| `reason` | text null | |
| `ip` | string(45) null | |
| `created_at` | datetime | no `updated_at` |

Index (`auditable_type`, `auditable_id`); index `created_at`.

## Interfaces (must match exactly)

```php
namespace App\Support\Audit;

/** Model trait. Hooks created/updated/deleted. */
trait Auditable
{
    // optional on the model: protected array $auditMasked = [];   // attributes stored masked
    // optional on the model: protected array $auditExcluded = []; // attributes never recorded
    public function auditLogs(): \Illuminate\Database\Eloquent\Relations\MorphMany;
}

final class AuditLogger
{
    /** Everything audited inside $fn gets $reason. Nested calls: the innermost reason wins. Returns $fn's result. */
    public static function withReason(string $reason, \Closure $fn): mixed;
}
```

## Rules

- `CREATED`: `new_values` = all attributes except `id`, `created_at`, `updated_at` and excluded ones; `old_values` null.
- `UPDATED`: only attributes that changed, in both `old_values` and `new_values`. `updated_at` is never recorded. If nothing but `updated_at` changed, write no row.
- `DELETED`: `old_values` = all attributes; `new_values` null.
- Masked attribute: stored as `'***'` followed by the last 4 characters of the value; a value of 4 characters or fewer is stored as `'***'`; null stays null.
- Enum and date attributes are stored as their string value (`'ACTIVE'`, `'2026-12-01'`).
- `AuditLog` has no update or delete: override `save()` on an existing row and `delete()` to throw `\LogicException`.
- The audit viewer filters by date range, user, model type, event; shows old → new per field; read-only.

## Tests — `tests/Feature/AuditTest.php`

```php
public function test_create_records_new_values(): void
{
    $c = Company::factory()->create();
    $u = User::factory()->allCompanies()->create();
    $this->actingAs($u);

    $p = ScopedProbe::create(['company_id' => $c->id, 'name' => 'A']);

    $log = AuditLog::where('auditable_type', ScopedProbe::class)->sole();
    $this->assertSame('CREATED', $log->event);
    $this->assertSame($p->id, $log->auditable_id);
    $this->assertSame($u->id, $log->user_id);
    $this->assertSame($c->id, $log->company_id);
    $this->assertNull($log->old_values);
    $this->assertSame('A', $log->new_values['name']);
    $this->assertArrayNotHasKey('updated_at', $log->new_values);
}

public function test_update_records_only_changed_fields(): void
{
    $p = ScopedProbe::factory()->create(['name' => 'A']);
    $p->update(['name' => 'B']);

    $log = AuditLog::where('event', 'UPDATED')->sole();
    $this->assertSame(['name' => 'A'], $log->old_values);
    $this->assertSame(['name' => 'B'], $log->new_values);
}

public function test_saving_without_changes_writes_nothing(): void
{
    $p = ScopedProbe::factory()->create(['name' => 'A']);
    $p->touch();
    $p->update(['name' => 'A']);
    $this->assertSame(0, AuditLog::where('event', 'UPDATED')->count());
}

public function test_delete_records_old_values(): void
{
    $p = ScopedProbe::factory()->create(['name' => 'A']);
    $p->delete();
    $log = AuditLog::where('event', 'DELETED')->sole();
    $this->assertSame('A', $log->old_values['name']);
    $this->assertNull($log->new_values);
}

public function test_masked_field_is_not_stored_in_clear(): void
{
    $p = ScopedProbe::factory()->create(['account_no' => '0012345678']);
    $p->update(['account_no' => '0098766789']);

    $log = AuditLog::where('event', 'UPDATED')->sole();
    $this->assertSame('***5678', $log->old_values['account_no']);
    $this->assertSame('***6789', $log->new_values['account_no']);
}

public function test_short_masked_value_is_fully_hidden(): void
{
    ScopedProbe::factory()->create(['account_no' => '1234']);
    $this->assertSame('***', AuditLog::where('event', 'CREATED')->where('auditable_type', ScopedProbe::class)->sole()->new_values['account_no']);
}

public function test_reason_is_attached(): void
{
    $p = ScopedProbe::factory()->create(['name' => 'A']);
    AuditLogger::withReason('Correction after HR review', fn () => $p->update(['name' => 'B']));
    $this->assertSame('Correction after HR review', AuditLog::where('event', 'UPDATED')->sole()->reason);
}

public function test_audit_rows_cannot_be_changed_or_deleted(): void
{
    ScopedProbe::factory()->create();
    $log = AuditLog::first();
    $before = AuditLog::count();

    try { $log->update(['reason' => 'x']); $this->fail('update allowed'); } catch (\LogicException) {}
    try { $log->delete(); $this->fail('delete allowed'); } catch (\LogicException) {}
    $this->assertSame($before, AuditLog::count());
    $this->assertNull($log->fresh()->reason);
}

public function test_audit_viewer_needs_permission(): void
{
    $this->actingAs(User::factory()->allCompanies()->create(['must_change_password' => false]))
        ->get(route('access-control.audit.index'))->assertForbidden();
    $this->actingAs(User::factory()->allCompanies()->withPermissions('audit.view')->create(['must_change_password' => false]))
        ->get(route('access-control.audit.index'))->assertOk();
}
```

## Done when

- All tests pass; suite and Pint clean.
- By hand: create a company row with tinker, then change its name → both entries appear in the audit viewer with old and new values.

## Do not

- Do not audit `users`, `sessions`, `jobs`, permission tables or `audit_logs` itself.
- Do not record `updated_at`.
