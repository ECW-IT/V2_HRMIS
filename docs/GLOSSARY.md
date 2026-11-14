# Glossary — one name for each thing

The old HRM and payroll use several names for the same thing, and the same name for different things. HRMIS v2 uses **one term per concept**. Use the "v2 term" column in code, screens, documents and conversation. The "old term" column is only for finding things in the old systems and the import.

Copy this file to `ecw_hrmis/docs/GLOSSARY.md`. When a new term is needed, add it here first.

## How to read it

- **v2 term** — the only name to use from now on.
- **In code** — table, column or class in `ecw_hrmis`.
- **Old term(s)** — what the old systems call it, and where.
- **Why renamed** — only where the old name was misleading.

---

## 1. People and employment

| v2 term | Meaning | In code | Old term(s) | Why renamed |
|---|---|---|---|---|
| **Person** | A human being, recorded once for life | `persons` | HRM `employees` row (one per job stint) | The old row mixed the person with one period of employment, so a rejoin duplicated the person |
| **Employment** | One period of work by one person in one company | `employments` | HRM `employees` row; payroll `payr__employee_profile` | — |
| **Employee number** | Display number issued by the system, e.g. `ECW035` | `employments.employee_no` | HRM `employees.emp_id`; payroll `emp_no`; also `service_no` | Old `emp_id` looks like a key but is free text in six formats. Never join on it |
| **Legacy employee number** | The old text exactly as stored, e.g. `ECW 035` | `employments.legacy_employee_no` | HRM `emp_id` | Kept only to match old records |
| **Employment ID** | The numeric key every table uses to point at an employee | `employment_id` | HRM `employees.id`, stored under the names `emp_id`, `employee_id`, `employee_record_id` in different tables | One name everywhere |
| **Device user ID** | The number a person has on a fingerprint terminal | `biometric_enrolments.device_user_id`, `punches.device_user_id` | HRM `emp_fp_id`, `uid`, `userid` | "uid" was used for three different numbers |
| **Enrolment** | "Device user ID N in device group G belongs to this employment from date X" | `biometric_enrolments` | (did not exist — the ID sat on the employee row) | Allows an ID to be reused after someone leaves |
| **Device group** | Terminals that share one user list, normally one per site | `devices.device_group` | HRM `location` on attendance rows | — |
| **EPF number** | Member number at the Employees' Provident Fund | `employments.epf_no` | HRM `emp_etfno`; payroll `epf_no` | The HRM column says ETF but holds the EPF number |
| **Employment type** | Permanent, Probation, Intern, Contract | `employments.type` | HRM `emp_status` (1, 2, 3); payroll `employee_type` | Old "status" meant type; see next row |
| **Employment status** | Active, Suspended, Exited | `employments.status` | HRM `is_resigned`, `deleted`; payroll `status` (1/2) | Three flags replaced by one value |
| **Exit** | The end of an employment, for any reason | `EmploymentService::exit()`, `exit_date`, `exit_reason` | "Resign", `is_resigned`, `resign_date` | Not every exit is a resignation |
| **Sister-company transfer** | Exit from one company and a new employment in the other, same person | `transferToSisterCompany()` | "Company re-register", "branch transfer" | — |
| **Assignment** | Where an employee sits over a date range: org unit, job, grade, location, manager | `assignments` | Columns on HRM `employees` + `section_employees` | Now dated, so history is kept |

## 2. Organisation

| v2 term | Meaning | In code | Old term(s) | Why renamed |
|---|---|---|---|---|
| **Company** | A legal employer with its own EPF registration and payroll | `companies` | HRM `tbl_company` **and** `tbl_company_branch`; payroll `branch_id` | Decision D13: each branch that employs people is treated as its own company |
| **Location** | A physical site | `locations` | "Branch" | A place, not an employer |
| **Org unit** | A department, section or sub-section in one tree | `org_units` | `departments`, `sections`, `leave_mgt_company_departments` | Three lists became one tree |
| **Job** | What the work is, e.g. "Auto Electrician" | `hr_jobs` | `job_titles`, "designation", `leave_mgt_job_titles` | Table is `hr_jobs` because Laravel's queue owns `jobs` |
| **Grade** | The level on the single ladder; drives pay and time rules | `grades` | HRM `employeeschemas` ("employee schema"); payroll `employeeschemas` ("Staff II"…); `emp_salary_grade`; `leave_mgt_position_levels`; `hrm_roles` | "Schema" is a database word; five lists did one job |
| **Role** | What a **user** may do in the system | Spatie `roles` | `roles`; also `hrm_roles` (which were really levels) | Role is about system access only |
| **Manager** | The person an employee reports to | `assignments.manager_employment_id` | `hrm_employee_reporting`, `leave_mgt_employee_hierarchy`, "HOD", "supervisor" | — |

