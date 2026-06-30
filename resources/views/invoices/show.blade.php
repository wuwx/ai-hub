<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1a1a1a;
            background: #f5f5f5;
            padding: 24px;
        }
        .invoice {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 48px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 2px solid #f0f0f0;
        }
        .brand h1 { font-size: 28px; font-weight: 700; color: #111; }
        .brand p { color: #666; font-size: 14px; margin-top: 4px; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { font-size: 24px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        .invoice-meta .number { font-size: 16px; font-weight: 600; margin-top: 4px; }
        .invoice-meta .status {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: {{ $invoice->status === 'paid' ? '#dcfce7' : ($invoice->status === 'overdue' ? '#fee2e2' : '#fef9c3') }};
            color: {{ $invoice->status === 'paid' ? '#166534' : ($invoice->status === 'overdue' ? '#991b1b' : '#854d0e') }};
        }
        .parties {
            display: flex;
            justify-content: space-between;
            margin-bottom: 32px;
        }
        .parties .label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #999; margin-bottom: 4px; }
        .parties .name { font-size: 16px; font-weight: 600; }
        .parties .detail { font-size: 14px; color: #666; margin-top: 2px; }
        .dates {
            display: flex;
            gap: 48px;
            margin-bottom: 32px;
            padding: 16px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        .dates .label { font-size: 12px; text-transform: uppercase; color: #999; }
        .dates .value { font-size: 14px; font-weight: 600; margin-top: 2px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
        }
        th {
            text-align: left;
            padding: 12px 16px;
            background: #f8f8f8;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #eee;
        }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        .totals {
            margin-left: auto;
            width: 300px;
            margin-bottom: 40px;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        .totals .row.total {
            border-top: 2px solid #333;
            margin-top: 8px;
            padding-top: 16px;
            font-size: 18px;
            font-weight: 700;
        }
        .footer {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid #eee;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .actions {
            text-align: center;
            margin-bottom: 24px;
        }
        .actions button {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .actions button:hover { background: #1d4ed8; }
        @media print {
            body { background: #fff; padding: 0; }
            .invoice { box-shadow: none; max-width: none; padding: 0; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="actions">
            <button onclick="window.print()">Print / Save as PDF</button>
        </div>

        <div class="header">
            <div class="brand">
                <h1>{{ config('app.name', 'AI Hub') }}</h1>
                <p>LLM API Gateway &amp; Billing Platform</p>
            </div>
            <div class="invoice-meta">
                <h2>Invoice</h2>
                <div class="number">{{ $invoice->invoice_number }}</div>
                <div class="status">{{ ucfirst($invoice->status) }}</div>
            </div>
        </div>

        <div class="parties">
            <div>
                <div class="label">Billed To</div>
                <div class="name">{{ $team->name }}</div>
            </div>
            <div>
                <div class="label">From</div>
                <div class="name">{{ config('app.name', 'AI Hub') }}</div>
            </div>
        </div>

        <div class="dates">
            <div>
                <div class="label">Billing Period</div>
                <div class="value">{{ $invoice->billing_month?->format('F Y') }}</div>
            </div>
            <div>
                <div class="label">Issued Date</div>
                <div class="value">{{ $invoice->issued_at?->format('M d, Y') ?? '—' }}</div>
            </div>
            <div>
                <div class="label">Due Date</div>
                <div class="value">{{ $invoice->due_at?->format('M d, Y') ?? '—' }}</div>
            </div>
            @if ($invoice->paid_at)
            <div>
                <div class="label">Paid Date</div>
                <div class="value">{{ $invoice->paid_at->format('M d, Y') }}</div>
            </div>
            @endif
        </div>

        @if ($invoice->items->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Tokens In</th>
                    <th>Tokens Out</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description ?: ($item->llmModel?->name ?? 'Model usage') }}</td>
                    <td>{{ number_format($item->token_input) }}</td>
                    <td>{{ number_format($item->token_output) }}</td>
                    <td class="amount">{{ number_format($item->line_subtotal_cents / 100, 2) }} {{ $invoice->currency }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <p style="text-align: center; padding: 24px; color: #999;">No itemized charges for this invoice.</p>
        @endif

        <div class="totals">
            <div class="row">
                <span>Subtotal</span>
                <span>{{ number_format($invoice->subtotal_cents / 100, 2) }} {{ $invoice->currency }}</span>
            </div>
            @if ($invoice->tax_cents > 0)
            <div class="row">
                <span>Tax</span>
                <span>{{ number_format($invoice->tax_cents / 100, 2) }} {{ $invoice->currency }}</span>
            </div>
            @endif
            <div class="row total">
                <span>Total</span>
                <span>{{ number_format($invoice->total_cents / 100, 2) }} {{ $invoice->currency }}</span>
            </div>
        </div>

        <div class="footer">
            <p>Thank you for your business. This invoice was generated automatically by {{ config('app.name', 'AI Hub') }}.</p>
            <p>For billing questions, please contact your account manager.</p>
        </div>
    </div>
</body>
</html>
