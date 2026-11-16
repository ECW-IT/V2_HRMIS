<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border-bottom: 1px solid #ddd; padding: 3px 4px; text-align: left; }
        th.num, td.num { text-align: right; }
        .meta td { border: none; padding: 1px 4px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .muted { color: #555; }
    </style>
</head>
<body>
@foreach ($payslips as $p)
<section class="page">
    <h1>{{ $p['company_name'] }}</h1>
    <div class="muted">Payslip — {{ $p['period_label'] }}</div>

    <table class="meta">
        <tr><td>Employee no.</td><td>{{ $p['employee_no'] }}</td><td>EPF no.</td><td>{{ $p['epf_no'] }}</td></tr>
        <tr><td>Name</td><td>{{ $p['name'] }}</td><td>Job</td><td>{{ $p['job'] }}</td></tr>
        <tr><td>Org unit</td><td>{{ $p['org_unit'] }}</td><td>Bank</td><td>
            @if ($p['is_cash'])
                Cash
            @else
                {{ $p['bank_name'] }} / {{ $p['bank_branch'] }} / {{ $p['account_no_masked'] }}
            @endif
        </td></tr>
    </table>

    <h2>Attendance</h2>
    <table class="meta">
        <tr>
            <td>Expected days {{ $p['expected_days'] }}</td>
            <td>Credited days {{ $p['credited_days'] }}</td>
            <td>No-pay days {{ $p['nopay_days'] }}</td>
            <td>Late minutes {{ $p['late_minutes_chargeable'] }}</td>
            <td>OT hours {{ $p['ot_hours'] }}</td>
        </tr>
    </table>

    <h2>Earnings</h2>
    <table>
        <thead><tr><th>Component</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach ($p['earnings'] as $line)
            <tr>
                <td>{{ $line['name'] }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
                <td class="num">{{ $line['rate'] }}</td>
                <td class="num">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Deductions</h2>
    <table>
        <thead><tr><th>Component</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead>
        <tbody>
        @foreach ($p['deductions'] as $line)
            <tr>
                <td>{{ $line['name'] }}</td>
                <td class="num">{{ $line['quantity'] }}</td>
                <td class="num">{{ $line['rate'] }}</td>
                <td class="num">{{ $line['amount'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="meta" style="margin-top:10px">
        <tr><td>Gross</td><td class="num">{{ $p['gross'] }}</td></tr>
        <tr><td>Total deductions</td><td class="num">{{ $p['total_deductions'] }}</td></tr>
        <tr><td><strong>Net pay</strong></td><td class="num"><strong>{{ $p['net_pay'] }}</strong></td></tr>
    </table>

    @if (bccomp($p['carried_forward_out'], '0.00', 2) > 0)
        <p>Carried forward to next month: {{ $p['carried_forward_out'] }}</p>
    @endif

    <p class="muted">Employer EPF {{ $p['employer_epf'] }} (paid by the company); ETF {{ $p['employer_etf'] }} (paid by the company).</p>

    @if (count($p['loans']) > 0)
        <h2>Loan balances</h2>
        <ul>
            @foreach ($p['loans'] as $loan)
                <li>Outstanding {{ $loan['outstanding'] }}</li>
            @endforeach
        </ul>
    @endif

    @if (count($p['funds']) > 0)
        <h2>Fund balances</h2>
        <ul>
            @foreach ($p['funds'] as $fund)
                <li>{{ $fund['fund'] }}: {{ $fund['balance'] }}</li>
            @endforeach
        </ul>
    @endif

    <p class="muted">Year to date — tax {{ $p['ytd_tax'] }}; EPF (employee) {{ $p['ytd_epf_employee'] }}.</p>
</section>
@endforeach
</body>
</html>
