<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Good Receipt Note Report</title>
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
        <h2>Good Receipt Note Report</h2>
        <p>From: {{ $startDate }} To: {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>GRN Number</th>
                <th>Received Date</th>
                <th>Purchase Order</th>
                <th>Store</th>
                <th>Item</th>
                <th>Accepted Quantity</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($grns as $index => $grn)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $grn->grn_number }}</td>
                    <td>{{ \Carbon\Carbon::parse($grn->received_date)->format('Y-m-d') }}</td>
                    <td>{{ $grn->purchase_order_number }}</td>
                    <td>{{ $grn->store_name }}</td>
                    <td>{{ $grn->item_name }}</td>
                    <td>{{ number_format($grn->accepted_quantity, 2) }}</td>
                    <td>{{ $grn->unit }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
