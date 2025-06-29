<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Stock Level Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; } /* Reduced font size */
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 15px; }
        .company-info { margin-bottom: 8px; }
        .total { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: left; font-size: 10px; } /* Reduced padding and font */
        .status-low { background-color: #ffcccc; }
        .status-normal { background-color: #ffffcc; }
        .status-high { background-color: #ccffcc; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
        <p class="company-info">
            {{ $companyDetails->address ?? '' }} <br>
            Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
        </p>
        <h2>Stock Level Report</h2>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Store Name</th>
                <th>Stock Qty</th>
                <th>Min Qty</th>
                <th>Max Qty</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stockLevels as $index => $stock)
                <tr class="status-{{ strtolower($stock->status) }}">
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $stock->item_name }}</td>
                    <td>{{ $stock->store_name }}</td>
                    <td>{{ number_format($stock->stock_quantity, 2) }}</td>
                    <td>{{ number_format($stock->min_quantity, 2) }}</td>
                    <td>{{ number_format($stock->max_quantity, 2) }}</td>
                    <td>{{ $stock->status }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
