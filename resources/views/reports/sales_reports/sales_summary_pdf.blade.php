<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Summary</title>
    <style>
        @charset "UTF-8";
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2 { margin: 0 0 10px 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 10px; }
        .total { font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ htmlspecialchars($companyDetails['company_name'] ?? 'Company Name', ENT_QUOTES, 'UTF-8') }}</h1>
        <p class="company-info">
            {{ htmlspecialchars($companyDetails['address'] ?? '', ENT_QUOTES, 'UTF-8') }}<br>
            Email: {{ htmlspecialchars($companyDetails['email'] ?? '', ENT_QUOTES, 'UTF-8') }} |
            Phone: {{ htmlspecialchars($companyDetails['phone'] ?? '', ENT_QUOTES, 'UTF-8') }}
        </p>
        <h2>Sales Summary Report</h2>
        <p>From: {{ htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') }} To: {{ htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') }}</p>
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
                    <td>{{ htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') }}</td>
                    <td>{{ htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8') }}</td>
                    <td>{{ number_format($order['ground_total'], 2) }}</td>
                    <td>{{ htmlspecialchars(ucfirst($order['status']), ENT_QUOTES, 'UTF-8') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" class="total">Total</td>
                <td class="total">{{ $totalAmount }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>