## 3. Time and attendance

| v2 term | Meaning | In code | Old term(s) | Why renamed |
|---|---|---|---|---|
| **Punch** | One raw fingerprint event from a terminal. Never edited | `punches` | HRM `attendances` row | "Attendance" also meant the daily result and the monthly summary |
| **Attendance adjustment** | An approved request to add a missing punch or ignore a wrong one | `attendance_adjustments` | `attendance_edits`; manual rows inside `attendances` | Raw data and corrections are now separate |
| **Work date** | The day a punch counts for. A punch before 05:00 belongs to the previous day | `timecards.work_date` | `attendances.date` | Night work crossing midnight |
| **Timecard** | The calculated result for one employee on one work date | `timecards` | (calculated on screen each time; never stored) | — |
| **Day value** | How much of a day was worked: 1, 0.5, 0.25 or 0 | `timecards.day_value` | "work_type_hrs", Full Day / Half Day / Short Day | — |
| **Shift** | Start and end time plus the thresholds that decide day value and lateness | `shifts` | HRM `job_categories` ("job category"); `job_category_duty_schedules`; `shift_types` | "Job category" sounds like a kind of job; it is a time pattern |
| **Late-from time** | First minute that counts as late, e.g. 08:06 | `shifts.late_from` | `late_on`; `late_types` | — |
| **Morning cut-off** | Latest arrival that can still earn a full day, e.g. 10:00 | `shifts.morning_cutoff` | `morning_short_endning` | — |
| **Half-day-from time** | Leaving at or after this (but before the early-leave time) earns a half day | `shifts.half_day_from` | `halfday_begining` | — |
| **Early-leave-from time** | Leaving at or after this still earns a full day, recorded as short leave | `shifts.early_leave_from` | `evening_short_begining` | — |
| **Short leave** | A full day on which the employee left in the early-leave window | `timecards.short_leave` | "short taken" | — |
| **Break** | A window inside overtime that is not paid, e.g. dinner 20:00–20:30 | `shift_breaks` | `job_category_breaks`, `break_types` | — |
| **Time profile** | An employee's shift and time rules over a date range | `employment_time_profiles` | Columns on `employees`: `job_category_id`, `ot_assign_type`, `late_available`, `attendance_applicable` | Dated, and in one place |
| **Attendance mode** | `PUNCH` = must use the terminal; `NO_PUNCH` = paid without punches | `employment_time_profiles.attendance_mode` | `attendance_applicable` (1 Direct, 2 Indirect) | — |
| **OT mode** | Which overtime an employee can earn: none, morning, evening, both | `employment_time_profiles.ot_mode` | `ot_assign_type` (null/−1/0, 1, 2, 3) | — |
| **Late minutes** | Minutes late in, plus minutes left early, for one day | `timecards.late_in_minutes`, `early_out_minutes` | `morning_lateminutes`, `evening_lateminutes`, `total_lateminutes` | — |
| **Late grace** | Late minutes forgiven each month before any deduction (240) | `companies.monthly_late_grace_minutes` | Hard-coded `- 240` | — |
| **Chargeable late minutes** | Late minutes for the month minus the grace; what payroll deducts for | `period_time_summaries.late_minutes_chargeable` | `extra_late_minutes`, "actual late min", "deduction late minutes" | "Extra" did not say extra to what |
| **Computed OT** | Overtime the system worked out from punches, before approval | `timecards.ot_morning_minutes`, `ot_evening_minutes` | OT approval screen figures | — |
| **Approved OT** | Overtime HR has approved; the only OT payroll sees | `timecards.ot_approved_minutes` | `ot_approved` table | — |
| **Holiday** | A date marked on a company's calendar | `holidays` | `holidays` | — |
| **Holiday kind** | Poya, Mercantile, Public, Company | `holidays.kind` | `holiday_type` (1, 2, 3), `holiday_types` | — |
| **Exception** | Something on a timecard that a person must resolve, e.g. a missing punch | `timecards.exception` | (not tracked) | — |

## 4. Periods and summaries

