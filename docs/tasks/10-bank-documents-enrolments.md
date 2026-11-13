# Task 10 — Bank accounts, documents, fingerprint enrolments

**Depends on:** 09. **Read first:** `AGENTS.md`.

## Goal

Three records attached to an employee: where they are paid, their documents, and which fingerprint-device user ID is theirs. The enrolment rule matters most: one device ID can belong to only one employee at a time, or attendance goes to the wrong person (the old data has 19 such cases).

## Files

- `Modules/CoreHr`: models `Bank`, `BankBranch`, `EmployeeBankAccount`, `DocumentType`, `EmployeeDocument`; services `BankAccountService`, `DocumentService`; controller `DocumentDownloadController`; exceptions `InvalidBankAccount`, `InvalidDocument`; factories; migrations; seeders `BankSeeder`, `DocumentTypeSeeder`; `database/seeders/data/document_types.php`.
- Create module `Modules/Attendance` with: model `BiometricEnrolment`; `app/Services/EnrolmentService.php`; exception `DeviceUserIdInUse`; factory; migration.
- Modify: `Modules/CoreHr/app/Services/EmploymentService.php` — `exit()` closes enrolments.
- Modify: `tests/Support/HrFixtures.php` — add `bankWithBranch(): array` (returns `[Bank, BankBranch]`) and `account(array $overrides = []): array` (described under Tests).
- Tests: `Modules/CoreHr/tests/Feature/BankAccountServiceTest.php`, `DocumentServiceTest.php`; `Modules/Attendance/tests/Feature/EnrolmentServiceTest.php`.

No screens (Task 13) except the download route.

## Schema

All have `id`, timestamps; models use `Auditable`.

**`banks`** — `code` string(4) unique, `name` string(150), `is_active` boolean default true.
**`bank_branches`** — `bank_id` FK, `code` string(3), `name` string(150); unique (`bank_id`, `code`).
**`employee_bank_accounts`** (`EffectiveDated`, owner `employment_id`) — `employment_id` FK, `effective_from` date, `effective_to` date null, `is_cash` boolean default false, `bank_id` FK null, `bank_branch_id` FK null, `account_no` string(30) null, `account_name` string(150) null; unique (`employment_id`, `effective_from`). Model: `protected array $auditMasked = ['account_no'];`.
**`document_types`** — `code` string(30) unique, `name` string(100), `requires_expiry` boolean default false, `is_mandatory` boolean default false, `is_active` boolean default true.
**`employee_documents`** — `person_id` FK, `employment_id` FK null, `document_type_id` FK, `file_path` string(255), `original_name` string(255), `mime_type` string(100), `size_bytes` unsigned int, `issued_on` date null, `expires_on` date null, `remarks` string(255) null, `uploaded_by` FK users null, `archived_at` datetime null.
**`biometric_enrolments`** — `employment_id` FK, `device_group` string(20), `device_user_id` unsigned int, `valid_from` date, `valid_to` date null. Index (`device_group`, `device_user_id`).

## Seed

`BankSeeder`: reads `database/seeders/data/banks.csv` and `bank_branches.csv` (columns `code,name` and `bank_code,code,name`) when the files exist; does nothing and prints a notice when they do not. The files are exported from the old HRM `banks` / `bank_branches` tables by a person — the agent does not create them.

`document_types.php` placeholder (first line `// PLACEHOLDER — confirm in Task 00`):

| code | name | requires_expiry | is_mandatory |
|---|---|---|---|
| `NIC_COPY` | Copy of NIC | no | yes |
| `BIRTH_CERT` | Birth certificate | no | no |
| `APPOINTMENT` | Appointment letter | no | yes |
| `CV` | CV | no | no |
| `EDU_CERT` | Educational certificates | no | no |
| `POLICE` | Police clearance | yes | no |
| `GS_CERT` | Grama Niladhari certificate | no | no |
| `DRIVING_LICENCE` | Driving licence | yes | no |
| `RESIGNATION` | Resignation letter | no | no |
| `OTHER` | Other | no | no |

## Interfaces (must match exactly)

