# Task 12 — Attendance ingest

**Owner:** Avishka. **Depends on:** 10 (enrolments). **Read first:** `AGENTS.md`; in `ecw_hrm_v2`: `app/Http/Controllers/FingerprintUserController.php` (`attendanceSync`) and `app/classes/src/` for how the current sync talks to the ZKTeco terminals.

Can be built in parallel with Tasks 11, 13 and 14.

## Goal

Raw fingerprint punches arrive from every terminal and are stored **exactly once, unchanged**, linked to the right employee. Turning punches into worked days is Phase 2 — not this task.

Background from the old data: 1,828 exact duplicate punches, about 970 rows with corrupt device serials or dates like year `0001`, 30 device IDs mapped to more than one employee, and an ingest endpoint with **no authentication**.

## Files

- `Modules/Attendance`: models `Device`, `Punch`, `PunchRejection`; enums `PunchResolution`, `RejectionReason`; services `DeviceService`, `PunchIngestService`, `PunchResolver`; `app/Support/IngestResult.php`; `app/Http/Controllers/Api/PunchIngestController.php`; `app/Http/Middleware/AuthenticateDevice.php`; `app/Console/ResolvePendingPunches.php`; Livewire `DevicesIndex`, `UnresolvedPunches`, `EmployeePunches` + views; `routes/api.php`, `routes/web.php`; factories; migrations.
- Modify: `Modules/Attendance/app/Services/EnrolmentService.php` — after `enrol()`, call `PunchResolver::resolvePending($deviceGroup)`.
- Modify: `routes/console.php` — schedule `attendance:resolve-pending` daily at 01:00.
- Tests: `Modules/Attendance/tests/Feature/PunchIngestApiTest.php`, `PunchResolverTest.php`, `DeviceScreensTest.php`.

The device-side sync agent is separate work, described at the end.

## Schema

**`devices`** (`Auditable`) — `serial_no` string(50) unique, `name` string(100), `location_id` FK, `device_group` string(20), `ip_address` string(45) null, `api_token_hash` char(64), `is_active` boolean default true, `last_seen_at` datetime null, timestamps.

**`punches`** — `id`, `device_id` FK, `device_user_id` unsigned int, `punched_at` datetime, `employment_id` FK null, `resolution` string(10), `verify_mode` unsigned tinyint null, `raw_state` unsigned tinyint null, `received_at` datetime. **Unique (`device_id`, `device_user_id`, `punched_at`).** Index (`employment_id`, `punched_at`); index (`resolution`, `device_id`). No `updated_at`.

**`punch_rejections`** — `id`, `device_id` FK null, `payload` json, `reason` string(20), `received_at` datetime.

`PunchResolution`: `RESOLVED`, `UNRESOLVED`. `RejectionReason`: `BAD_TIMESTAMP`, `BAD_USER_ID`, `MALFORMED`.

`Punch` model: **append-only**. `delete()` throws `\LogicException`. `save()` on an existing row throws `\LogicException` unless the only dirty attributes are `employment_id` and `resolution`. Not `Auditable` (volume) — the table itself is the record.

## API contract

```
POST /api/v1/attendance/punches
Authorization: Bearer <device token>
Content-Type: application/json

{
  "serial_no": "GED7243600417",
  "punches": [
    { "device_user_id": 116, "punched_at": "2026-12-01 08:13:37", "verify_mode": 1, "state": 0 }
  ]
}
```

| Response | When |
|---|---|
| `200 {"received":N,"inserted":N,"duplicates":N,"unresolved":N,"rejected":N}` | processed (even if some rows were rejected) |
| `401 {"message":"Unauthenticated."}` | no token, unknown token, or device inactive |
| `422` | `serial_no` missing or different from the token's device; `punches` missing, not an array, empty, or more than 1,000 items |

- The token is random 40 characters, shown **once** when the device is registered or the token is rotated; only `hash('sha256', $token)` is stored.
- `AuthenticateDevice` middleware finds the device by token hash and puts it on the request. The route has **no** session or user auth and is rate-limited to 120 requests per minute per device.
- `punched_at` is device local time (`Asia/Colombo`), format `Y-m-d H:i:s`.
- `received = inserted + duplicates + rejected`. `unresolved` is the part of `inserted` stored with no employee.

## Rules for each punch