| v2 term | Meaning | In code | Old term(s) | Why renamed |
|---|---|---|---|---|
| **Pay period** | One calendar month for one company | `pay_periods` | Payroll `payr_paycycles` ("paycycle"); HRM `pay_cycles`, `leave_mgt_pay_cycles` | One list instead of three |
| **Expected working days** | Days an employee of a grade must work in a pay period to earn full basic | `period_expected_days` | HRM `entitle_working_days` ("entitle working days"), `working_week_days`; payroll `payr_expected_workdays`; `emp_payroll_workdays` | Two tables disagreed; "entitle" was unclear |
| **Worked days** | Sum of day values in the period | `period_time_summaries.worked_days` | `total_work_days` | — |
| **Credited days** | Worked days plus paid-leave days | `period_time_summaries.credited_days` | (not separate) | — |
| **Extra days** | Credited days above expected | `extra_days` | `extra_work_days` | — |
| **No-pay days** | Expected days not covered by credited days | `nopay_days` | `nopay_days`, "nopay" | — |
| **Period time summary** | The locked monthly totals per employee that payroll reads | `period_time_summaries` | HRM `employee_work_rates` ("work rate") | It holds days and hours, not rates |
| **Period close** | Checking and locking a pay period's time data | `PeriodCloseService` | "Monthly attendance approve" | — |

## 5. Leave

| v2 term | Meaning | In code | Old term(s) | Why renamed |
|---|---|---|---|---|
| **Leave type** | A kind of leave, e.g. Monthly Leave | `leave_types` | `leave_types`, `leave_mgt_leave_types`, `quater_leaves` | Four designs became one |
| **Leave policy** | How many days of a leave type a group of employees gets | `leave_policies` | `leave_mgt_pay_cycle_leave_amounts`; leave counts on `job_categories` and `employees` | — |
| **Leave ledger** | Every grant and every day taken; the balance is their sum | `leave_ledger` | `leave_mgt_leave_balances` (stored number) | A stored balance drifts; a ledger cannot |
| **Leave request** | One day (or half day) of leave asked for by an employee | `leave_requests` | `leave_mgt_leave_requests` | — |
| **Portion** | Full day, morning half or evening half | `leave_requests.portion` | `leave_type` (`full_day`/`half_day`) + `time_period` | Old `leave_type` here meant the portion, not the type |
| **Paid leave** | A leave type whose days count as credited days | `leave_types.counts_as_credited` | (not modelled) | — |

## 6. Payroll (Phase 3 — terms fixed now so earlier phases use them)

| v2 term | Meaning | In code (planned) | Old term(s) | Why renamed |
|---|---|---|---|---|
| **Pay component** | A named earning, deduction or employer contribution with a code | `pay_components` | Payroll `payr_adjustment_types` ("adjustment type"), "allowance", "deduction" | "Adjustment" suggested a correction; it is the normal build-up of pay |
| **Compensation** | An employee's basic salary and recurring components over a date range | `employee_compensation` | `payr__employee_profile.basic_salary`, `payr_employee_adjestments` | — |
| **Pay entry** | A one-off amount for one period | `pay_entries` | `payr_salary_adjustments`, `payrextraworks` | — |
| **Pay run** | One calculation of pay for a company and period | `pay_runs` | "Paysheet processing" | — |
| **Paysheet 1 / Paysheet 2** | The two reports of one pay run; sheet 1 is the EPF/ETF base | `pay_components.sheet` | "First table / second table", `paysheet_type` (0, 1, 2) | — |
| **Earned basic** | Basic salary scaled by credited days ÷ expected days | result line | "Actual basic salary" | — |
| **Carried forward** | Deductions that exceeded pay, recovered next month | `arrears_ledger` | Negative net salary | — |
| **Advance** | Money paid before pay day and recovered from pay | `salary_advances` | `payr_salary_advance_requests` | — |

## 7. System

| v2 term | Meaning | In code | Old term(s) |
|---|---|---|---|
| **Approval request** | A change waiting for someone to approve | `approval_requests` | `record_approval_requests` |
| **Audit log** | Automatic record of who changed what | `audit_logs` | `history_logs`, `employee_histories`, `payr_audit_logs` |
| **Readiness check** | A named test that an employee's data is complete | `ReadinessCheck` | (none) |
| **Legacy** | Anything belonging to the old HRM (`ecw_hrm_v2`) or old payroll (`ecw_payroll`) | `legacy_*` | — |

## Words to avoid

| Do not say | Say instead | Reason |
|---|---|---|
| emp_id, uid | employment ID / employee number / device user ID — whichever is meant | Each meant several things |
| job category | shift | |
| employee schema, schema | grade | |
| work rate | period time summary | |
| paycycle | pay period | |
| adjustment, adjustment type | pay component | |
| entitle working days | expected working days | |
| extra late minutes | chargeable late minutes | |
| branch (as an employer) | company | "Location" for the place |
| resign (for every ending) | exit | |
| attendance (for a raw event) | punch | "Attendance" is fine for the subject as a whole |