```php
namespace Modules\CoreHr\Services;

final class BankAccountService
{
    /** $data: effective_from, is_cash?, bank_id?, bank_branch_id?, account_no?, account_name? */
    public function set(Employment $e, array $data): EmployeeBankAccount;
    public function on(Employment $e, \DateTimeInterface|string $date): ?EmployeeBankAccount;
}

final class DocumentService
{
    /** $meta: document_type_id, employment_id?, issued_on?, expires_on?, remarks? */
    public function upload(Person $p, \Illuminate\Http\UploadedFile $file, array $meta, ?User $by): EmployeeDocument;
    public function archive(EmployeeDocument $doc): void;       // sets archived_at; the file stays
}

namespace Modules\Attendance\Services;

final class EnrolmentService
{
    public function enrol(Employment $e, string $deviceGroup, int $deviceUserId, \DateTimeInterface|string $from): BiometricEnrolment;
    public function end(BiometricEnrolment $enrolment, \DateTimeInterface|string $on): BiometricEnrolment;
    /** Closes every open enrolment of the employment on $on. Returns rows closed. */
    public function endAllFor(Employment $e, \DateTimeInterface|string $on): int;
    /** employment_id holding the id at that moment (date part of $at, inclusive range), or null. */
    public function resolve(string $deviceGroup, int $deviceUserId, \DateTimeInterface|string $at): ?int;
}
```

## Rules

**Bank accounts**

- `effective_from` < the employment's `hire_date` → `InvalidBankAccount`.
- `is_cash = false` requires `bank_id`, `bank_branch_id`, `account_no`, `account_name`; the branch must belong to the bank → else `InvalidBankAccount`.
- `account_no`: digits only after trimming, 6–20 long → else `InvalidBankAccount`.
- `is_cash = true` → the four bank fields are stored null.
- Saved through `EffectiveDating::insert` (so a new account closes the previous one the day before).

**Documents**

- Allowed types: pdf, jpg, jpeg, png. Maximum 10 MB → else `InvalidDocument`.
- Stored on the **`private`** disk at `documents/{person_id}/{uuid}.{ext}`. Never on `public`.
- `requires_expiry` type without `expires_on` → `InvalidDocument`. `expires_on` before `issued_on` → `InvalidDocument`.
- `employment_id`, when given, must belong to the person.
- Download route `documents.download` (`GET /documents/{document}`): needs `employee.document.view` **and** the person must have at least one employment visible to the user under the company scope → else 403 / 404. Streams the file with its original name.

**Enrolments**

- `deviceUserId` < 1 → `\InvalidArgumentException` ("Device user ID must be 1 or more.").
- `from` < employment `hire_date` → `\InvalidArgumentException`.
- Another enrolment with the same (`device_group`, `device_user_id`) whose range overlaps [`from`, open) → `DeviceUserIdInUse` (message names the employee number holding it). Check inside a transaction with `FOR UPDATE` on matching rows.
- An employment may hold several enrolments (different groups), but only one open enrolment per group: enrolling again in the same group ends the previous one the day before `from`.
- `resolve`: the enrolment where `valid_from <= date(at)` and (`valid_to` is null or `valid_to >= date(at)`).
- `EmploymentService::exit()` now calls `EnrolmentService::endAllFor($e, $on)` inside its transaction.

## Tests

`EnrolmentServiceTest.php` (uses `HrFixtures`)

