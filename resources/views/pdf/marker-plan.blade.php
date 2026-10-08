<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Marker Plan {{ $plan->ref_no }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 17px; margin: 0 0 2px 0; }
        h2 { font-size: 12px; margin: 10px 0 4px 0; }
        .muted { color: #555; font-size: 9px; }
        table { border-collapse: collapse; width: 100%; }
        tr { page-break-inside: avoid; }

        .info { margin: 8px 0 4px 0; }
        .info td { border: 1px solid #999; padding: 4px 6px; }
        .info td.label { background: #f0f0f0; font-weight: bold; width: 10%; }

        .grid th, .grid td { border: 1px solid #333; padding: 7px 6px; text-align: center; }
        .grid th { background: #e8e8e8; }
        .grid .left { text-align: left; }

        .sign { margin-top: 10px; }
        .sign td { width: 33%; padding: 38px 20px 0 20px; text-align: center; }
        .sign .line { border-top: 1px solid #333; padding-top: 3px; }
    </style>
</head>
<body>
    <h1>Marker Plan</h1>
    <div class="muted">
        Created: {{ $plan->created_at->format('d M Y H:i') }} &nbsp;|&nbsp;
        Last updated: {{ $plan->updated_at->format('d M Y H:i') }} &nbsp;|&nbsp;
        Printed: {{ $printedAt->format('d M Y H:i') }}
    </div>

    <table class="info">
        <tr>
            <td class="label">Ref No</td><td><strong>{{ $plan->ref_no }}</strong></td>
            <td class="label">SKCL No</td><td>{{ $order->skcl_no }}</td>
            <td class="label">File No</td><td>{{ $order->file_no }}</td>
            <td class="label">Buyer</td><td>{{ $order->buyer?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Style</td><td>{{ $order->style }}</td>
            <td class="label">Item</td><td>{{ $order->item_name }}</td>
            <td class="label">Color</td><td>{{ $order->color }}</td>
            <td class="label">Shipment</td><td>{{ $order->shipment_date?->format('d M Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Table</td><td>{{ $tableName ?? '-' }}</td>
            <td class="label">Lay Quantity</td><td><strong>{{ $plan->fixed_qty ?? '-' }}</strong></td>
            <td class="label">Status</td><td>{{ $statusLabel }}</td>
            <td class="label">Total Qty</td><td>{{ $totalQty }}</td>
        </tr>
    </table>

    <h2>Size wise Ratio</h2>

    <table class="grid">
        <thead>
            <tr>
                <th class="left">Size</th>
                @foreach ($sizes as $size)
                    <th>{{ $size }}</th>
                @endforeach
                <th>Total Ratio</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="left"><strong>Ratio</strong></td>
                @foreach ($sizes as $size)
                    <td>{{ $ratios[$size] ?? '-' }}</td>
                @endforeach
                <td><strong>{{ $totalRatio }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td><div class="line">Prepared by</div></td>
            <td><div class="line">Checked by</div></td>
            <td><div class="line">Approved by</div></td>
        </tr>
    </table>
</body>
</html>
