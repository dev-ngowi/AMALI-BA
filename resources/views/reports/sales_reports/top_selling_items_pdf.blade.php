<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Top Selling Items Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 10px; }
        .total { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
    <p class="company-info">
        {{ $companyDetails->address ?? '' }} <br>
        Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
    </p>
    <h2>Top Selling Items Report</h2>
    <p>From: {{ $startDate }} To: {{ $endDate }}</p>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Item Name</th>
            <th>Total Quantity</th>
            <th>Total Revenue</th>
        </tr>
    </thead>
    <tbody>
        @foreach($topItems as $index => $item)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->item_name }}</td>
                <td>{{ $item->total_quantity }}</td>
                <td>{{ number_format($item->total_revenue, 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" class="total">Total Revenue</td>
            <td class="total">{{ number_format($totalRevenue, 2) }}</td>
        </tr>
    </tbody>
</table>

</body>
</html>