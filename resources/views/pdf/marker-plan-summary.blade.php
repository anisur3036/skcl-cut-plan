<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Marker Plan Summary {{ $info['skcl_no'] }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 17px; margin: 0 0 2px 0; }
        h2 { font-size: 12px; margin: 12px 0 4px 0; }
        .muted { color: #555; font-size: 9px; }
        table { border-collapse: collapse; width: 100%; }
        tr { page-break-inside: avoid; }

        .info { margin: 8px 0 4px 0; }
        .info td { border: 1px solid #999; padding: 4px 6px; }
        .info td.label { background: #f0f0f0; font-weight: bold; width: 10%; }

        .grid th, .grid td { border: 1px solid #333; padding: 6px 6px; text-align: center; }
        .grid th { background: #e8e8e8; }
        .grid .left { text-align: left; }
        .grid tfoot td { font-weight: bold; background: #f4f4f4; }
    </style>
</head>
<body>
    <h1>Marker Plan Summary</h1>
    <div class="muted">
        Approved marker plans only &nbsp;|&nbsp; Printed: {{ $printedAt->format('d M Y H:i') }}
    </div>

    <table class="info">
        <tr>
            <td class="label">SKCL No</td><td><strong>{{ $info['skcl_no'] }}</strong></td>
            <td class="label">File No</td><td>{{ $info['file_no'] ?: '-' }}</td>
            <td class="label">Buyer</td><td>{{ $info['buyer'] ?? '-' }}</td>
            <td class="label">Style</td><td>{{ $info['style'] ?: '-' }}</td>
        </tr>
        <tr>
            <td class="label">Shipment</td><td>{{ $info['shipment'] ?? '-' }}</td>
            <td class="label">Color</td><td>{{ $colorName !== '' ? $colorName : 'All colors' }}</td>
            <td class="label">Approved plans</td><td>{{ $info['plan_count'] }}</td>
            <td class="label">Total qty</td><td><strong>{{ $info['total_qty'] }}</strong></td>
        </tr>
    </table>

    <h2>Color wise Quantity</h2>

    @if (count($sizes) === 0)
        <p>No approved marker plan quantities for this selection.</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th class="left">Item</th>
                    <th class="left">Color</th>
                    @foreach ($sizes as $size)
                        <th>{{ $size }}</th>
                    @endforeach
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="left">{{ $row['item_name'] }}</td>
                        <td class="left">{{ $row['color_name'] }}</td>
                        @foreach ($sizes as $size)
                            <td>{{ ($row['quantities'][$size] ?? 0) ?: '-' }}</td>
                        @endforeach
                        <td><strong>{{ $row['total'] }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td class="left" colspan="2">Total</td>
                    @foreach ($sizes as $size)
                        <td>{{ $totals['by_size'][$size] ?? 0 }}</td>
                    @endforeach
                    <td>{{ $totals['grand'] }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <p class="muted">Only approved marker plans are counted in the quantities.</p>
</body>
</html>
