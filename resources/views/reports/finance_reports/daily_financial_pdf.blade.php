<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Daily Financial Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 15px; }
        .company-info { margin-bottom: 8px; }
        .total { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: left; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
        <p class="company-info">
            {{ $companyDetails->address ?? '' }} <br>
            Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
        </p>
        <h2>Daily Financial Report</h2>
        <p>From: {{ $startDate }} To: {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Total Orders</th>
                <th>Total Purchases</th>
                <th>Total Expenses</th>
                <th>After Expenses</th>
            </tr>
        </thead>
        <tbody>
            @foreach($financials as $index => $financial)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($financial->date)->format('Y-m-d') }}</td>
                    <td>{{ number_format($financial->total_orders, 2) }}</td>
                    <td>{{ number_format($financial->total_purchases, 2) }}</td>
                    <td>{{ number_format($financial->total_expenses, 2) }}</td>
                    <td>{{ number_format($financial->after_expenses, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