1. Not an object, or `device_user_id` / `punched_at` missing, or `punched_at` not in the exact format → rejection `MALFORMED`.
2. `device_user_id` not an integer ≥ 1 → rejection `BAD_USER_ID`.
3. `punched_at` before `2020-01-01 00:00:00`, or more than 24 hours after now → rejection `BAD_TIMESTAMP`.
4. A punch with the same (`device`, `device_user_id`, `punched_at`) already stored, or repeated within this batch → counted as duplicate, not stored again.
5. Otherwise store it. `employment_id = EnrolmentService::resolve($device->device_group, $device_user_id, $punched_at)`; `resolution` = `RESOLVED` when found, else `UNRESOLVED`. `received_at` = now. `verify_mode` and `state` are stored if integers 0–255, else null.

Rejections store the offending item as `payload`. One bad row never stops the rest. Insert with `insertOrIgnore` in chunks, then count what was really inserted, so two agents sending the same batch at once cannot create duplicates. `devices.last_seen_at` is set to now on every authenticated call.

## Interfaces (must match exactly)

```php
namespace Modules\Attendance\Support;
final readonly class IngestResult
{
    public function __construct(public int $received, public int $inserted, public int $duplicates, public int $unresolved, public int $rejected) {}
    public function toArray(): array;
}

namespace Modules\Attendance\Services;

final class DeviceService
{
    /** Returns [Device, plainToken]. */
    public function register(array $data): array;           // serial_no, name, location_id, device_group, ip_address?
    public function rotateToken(Device $device): string;    // returns the new plain token; the old one stops working
    public function findByToken(string $plainToken): ?Device;   // active devices only
}

final class PunchIngestService
{
    public function ingest(Device $device, array $punches): IngestResult;
}

final class PunchResolver
{
    /** Tries to resolve UNRESOLVED punches (optionally one device group). Returns how many became RESOLVED. */
    public function resolvePending(?string $deviceGroup = null): int;
}
```

## Screens

| Screen | Route | Permission | Content |
|---|---|---|---|
| Devices | `attendance.devices.index` | `attendance.device.manage` | Table: name, serial, location, group, active, last seen (red when more than 2 hours ago during 06:00–20:00), punches today. Actions: Register (form: serial, name, location, group, IP), Rotate token, Activate/Deactivate. After register or rotate, show the token once in a dialog with a copy button and the text "This token will not be shown again." |
| Unresolved punches | `attendance.unresolved.index` | `attendance.device.manage` | Grouped by device group + device user ID: count, first and last punch time. Action "Enrol this ID to…" → pick an employee of a permitted company and a start date (default: date of the first unresolved punch) → calls `EnrolmentService::enrol` → the group's punches resolve and the row disappears. |
| Employee punches | component used in the profile (Task 13) | `attendance.punch.view` | For one employment and a month: date, time, device. Read-only. |

## Tests

`PunchIngestApiTest.php` — `setUp`: `Carbon::setTestNow('2026-12-01 09:00:00')`; `[$this->device, $this->token] = app(DeviceService::class)->register([... 'serial_no' => 'GED7243600417', 'device_group' => 'NIT'])`. Helpers: `post(array $punches, ?string $token = null, ?string $serial = null)` sends the JSON with the bearer token; `p(int $uid, string $at)` builds one punch.

