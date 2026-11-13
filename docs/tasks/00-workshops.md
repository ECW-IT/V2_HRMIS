# Task 00 — Workshops (human work, not for the agent)

**Owner:** project lead with HR and Finance. **Runs alongside** Tasks 01–13. **Blocks:** Task 14 (legacy import).

The agent must never invent this data. Until a file below exists, the code uses the placeholder seed in Task 06.

## Outputs

| # | Output | File in `ecw_hrmis` | Format |
|---|---|---|---|
| 1 | Company list | `database/seeders/data/companies.php` | PHP array: `code`, `name`, `employee_no_prefix`, `employee_no_padding`, `epf_employer_no`, `etf_employer_no`, `tin` |
| 2 | Grade ladder | `database/seeders/data/grades.php` | PHP array: `code`, `name`, `rank` (1 = most senior) |
| 3 | Org units | `docs/mapping/org_units.csv` | `company_code,code,name,type,parent_code` |
| 4 | Org-unit mapping | `docs/mapping/org_unit_map.csv` | `legacy_department_id,legacy_section_id,company_code,org_unit_code` (`legacy_section_id` blank = whole department) |
| 5 | Job catalogue | `docs/mapping/jobs.csv` | `code,name` |
| 6 | Job mapping | `docs/mapping/job_map.csv` | `legacy_job_title_id,job_code` |
| 7 | Grade per employee | `docs/mapping/grade_assignment.csv` | `legacy_hrm_id,grade_code` — one row per active employee, signed off by HR |
| 8 | Company mapping | `docs/mapping/company_map.csv` | `legacy_branch_id,company_code,location_code` |
| 9 | Device groups | `docs/mapping/device_groups.csv` | `legacy_branch_id,device_group` |
| 10 | Document types | `database/seeders/data/document_types.php` | `code`, `name`, `requires_expiry`, `is_mandatory`, plus `docs/mapping/document_type_map.csv`: `legacy_attachment_type_id,document_type_code` |

## Questions to settle (from `04_decisions.md`, section 4)

- [ ] Which legal entities exist — how do Nittambuwa, Negombo and Interior Master map to companies?
- [ ] Does service continue across a sister-company transfer for gratuity and leave?
- [ ] Target go-live month.
- [ ] Which documents are mandatory for every employee?

## Cleaning in the old systems before import (Phase S)

Each of these otherwise becomes an `ERROR` in Task 14 and blocks the load:

- [ ] Duplicate employee numbers resolved (4 in HRM).
- [ ] Shared fingerprint IDs resolved (19 among active employees); no ID `0`.
- [ ] Join date filled for every active employee (8 missing).
- [ ] EPF numbers corrected; HRM and payroll agree (17 differ).
- [ ] Every HRM-active employee has an active payroll profile and the reverse (16 mismatches).
- [ ] Two active rows sharing one NIC resolved (4 NICs).

## Also started here, needed for later phases

- Payroll rule book (every earning and deduction: code, eligibility, formula, example).
- Policy book (shifts, late and OT rules, Monthly Leave rule, holiday calendar).
