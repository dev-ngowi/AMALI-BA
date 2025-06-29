<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sales Detailed Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 10px; }
        .total { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        .items-table { margin-left: 20px; }
        .items-table th, .items-table td { font-size: 10px; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
    <p class="company-info">
        {{ $companyDetails->address ?? '' }} <br>
        Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
    </p>
    <h2>Sales Detailed Report</h2>
    <p>From: {{ $startDate }} To: {{ $endDate }}</p>
</div>

<table>
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
            <tr>
                <td colspan="5">
                    <table class="items-table" width="100%" border="1" cellspacing="0" cellpadding="5">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->orderItems as $item)
                                <tr>
                                    <td>{{ $item->item_name }}</td>
                                    <td>{{ $item->quantity }}</td>
                                    <td>{{ number_format($item->price, 2) }}</td>
                                    <td>{{ number_format($item->quantity * $item->price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </td>
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