```php
public function test_valid_batch_is_stored_and_resolved(): void
{
    $e = $this->hired('2026-01-01'); app(EnrolmentService::class)->enrol($e, 'NIT', 116, '2026-01-01');

    $this->post([$this->p(116, '2026-12-01 08:13:37')])
        ->assertOk()->assertExactJson(['received' => 1, 'inserted' => 1, 'duplicates' => 0, 'unresolved' => 0, 'rejected' => 0]);

    $this->assertDatabaseHas('punches', ['device_id' => $this->device->id, 'device_user_id' => 116, 'punched_at' => '2026-12-01 08:13:37', 'employment_id' => $e->id, 'resolution' => 'RESOLVED']);
    $this->assertSame('2026-12-01 09:00:00', $this->device->fresh()->last_seen_at->format('Y-m-d H:i:s'));
}

public function test_same_batch_sent_twice_inserts_once(): void
{
    $batch = [$this->p(1, '2026-12-01 08:00:00'), $this->p(2, '2026-12-01 08:01:00'), $this->p(3, '2026-12-01 08:02:00')];
    $this->post($batch)->assertJson(['inserted' => 3, 'duplicates' => 0]);
    $this->post($batch)->assertJson(['received' => 3, 'inserted' => 0, 'duplicates' => 3]);
    $this->assertDatabaseCount('punches', 3);
}

public function test_duplicate_inside_one_batch_is_stored_once(): void
{
    $this->post([$this->p(1, '2026-12-01 08:00:00'), $this->p(1, '2026-12-01 08:00:00')])
        ->assertJson(['received' => 2, 'inserted' => 1, 'duplicates' => 1]);
}

public function test_unknown_device_user_is_stored_unresolved_not_dropped(): void
{
    $this->post([$this->p(999, '2026-12-01 08:00:00')])->assertJson(['inserted' => 1, 'unresolved' => 1]);
    $this->assertDatabaseHas('punches', ['device_user_id' => 999, 'employment_id' => null, 'resolution' => 'UNRESOLVED']);
}

public function test_punch_resolves_to_the_holder_of_the_id_at_that_time(): void
{
    $a = $this->hired('2026-01-01'); $b = $this->hired('2026-11-01');
    $enrol = app(EnrolmentService::class);
    $enrol->enrol($a, 'NIT', 12, '2026-01-01');
    app(EmploymentService::class)->exit($a, '2026-10-31', ExitReason::RESIGNATION);
    $enrol->enrol($b, 'NIT', 12, '2026-11-01');

    $this->post([$this->p(12, '2026-10-30 17:01:00'), $this->p(12, '2026-11-02 07:55:00')]);

    $this->assertSame($a->id, Punch::where('punched_at', '2026-10-30 17:01:00')->sole()->employment_id);
    $this->assertSame($b->id, Punch::where('punched_at', '2026-11-02 07:55:00')->sole()->employment_id);
}

public function test_bad_timestamps_are_rejected_and_logged(): void
{
    $this->post([
        $this->p(1, '0001-08-17 08:00:00'),          // corrupt year, as seen in the old data
        $this->p(1, '2019-12-31 23:59:59'),          // before 2020
        $this->p(1, '2026-12-02 09:00:01'),          // more than 24h ahead of 2026-12-01 09:00:00
        $this->p(1, '2026-12-02 09:00:00'),          // exactly 24h ahead: accepted
    ])->assertJson(['received' => 4, 'inserted' => 1, 'rejected' => 3]);

    $this->assertSame(3, PunchRejection::where('reason', 'BAD_TIMESTAMP')->count());
    $this->assertDatabaseCount('punches', 1);
}

public function test_bad_user_ids_are_rejected(): void
{
    $this->post([$this->p(0, '2026-12-01 08:00:00'), ['device_user_id' => -5, 'punched_at' => '2026-12-01 08:00:00'], ['device_user_id' => 'abc', 'punched_at' => '2026-12-01 08:00:00']])
        ->assertJson(['inserted' => 0, 'rejected' => 3]);
    $this->assertSame(3, PunchRejection::where('reason', 'BAD_USER_ID')->count());
}

public function test_malformed_rows_are_rejected_without_stopping_the_batch(): void
{
    $this->post([['device_user_id' => 7], 'nonsense', ['device_user_id' => 7, 'punched_at' => '01/12/2026 08:00'], $this->p(7, '2026-12-01 08:00:00')])
        ->assertJson(['received' => 4, 'inserted' => 1, 'rejected' => 3]);
    $this->assertSame(3, PunchRejection::where('reason', 'MALFORMED')->count());
}

public function test_missing_or_unknown_token_gets_401(): void
{
    $this->postJson('/api/v1/attendance/punches', ['serial_no' => 'GED7243600417', 'punches' => [$this->p(1, '2026-12-01 08:00:00')]])->assertUnauthorized();
    $this->post([$this->p(1, '2026-12-01 08:00:00')], token: 'wrong')->assertUnauthorized();
    $this->assertDatabaseCount('punches', 0);
}

public function test_inactive_device_gets_401(): void
{
    $this->device->update(['is_active' => false]);
    $this->post([$this->p(1, '2026-12-01 08:00:00')])->assertUnauthorized();
}

public function test_serial_not_matching_the_token_gets_422(): void
{
    $this->post([$this->p(1, '2026-12-01 08:00:00')], serial: 'OTHER123')->assertStatus(422);
    $this->assertDatabaseCount('punches', 0);
}

public function test_empty_or_oversized_batch_gets_422(): void
{
    $this->post([])->assertStatus(422);
    $this->post(array_map(fn ($i) => $this->p($i, '2026-12-01 08:00:00'), range(1, 1001)))->assertStatus(422);
    $this->post(array_map(fn ($i) => $this->p($i, '2026-12-01 08:00:00'), range(1, 1000)))->assertOk()->assertJson(['inserted' => 1000]);
}

public function test_rotated_token_replaces_the_old_one(): void
{
    $new = app(DeviceService::class)->rotateToken($this->device);
    $this->post([$this->p(1, '2026-12-01 08:00:00')])->assertUnauthorized();                 // old token
    $this->post([$this->p(1, '2026-12-01 08:00:00')], token: $new)->assertOk();
}

public function test_plain_token_is_never_stored(): void
{
    $this->assertSame(hash('sha256', $this->token), $this->device->fresh()->api_token_hash);
    $this->assertDatabaseMissing('devices', ['api_token_hash' => $this->token]);
}

public function test_same_user_id_and_time_on_two_devices_are_two_punches(): void
{
    [$d2, $t2] = app(DeviceService::class)->register(['serial_no' => 'GED7254500730', 'name' => 'Negombo', 'location_id' => Location::factory()->create()->id, 'device_group' => 'NEG']);
    $this->post([$this->p(5, '2026-12-01 08:00:00')]);
    $this->post([$this->p(5, '2026-12-01 08:00:00')], token: $t2, serial: 'GED7254500730')->assertJson(['inserted' => 1]);
    $this->assertDatabaseCount('punches', 2);
}
```

