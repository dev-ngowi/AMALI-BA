<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Business Health Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 15px; }
        .company-info { margin-bottom: 8px; }
        .total { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: left; font-size: 10px; }
        .period-header { background-color: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
        <p class="company-info">
            {{ $companyDetails->address ?? '' }} <br>
            Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
        </p>
        <h2>Business Health Report</h2>
    </div>

    @foreach($healthData as $period => $data)
        <div>
            <h3>{{ $period }}</h3>
            <table>
                <thead>
                    <tr class="period-header">
                        <th>Metric</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total Sales</td>
                        <td>{{ number_format($data->total_sales, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Expenses</td>
                        <td>{{ number_format($data->total_expenses, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Total Purchases</td>
                        <td>{{ number_format($data->purchases, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Damage Loss</td>
                        <td>{{ number_format($data->damage_loss, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Profit</td>
                        <td>{{ number_format($data->profit, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Current Balance</td>
                        <td>{{ number_format($data->current_balance, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
