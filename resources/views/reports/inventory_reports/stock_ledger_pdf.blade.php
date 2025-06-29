<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Stock Ledger Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1, h2, h3 { margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 20px; }
        .company-info { margin-bottom: 10px; }
        .total { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        .item-group { margin-bottom: 20px; }
        .item-header { background-color: #f0f0f0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $companyDetails->company_name ?? 'Company Name' }}</h1>
        <p class="company-info">
            {{ $companyDetails->address ?? '' }} <br>
            Email: {{ $companyDetails->email ?? '' }} | Phone: {{ $companyDetails->phone ?? '' }}
        </p>
        <h2>Stock Ledger Report</h2>
        <p>From: {{ $startDate }} To: {{ $endDate }}</p>
    </div>

    @foreach($stockLedgerData->groupBy('item_id') as $itemId => $entries)
        <div class="item-group">
            <h3>Item: {{ $entries->first()->item_name }}</h3>
            <table>
                <thead>
                    <tr class="item-header">
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Inflow</th>
                        <th>Outflow</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($entry->date)->format('Y-m-d') }}</td>
                            <td>{{ $entry->reference }}</td>
                            <td>{{ number_format($entry->inflow, 2) }}</td>
                            <td>{{ number_format($entry->outflow, 2) }}</td>
                            <td>{{ number_format($entry->balance, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</body>
</html>