```php
public function test_enrol_and_resolve(): void
{
    $e = $this->hired('2026-01-01');
    $this->svc->enrol($e, 'NIT', 116, '2026-01-01');
    $this->assertSame($e->id, $this->svc->resolve('NIT', 116, '2026-03-10 08:13:37'));
    $this->assertNull($this->svc->resolve('NEG', 116, '2026-03-10 08:13:37'));
    $this->assertNull($this->svc->resolve('NIT', 117, '2026-03-10 08:13:37'));
    $this->assertNull($this->svc->resolve('NIT', 116, '2025-12-31 23:59:59'));
}

public function test_device_user_id_cannot_be_shared_by_two_active_employees(): void
{
    $a = $this->hired('2026-01-01'); $b = $this->hired('2026-01-01');
    $this->svc->enrol($a, 'NIT', 116, '2026-01-01');
    $this->expectException(DeviceUserIdInUse::class);
    $this->svc->enrol($b, 'NIT', 116, '2026-06-01');
}

public function test_same_id_in_another_device_group_is_allowed(): void
{
    $a = $this->hired(); $b = $this->hired();
    $this->svc->enrol($a, 'NIT', 116, '2026-01-01');
    $this->assertNotNull($this->svc->enrol($b, 'NEG', 116, '2026-01-01'));
}

public function test_id_can_be_reused_after_the_previous_holder_exited(): void
{
    $a = $this->hired('2026-01-01'); $b = $this->hired('2026-11-01');
    $this->svc->enrol($a, 'NIT', 12, '2026-01-01');
    app(EmploymentService::class)->exit($a, '2026-10-31', ExitReason::RESIGNATION);

    $this->svc->enrol($b, 'NIT', 12, '2026-11-01');

    $this->assertSame($a->id, $this->svc->resolve('NIT', 12, '2026-10-30 17:01:00'));
    $this->assertSame($a->id, $this->svc->resolve('NIT', 12, '2026-10-31 17:01:00'));
    $this->assertSame($b->id, $this->svc->resolve('NIT', 12, '2026-11-01 07:55:00'));
}

public function test_reuse_before_the_previous_holder_left_is_refused(): void
{
    $a = $this->hired('2026-01-01'); $b = $this->hired('2026-10-01');
    $this->svc->enrol($a, 'NIT', 12, '2026-01-01');
    app(EmploymentService::class)->exit($a, '2026-10-31', ExitReason::RESIGNATION);
    $this->expectException(DeviceUserIdInUse::class);
    $this->svc->enrol($b, 'NIT', 12, '2026-10-15');
}

public function test_device_user_id_zero_is_refused(): void
{
    $this->expectException(\InvalidArgumentException::class);
    $this->svc->enrol($this->hired(), 'NIT', 0, '2026-01-01');
}

public function test_new_enrolment_in_same_group_ends_the_old_one(): void
{
    $e = $this->hired('2026-01-01');
    $old = $this->svc->enrol($e, 'NIT', 116, '2026-01-01');
    $this->svc->enrol($e, 'NIT', 240, '2026-06-01');
    $this->assertSame('2026-05-31', $old->fresh()->valid_to->toDateString());
}

public function test_exit_closes_enrolments(): void
{
    $e = $this->hired('2026-01-01');
    $enrol = $this->svc->enrol($e, 'NIT', 116, '2026-01-01');
    app(EmploymentService::class)->exit($e, '2026-10-31', ExitReason::RESIGNATION);
    $this->assertSame('2026-10-31', $enrol->fresh()->valid_to->toDateString());
}
```

`BankAccountServiceTest.php`

```php
public function test_non_cash_account_needs_bank_branch_number_and_name(): void
{
    $e = $this->hired('2026-01-01');
    foreach (['bank_id', 'bank_branch_id', 'account_no', 'account_name'] as $missing) {
        try { $this->svc->set($e, array_diff_key($this->account(), [$missing => 1])); $this->fail($missing); } catch (InvalidBankAccount) { $this->addToAssertionCount(1); }
    }
}

public function test_branch_must_belong_to_the_bank(): void
{
    $e = $this->hired('2026-01-01');
    $this->expectException(InvalidBankAccount::class);
    $this->svc->set($e, $this->account(['bank_branch_id' => BankBranch::factory()->create()->id]));   // branch of a different bank
}

public function test_account_number_must_be_six_to_twenty_digits(): void
{
    $e = $this->hired('2026-01-01');
    foreach (['12345', '12 34 56A', str_repeat('1', 21)] as $bad) {
        try { $this->svc->set($e, $this->account(['account_no' => $bad])); $this->fail($bad); } catch (InvalidBankAccount) { $this->addToAssertionCount(1); }
    }
    $this->assertSame('0012345678', $this->svc->set($e, $this->account(['account_no' => ' 0012345678 ']))->account_no);
}

public function test_cash_payment_stores_no_bank_details(): void
{
    $acc = $this->svc->set($this->hired('2026-01-01'), ['effective_from' => '2026-01-01', 'is_cash' => true, 'account_no' => '0012345678']);
    $this->assertTrue($acc->is_cash);
    $this->assertNull($acc->account_no);
}

public function test_new_account_closes_the_previous_one(): void
{
    $e = $this->hired('2026-01-01');
    $old = $this->svc->set($e, $this->account(['effective_from' => '2026-01-01']));
    $new = $this->svc->set($e, $this->account(['effective_from' => '2026-07-01', 'account_no' => '9998887776']));
    $this->assertSame('2026-06-30', $old->fresh()->effective_to->toDateString());
    $this->assertSame($old->id, $this->svc->on($e, '2026-06-30')->id);
    $this->assertSame($new->id, $this->svc->on($e, '2026-07-01')->id);
}

public function test_account_before_hire_date_is_refused(): void
{
    $this->expectException(InvalidBankAccount::class);
    $this->svc->set($this->hired('2026-06-01'), $this->account(['effective_from' => '2026-05-01']));
}

public function test_account_number_is_masked_in_the_audit_log(): void
{
    $acc = $this->svc->set($this->hired('2026-01-01'), $this->account(['account_no' => '0012345678']));
    $this->assertSame('***5678', $acc->auditLogs()->where('event', 'CREATED')->sole()->new_values['account_no']);
}
```

