<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Models\Company;
use Tests\Support\Probe\ScopedProbe;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

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

        try {
            $log->update(['reason' => 'x']);
            $this->fail('update allowed');
        } catch (\LogicException) {
        }
        try {
            $log->delete();
            $this->fail('delete allowed');
        } catch (\LogicException) {
        }
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

    public function test_datetime_values_keep_their_time(): void
    {
        $p = ScopedProbe::factory()->create([
            'occurred_at' => '2026-12-01 00:00:00',
        ]);
        $p->update(['occurred_at' => '2026-12-01 14:30:45']);

        $log = AuditLog::where('auditable_type', ScopedProbe::class)->where('event', 'UPDATED')->sole();
        $this->assertSame('2026-12-01', $log->old_values['occurred_at']);
        $this->assertSame('2026-12-01 14:30:45', $log->new_values['occurred_at']);
    }
}
