<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchase Order Summary Report</title>
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
        <h2>Purchase Order Summary Report</h2>
        <p>From: {{ $startDate }} To: {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Status</th>
                <th>Order Count</th>
                <th>Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($summary as $index => $row)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->date)->format('Y-m-d') }}</td>
                    <td>{{ $row->status }}</td>
                    <td>{{ $row->order_count }}</td>
                    <td>{{ number_format($row->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