`PunchResolverTest.php`

```php
public function test_unresolved_punches_attach_when_the_enrolment_is_added(): void
{
    $this->ingest([$this->p(999, '2026-12-01 08:00:00'), $this->p(999, '2026-12-01 17:05:00')]);
    $e = $this->hired('2026-06-01');

    app(EnrolmentService::class)->enrol($e, 'NIT', 999, '2026-12-01');          // triggers resolvePending('NIT')

    $this->assertSame(2, Punch::where('employment_id', $e->id)->where('resolution', 'RESOLVED')->count());
}

public function test_punches_before_the_enrolment_start_stay_unresolved(): void
{
    $this->ingest([$this->p(999, '2026-11-30 08:00:00'), $this->p(999, '2026-12-01 08:00:00')]);
    app(EnrolmentService::class)->enrol($this->hired('2026-06-01'), 'NIT', 999, '2026-12-01');
    $this->assertSame(1, Punch::where('resolution', 'UNRESOLVED')->count());
}

public function test_resolver_is_limited_to_the_device_group(): void
{
    // unresolved punch for id 50 on a NEG device; enrolment for id 50 exists in NIT only -> resolvePending('NIT') returns 0
}

public function test_resolved_punches_are_never_reassigned(): void
{
    // punch RESOLVED to A; run resolvePending() -> still A, return value 0
}

public function test_punch_cannot_be_edited_or_deleted(): void
{
    $this->ingest([$this->p(1, '2026-12-01 08:00:00')]);
    $punch = Punch::first();
    try { $punch->update(['punched_at' => '2026-12-01 07:00:00']); $this->fail(); } catch (\LogicException) {}
    try { $punch->delete(); $this->fail(); } catch (\LogicException) {}
    $this->assertSame('2026-12-01 08:00:00', $punch->fresh()->punched_at->format('Y-m-d H:i:s'));
}
```

`DeviceScreensTest.php`

```php
public function test_devices_screen_needs_permission(): void
public function test_registering_a_device_shows_the_token_once(): void      // after ->call('register'): assertSet('newToken', fn ($t) => strlen($t) === 40); a fresh mount shows no token
public function test_duplicate_serial_is_a_validation_error(): void
public function test_enrolling_from_the_unresolved_screen_clears_the_group(): void
// 3 unresolved punches for (NIT, 999) -> ->call('enrol', 'NIT', 999, $employment->id, '2026-12-01') -> list no longer shows 999
public function test_unresolved_screen_only_offers_employees_of_permitted_companies(): void
```

## Device sync agent (separate from the application)

A small scheduled program on a machine that can reach the terminals:

1. For each terminal, read attendance logs with the ZKTeco protocol (reuse the logic from `ecw_hrm_v2/app/classes/src`).
2. Keep, per terminal, the timestamp of the last punch successfully posted. Send only newer punches, **plus the previous 24 hours again** — the endpoint ignores duplicates, so overlap is safe and closes gaps.
3. Post in batches of up to 1,000 to `/api/v1/attendance/punches` with that terminal's token and serial.
4. On a non-200 response or timeout, do not advance the saved timestamp; retry on the next run.
5. Run every 5 minutes. Log each run: terminal, punches read, the five counters from the response.
6. Never clear logs from the terminal automatically.

Configuration (per terminal): IP, port, serial number, API token, API base URL. Tokens live in a local config file readable only by the service account — never in git.

## Done when

- All tests pass; suite and Pint clean.
- A real terminal's punches arrive through the agent; sending the same hour twice changes nothing; the Devices screen shows "last seen" updating.
- The old open endpoint in `ecw_hrm_v2` is **not** touched by this task.

## Do not

- Do not calculate worked hours, lateness or overtime.
- Do not add any way to create, edit or delete a punch by hand.
- Do not accept punches without a device token.
- Do not write to the old HRM database.
