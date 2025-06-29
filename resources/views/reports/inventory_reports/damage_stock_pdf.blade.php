<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Damage Stock Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 10px; }
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
        <h2>Damage Stock Report</h2>
        <p>From: {{ $startDate }} To: {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Item Name</th>
                <th>Store Name</th>
                <th>Quantity</th>
                <th>Last Sale Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach($damageStocks as $index => $stock)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $stock->item_name }}</td>
                    <td>{{ $stock->store_name }}</td>
                    <td>{{ number_format($stock->quantity, 2) }}</td>
                    <td>{{ $stock->last_sale_date }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
