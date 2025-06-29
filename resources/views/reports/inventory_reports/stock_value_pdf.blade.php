<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Stock Value Report</title>
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
        <h2>Stock Value Report</h2>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Store Name</th>
                <th>Stock Quantity</th>
                <th>Stock Cost Value</th>
                <th>Sale Value</th>
                <th>Potential Profit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockValues as $index => $stock)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $stock->item_name }}</td>
                    <td>{{ $stock->store_name }}</td>
                    <td>{{ number_format($stock->stock_quantity, 2) }}</td>
                    <td>{{ number_format($stock->stock_cost_value, 2) }}</td>
                    <td>{{ number_format($stock->sale_value, 2) }}</td>
                    <td>{{ number_format($stock->potential_profit, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="total">Total</td>
                <td class="total">{{ number_format($totals->total_stock_cost, 2) }}</td>
                <td class="total">{{ number_format($totals->total_sale_value, 2) }}</td>
                <td class="total">{{ number_format($totals->potential_profit, 2) }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
