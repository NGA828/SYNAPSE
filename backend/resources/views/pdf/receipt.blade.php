@extends('pdf.layout')

@section('title', 'Payment receipt')

@section('content')
    <div class="doc-title">Payment Receipt</div>
    <div class="doc-subtitle">Reference {{ $payment->reference }}</div>

    <table class="panel">
        <tr>
            <td class="label">Billed to</td>
            <td class="bold">{{ $school_name }}</td>
            <td class="label">Date</td>
            <td>{{ optional($payment->paid_at ?? $payment->created_at)->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Provider</td>
            <td>{{ strtoupper(str_replace('_', ' ', $payment->provider)) }}</td>
            <td class="label">Status</td>
            <td class="bold">{{ strtoupper($payment->status) }}</td>
        </tr>
    </table>

    <table class="data" style="margin-top: 16px;">
        <thead>
            <tr>
                <th>Description</th>
                <th class="num" style="width: 28%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $description }}
                    @if ($period)<br><span class="muted">{{ $period }}</span>@endif
                </td>
                <td class="num">{{ number_format((float) $payment->amount, 0, '.', ' ') }} {{ $payment->currency }}</td>
            </tr>
            <tr>
                <td class="bold">Total paid</td>
                <td class="num bold">{{ number_format((float) $payment->amount, 0, '.', ' ') }} {{ $payment->currency }}</td>
            </tr>
        </tbody>
    </table>

    @if ($payment->sandbox)
        <p class="muted" style="margin-top: 14px;">
            This payment was processed in sandbox mode and does not represent a real transaction.
        </p>
    @endif
@endsection
