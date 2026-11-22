<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Personal Information Sheet</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 18px; }
        .header { width: 100%; margin-bottom: 8px; }
        .header td { vertical-align: top; border: none; padding: 0; }
        .logo { height: 42px; }
        .title { text-align: center; color: #2A527A; font-size: 16px; font-weight: bold; margin: 0 0 4px; }
        .subtitle { text-align: center; font-size: 11px; margin: 0; }
        .meta { text-align: center; font-size: 10px; margin: 2px 0 0; }
        .photo { width: 78px; height: 95px; border: 1px solid #333; object-fit: cover; }
        .photo-placeholder { width: 78px; height: 95px; border: 1px solid #333; text-align: center; line-height: 95px; color: #888; font-size: 9px; }
        .rule { border: none; border-top: 3px solid #2A527A; margin: 10px 0 8px; }
        .section { margin: 0 0 8px; }
        .section-title {
            background: #2A527A; color: #fff; font-weight: bold; font-size: 10px;
            padding: 4px 6px; margin: 0;
        }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td { border: 1px solid #c5d0dc; padding: 3px 5px; vertical-align: top; }
        table.grid tr:nth-child(even) td { background: #f3f6f9; }
        .label { width: 18%; color: #333; font-weight: bold; }
        .value { width: 32%; }
        table.emergency { width: 100%; border-collapse: collapse; }
        table.emergency th {
            background: #2A527A; color: #fff; text-align: left; padding: 4px 5px; font-weight: bold;
        }
        table.emergency td { border: 1px solid #c5d0dc; padding: 3px 5px; }
        .signs { width: 100%; margin-top: 28px; }
        .signs td { width: 50%; text-align: center; border: none; padding-top: 28px; }
        .sign-line { border-top: 1px solid #333; width: 70%; margin: 0 auto 4px; }
        .docs { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .docs td { border: none; padding: 2px 4px; width: 33%; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1px solid #333; margin-right: 4px; text-align: center; font-size: 9px; line-height: 10px; }
        .footer { margin-top: 14px; border-top: 1px solid #2A527A; padding-top: 4px; text-align: center; color: #777; font-size: 8px; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 28%;">
                @if($logoSrc)
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo" />
                @endif
            </td>
            <td style="width: 44%; text-align: center;">
                <p class="title">PERSONAL INFORMATION SHEET</p>
                <p class="subtitle">{{ $nameWithInitials }}</p>
                <p class="meta">EMP NO : {{ $employeeNo }} | EPF NO : {{ $epfNo }}</p>
            </td>
            <td style="width: 28%; text-align: right;">
                @if($photoSrc)
                    <img class="photo" src="{{ $photoSrc }}" alt="Photo" />
                @else
                    <div class="photo-placeholder">No photo</div>
                @endif
            </td>
        </tr>
    </table>

    <hr class="rule" />

    <div class="section">
        <div class="section-title">Personal Details</div>
        <table class="grid">
            <tr>
                <td class="label">Full Name</td><td class="value">{{ $fullName }}</td>
                <td class="label">Calling Name</td><td class="value">{{ $callingName }}</td>
            </tr>
            <tr>
                <td class="label">Gender</td><td class="value">{{ $gender }}</td>
                <td class="label">Date of Birth</td><td class="value">{{ $dateOfBirth }}</td>
            </tr>
            <tr>
                <td class="label">Join Date</td><td class="value">{{ $hireDate }}</td>
                <td class="label">Region</td><td class="value">{{ $region }}</td>
            </tr>
            <tr>
                <td class="label">Marital Status</td><td class="value">{{ $maritalStatus }}</td>
                <td class="label"></td><td class="value"></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Identification Details</div>
        <table class="grid">
            <tr>
                <td class="label">NIC No</td><td class="value">{{ $nic }}</td>
                <td class="label">Passport Number</td><td class="value">{{ $passportNo }}</td>
            </tr>
            <tr>
                <td class="label">Permanent Address</td><td class="value">{{ $permanentAddress }}</td>
                <td class="label">Temporary Address</td><td class="value">{{ $temporaryAddress }}</td>
            </tr>
            <tr>
                <td class="label">Mobile No</td><td class="value">{{ $mobileNo }}</td>
                <td class="label">Landline No</td><td class="value">{{ $landlineNo }}</td>
            </tr>
            <tr>
                <td class="label">Home No</td><td class="value">{{ $homeNo }}</td>
                <td class="label">Email</td><td class="value">{{ $email }}</td>
            </tr>
            <tr>
                <td class="label">District</td><td class="value">{{ $district }}</td>
                <td class="label">GN Division</td><td class="value">{{ $gnDivision }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Employment Details</div>
        <table class="grid">
            <tr>
                <td class="label">Department</td><td class="value">{{ $department }}</td>
                <td class="label">Designation</td><td class="value">{{ $designation }}</td>
            </tr>
            <tr>
                <td class="label">Section</td><td class="value">{{ $section }}</td>
                <td class="label">Sub section</td><td class="value">{{ $subsection }}</td>
            </tr>
            <tr>
                <td class="label">Employee Category</td><td class="value">{{ $employeeCategory }}</td>
                <td class="label">Office / shop</td><td class="value">{{ $staffWorkCategory }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Emergency Contact Details</div>
        <table class="emergency">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Relation</th>
                    <th>Address</th>
                    <th>Contact No</th>
                </tr>
            </thead>
            <tbody>
                @forelse($emergencyContacts as $contact)
                    <tr>
                        <td>{{ $contact['name'] }}</td>
                        <td>{{ $contact['relationship'] }}</td>
                        <td>{{ $contact['address'] }}</td>
                        <td>{{ $contact['phone'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No emergency contacts recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Salary Details</div>
        <table class="grid">
            <tr>
                <td class="label">Basic Salary</td><td class="value">{{ $basicSalary }}</td>
                <td class="label">Bank Account</td><td class="value">{{ $bankAccount }}</td>
            </tr>
        </table>
    </div>

    <table class="signs">
        <tr>
            <td>
                <div class="sign-line"></div>
                Employee Signature
            </td>
            <td>
                <div class="sign-line"></div>
                HR Signature
            </td>
        </tr>
    </table>

    <div class="section" style="margin-top: 18px;">
        <div class="section-title">Document Availability</div>
        <table class="docs">
            @foreach(array_chunk($documents, 3) as $row)
                <tr>
                    @foreach($row as $doc)
                        <td>
                            <span class="box">{{ $doc['held'] ? 'X' : '' }}</span>
                            {{ $doc['name'] }}
                        </td>
                    @endforeach
                    @for($i = count($row); $i < 3; $i++)
                        <td></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    </div>

    <div class="footer">Generated: {{ $generatedAt }}</div>
</body>
</html>
