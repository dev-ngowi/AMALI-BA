<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Expenses Report</title>
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
        <h2>Expenses Report</h2>
        <p>From: {{ $startDate }} To: {{ $endDate }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Description</th>
                <th>Reference</th>
                <th>User</th>
                <th>Linked Item</th>
                <th>Store</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expenses as $index => $expense)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($expense->expense_date)->format('Y-m-d') }}</td>
                    <td>{{ $expense->expense_type }}</td>
                    <td>{{ number_format($expense->amount, 2) }}</td>
                    <td>{{ $expense->description }}</td>
                    <td>{{ $expense->reference_number }}</td>
                    <td>{{ $expense->user_name }}</td>
                    <td>{{ $expense->linked_item_name }}</td>
                    <td>{{ $expense->store_name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
