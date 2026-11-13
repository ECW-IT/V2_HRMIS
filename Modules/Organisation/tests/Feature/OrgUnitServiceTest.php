<?php

namespace Modules\Organisation\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Organisation\Enums\OrgUnitType;
use Modules\Organisation\Exceptions\DuplicateName;
use Modules\Organisation\Exceptions\InvalidOrgMove;
use Modules\Organisation\Exceptions\OrgUnitInUse;
use Modules\Organisation\Models\Company;
use Modules\Organisation\Models\OrgUnit;
use Modules\Organisation\Services\OrgUnitService;
use Tests\TestCase;

class OrgUnitServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrgUnitService $svc;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->svc = app(OrgUnitService::class);
        $this->c = Company::factory()->create();
    }

    protected function unit(
        string $code,
        OrgUnitType $type = OrgUnitType::DEPARTMENT,
        ?OrgUnit $parent = null,
        ?Company $c = null,
    ): OrgUnit {
        return $this->svc->create([
            'company_id' => ($c ?? $this->c)->id,
            'parent_id' => $parent?->id,
            'code' => $code,
            'name' => $code,
            'type' => $type,
        ]);
    }

    public function test_create_department_section_subsection_chain(): void
    {
        $d = $this->unit('PROD');
        $s = $this->unit('PAINT', OrgUnitType::SECTION, $d);
        $ss = $this->unit('PREP', OrgUnitType::SUBSECTION, $s);
        $this->assertSame($s->id, $ss->parent_id);
        $this->assertEqualsCanonicalizing([$d->id, $s->id, $ss->id], $this->svc->descendantIds($d));
        $this->assertSame([$ss->id], $this->svc->descendantIds($ss));
    }

    public function test_parent_in_another_company_is_refused(): void
    {
        $other = $this->unit('PROD', c: Company::factory()->create());
        $this->expectException(InvalidOrgMove::class);
        $this->unit('PAINT', OrgUnitType::SECTION, $other, $this->c);
    }

    public function test_section_must_sit_under_a_department(): void
    {
        $this->expectException(InvalidOrgMove::class);
        $this->unit('PAINT', OrgUnitType::SECTION, null);
    }

    public function test_moving_a_unit_under_its_own_descendant_is_refused(): void
    {
        $a = $this->unit('A');
        $b = $this->unit('B', OrgUnitType::DEPARTMENT, $a);
        $this->expectException(InvalidOrgMove::class);
        $this->svc->move($a, $b);
    }

    public function test_moving_a_unit_under_itself_is_refused(): void
    {
        $a = $this->unit('A');
        $this->expectException(InvalidOrgMove::class);
        $this->svc->move($a, $a);
    }

    public function test_move_changes_parent(): void
    {
        $a = $this->unit('A');
        $b = $this->unit('B');
        $s = $this->unit('S', OrgUnitType::SECTION, $a);
        $this->assertSame($b->id, $this->svc->move($s, $b)->parent_id);
    }

    public function test_duplicate_code_is_refused_within_a_company_but_allowed_across(): void
    {
        $this->unit('PROD');
        $this->assertNotNull($this->unit('PROD', c: Company::factory()->create()));
        $this->expectException(DuplicateName::class);
        $this->unit('PROD');
    }

    public function test_unit_with_active_children_cannot_be_deactivated(): void
    {
        $d = $this->unit('PROD');
        $this->unit('PAINT', OrgUnitType::SECTION, $d);
        $this->expectException(OrgUnitInUse::class);
        $this->svc->deactivate($d);
    }

    public function test_leaf_unit_can_be_deactivated(): void
    {
        $d = $this->unit('PROD');
        $this->svc->deactivate($d);
        $this->assertFalse($d->fresh()->is_active);
    }
}
