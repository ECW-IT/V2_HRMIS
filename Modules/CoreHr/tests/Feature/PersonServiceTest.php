<?php

namespace Modules\CoreHr\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CoreHr\Exceptions\DuplicatePerson;
use Modules\CoreHr\Exceptions\InvalidPersonData;
use Modules\CoreHr\Models\Person;
use Modules\CoreHr\Services\PersonService;
use Tests\TestCase;

class PersonServiceTest extends TestCase
{
    use RefreshDatabase;

    private PersonService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(PersonService::class);
    }

    private function valid(array $o = []): array
    {
        return array_merge([
            'nic' => '853400937V', 'first_name' => 'Nimal', 'last_name' => 'Perera', 'name_with_initials' => 'N. Perera',
            'date_of_birth' => '1985-12-05', 'gender' => 'MALE',
        ], $o);
    }

    public function test_create_stores_normalised_nic_and_what_was_entered(): void
    {
        $p = $this->svc->create($this->valid());
        $this->assertSame('198534000937', $p->nic);
        $this->assertSame('853400937V', $p->nic_as_entered);
        $this->assertSame('Sri Lankan', $p->nationality);
    }

    public function test_same_person_in_old_and_new_nic_format_is_a_duplicate(): void
    {
        $first = $this->svc->create($this->valid(['nic' => '853400937V']));
        try {
            $this->svc->create($this->valid(['nic' => '198534000937']));
            $this->fail('duplicate accepted');
        } catch (DuplicatePerson $e) {
            $this->assertSame($first->id, $e->existingPersonId);
        }
        $this->assertSame(1, Person::count());
    }

    public function test_find_by_nic_accepts_either_format_and_never_throws(): void
    {
        $p = $this->svc->create($this->valid());
        $this->assertSame($p->id, $this->svc->findByNic('198534000937')->id);
        $this->assertSame($p->id, $this->svc->findByNic('853400937v')->id);
        $this->assertNull($this->svc->findByNic('rubbish'));
        $this->assertNull($this->svc->findByNic('901230456V'));
    }

    public function test_date_of_birth_not_matching_nic_is_refused(): void
    {
        $this->expectException(InvalidPersonData::class);
        $this->svc->create($this->valid(['date_of_birth' => '1985-12-06']));
    }

    public function test_gender_not_matching_nic_is_refused(): void
    {
        $this->expectException(InvalidPersonData::class);
        $this->svc->create($this->valid(['gender' => 'FEMALE']));
    }

    public function test_person_needs_nic_or_passport(): void
    {
        $this->expectException(InvalidPersonData::class);
        $this->svc->create($this->valid(['nic' => null]));
    }

    public function test_passport_only_person_is_allowed(): void
    {
        $p = $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1234567', 'date_of_birth' => '1990-01-15']));
        $this->assertNull($p->nic);
    }

    public function test_duplicate_passport_is_refused(): void
    {
        $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1234567']));
        $this->expectException(DuplicatePerson::class);
        $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1234567']));
    }

    public function test_person_younger_than_sixteen_is_refused(): void
    {
        $this->travelTo('2026-12-01');
        $this->expectException(InvalidPersonData::class);
        $this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1', 'date_of_birth' => '2010-12-02']));
    }

    public function test_person_aged_exactly_sixteen_is_allowed(): void
    {
        $this->travelTo('2026-12-01');
        $this->assertNotNull($this->svc->create($this->valid(['nic' => null, 'passport_no' => 'N1', 'date_of_birth' => '2010-12-01'])));
    }

    public function test_update_cannot_take_another_persons_nic(): void
    {
        $this->svc->create($this->valid());
        $other = $this->svc->create($this->valid(['nic' => '199012300123', 'date_of_birth' => '1990-05-02']));
        $this->expectException(DuplicatePerson::class);
        $this->svc->update($other, ['nic' => '853400937V', 'date_of_birth' => '1985-12-05']);
    }

    public function test_only_one_primary_contact_per_type(): void
    {
        $p = $this->svc->create($this->valid(['contacts' => [['type' => 'MOBILE', 'value' => '0771234567', 'is_primary' => true]]]));
        $this->svc->update($p, ['contacts' => [['type' => 'MOBILE', 'value' => '0719876543', 'is_primary' => true]]]);
        $this->assertSame(1, $p->contacts()->where('type', 'MOBILE')->where('is_primary', true)->count());
        $this->assertSame('0719876543', $p->contacts()->where('is_primary', true)->sole()->value);
    }

    public function test_person_changes_are_audited(): void
    {
        $p = $this->svc->create($this->valid());
        $this->svc->update($p, ['calling_name' => 'Nimal']);
        $this->assertSame(1, $p->auditLogs()->where('event', 'UPDATED')->count());
    }
}
