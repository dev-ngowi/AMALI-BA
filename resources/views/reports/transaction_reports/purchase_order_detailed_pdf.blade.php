<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Purchase Order Detailed Report</title>
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
        <h2>Purchase Order Detailed Report</h2>
        <p>From: {{ $startDate }} To: {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Order Number</th>
                <th>Date</th>
                <th>Status</th>
                <th>Supplier</th>
                <th>Store</th>
                <th>Item</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total Price</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orders as $index => $order)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $order->order_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($order->order_date)->format('Y-m-d') }}</td>
                    <td>{{ $order->status }}</td>
                    <td>{{ $order->supplier_name }}</td>
                    <td>{{ $order->store_name }}</td>
                    <td>{{ $order->item_name }}</td>
                    <td>{{ number_format($order->quantity, 2) }}</td>
                    <td>{{ number_format($order->unit_price, 2) }}</td>
                    <td>{{ number_format($order->total_price, 2) }}</td>
                    <td>{{ $order->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
