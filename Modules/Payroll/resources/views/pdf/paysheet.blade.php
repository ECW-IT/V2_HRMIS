<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
        h1 { font-size: 12px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 2px 3px; text-align: left; }
        th.num, td.num { text-align: right; }
    </style>
</head>
<body>
<h1>{{ $report['title'] }}</h1>
<table>
    <thead>
    <tr>
        <th>Emp no.</th>
        <th>Name</th>
        <th>Job</th>
        @if (!empty($sheet1))
            <th class="num">Expected</th>
            <th class="num">Credited</th>
            <th class="num">OT hrs</th>
        @endif
        @foreach ($report['columns'] as $code => $name)
            <th class="num">{{ $code }}</th>
        @endforeach
        <th class="num">Gross</th>
        <th class="num">Deductions</th>
        <th class="num">Net</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($report['rows'] as $row)
        <tr>
            <td>{{ $row['employee_no'] }}</td>
            <td>{{ $row['name'] }}</td>
            <td>{{ $row['job'] }}</td>
            @if (!empty($sheet1))
                <td class="num">{{ $row['expected_days'] }}</td>
                <td class="num">{{ $row['credited_days'] }}</td>
                <td class="num">{{ $row['ot_hours'] }}</td>
            @endif
            @foreach ($report['columns'] as $code => $name)
                <td class="num">{{ $row[$code] ?? '' }}</td>
            @endforeach
            <td class="num">{{ $row['gross'] }}</td>
            <td class="num">{{ $row['deductions'] }}</td>
            <td class="num">{{ $row['net'] }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="{{ !empty($sheet1) ? 6 : 3 }}"><strong>Totals</strong></td>
        @foreach ($report['columns'] as $code => $name)
            <td class="num"><strong>{{ $report['totals'][$code] ?? '' }}</strong></td>
        @endforeach
        <td class="num"><strong>{{ $report['totals']['gross'] }}</strong></td>
        <td class="num"><strong>{{ $report['totals']['deductions'] }}</strong></td>
        <td class="num"><strong>{{ $report['totals']['net'] }}</strong></td>
    </tr>
    </tbody>
</table>
</body>
</html>
