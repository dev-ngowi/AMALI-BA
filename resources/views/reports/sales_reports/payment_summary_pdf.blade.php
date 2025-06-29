<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payment Summary Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 10px; }
        .total { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        .payments-table { margin-left: 20px; }
        .payments-table th, .payments-table td { font-size: 10px; }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
    <p class="company-info">
        {{ $companyDetails->address ?? '' }} <br>
        Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
    </p>
    <h2>Payment Summary Report</h2>
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
        @foreach($paymentData as $index => $order)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $order->order_number }}</td>
                <td>{{ $order->created_at->format('Y-m-d') }}</td>
                <td>{{ number_format($order->ground_total, 2) }}</td>
                <td>{{ ucfirst($order->status) }}</td>
            </tr>
            <tr>
                <td colspan="5">
                    <table class="payments-table" width="100%" border="1" cellspacing="0" cellpadding="5">
                        <thead>
                            <tr>
                                <th>Short Code</th>
                                <th>Payment Method</th>
                                <th>Payment Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->orderPayments as $payment)
                                <tr>
                                    <td>{{ $payment->payment->short_code }}</td>
                                    <td>{{ $payment->payment_method }}</td>
                                    <td>{{ $payment->payment_type }}</td>
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