(`account(array $o = [])` returns `['effective_from' => '2026-01-01', 'is_cash' => false, 'bank_id' => $bank->id, 'bank_branch_id' => $branchOfThatBank->id, 'account_no' => '0012345678', 'account_name' => 'N Perera']` merged with `$o`.)

`DocumentServiceTest.php` — `Storage::fake('private')` in `setUp`.

```php
public function test_upload_stores_on_the_private_disk(): void
{
    $p = $this->person();
    $doc = $this->svc->upload($p, UploadedFile::fake()->create('nic.pdf', 200, 'application/pdf'), ['document_type_id' => $this->type('NIC_COPY')->id], null);
    Storage::disk('private')->assertExists($doc->file_path);
    $this->assertStringStartsWith("documents/{$p->id}/", $doc->file_path);
    $this->assertSame('nic.pdf', $doc->original_name);
}

public function test_disallowed_file_type_is_refused(): void
{
    $this->expectException(InvalidDocument::class);
    $this->svc->upload($this->person(), UploadedFile::fake()->create('run.exe', 10), ['document_type_id' => $this->type('OTHER')->id], null);
}

public function test_file_over_ten_megabytes_is_refused(): void
{
    $this->expectException(InvalidDocument::class);
    $this->svc->upload($this->person(), UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'), ['document_type_id' => $this->type('OTHER')->id], null);
}

public function test_expiring_type_requires_expiry_date(): void
{
    $this->expectException(InvalidDocument::class);
    $this->svc->upload($this->person(), UploadedFile::fake()->create('lic.pdf', 10, 'application/pdf'), ['document_type_id' => $this->type('DRIVING_LICENCE', expiry: true)->id], null);
}

public function test_download_needs_permission(): void
{
    $e = $this->hired(); $doc = $this->uploadFor($e->person);
    $this->actingAs(User::factory()->forCompanies([$e->company])->create(['must_change_password' => false]))
        ->get(route('documents.download', $doc))->assertForbidden();
}

public function test_download_is_refused_for_a_person_in_another_company(): void
{
    $e = $this->hired(); $doc = $this->uploadFor($e->person);
    $this->actingAs(User::factory()->forCompanies([$this->company()])->withPermissions('employee.document.view')->create(['must_change_password' => false]))
        ->get(route('documents.download', $doc))->assertNotFound();
}

public function test_download_works_with_permission_and_company_access(): void
{
    $e = $this->hired(); $doc = $this->uploadFor($e->person);
    $this->actingAs(User::factory()->forCompanies([$e->company])->withPermissions('employee.document.view')->create(['must_change_password' => false]))
        ->get(route('documents.download', $doc))->assertOk()->assertDownload($doc->original_name);
}

public function test_archive_keeps_the_file(): void
{
    $doc = $this->uploadFor($this->person());
    $this->svc->archive($doc);
    $this->assertNotNull($doc->fresh()->archived_at);
    Storage::disk('private')->assertExists($doc->file_path);
}
```

## Done when

All tests pass; suite and Pint clean.

## Do not

- Do not create devices or punches — Task 12.
- Do not put documents on the public disk or return a direct URL to a file.
- Do not log or display a full account number to users without `employee.bank.view`.
