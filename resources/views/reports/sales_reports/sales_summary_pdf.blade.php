<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sales Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 10px; }
        .total { font-weight: bold; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
    <p class="company-info">
        {{ $companyDetails->address ?? '' }} <br>
        Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
    </p>
    <h2>Sales Summary Report</h2>
    <p>From: {{ $startDate }} To: {{ $endDate }}</p>
</div>

<table width="100%" border="1" cellspacing="0" cellpadding="5">
    <thead>
        <tr>
            <th>#</th>
            <th>Order Number</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @foreach($salesData as $index => $order)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->created_at->format('Y-m-d') }}</td>
                <td>{{ number_format($order->ground_total, 2) }}</td>
                <td>{{ ucfirst($order->status) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" class="total">Total</td>
            <td class="total">{{ number_format($totalAmount, 2) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

</body>
</html>