@extends('pdf.layout')

@section('title', $document_title)

@section('content')
    <div class="doc-title">{{ $document_title }}</div>
    <div class="doc-subtitle">Reference {{ $reference }}</div>

    <p style="margin-top: 22px; line-height: 1.9; font-size: 12px;">
        {!! $body !!}
    </p>

    @if (! empty($details))
        <table class="data" style="margin-top: 18px;">
            <tbody>
                @foreach ($details as $label => $value)
                    <tr>
                        <th style="width: 34%;">{{ $label }}</th>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top: 20px; line-height: 1.8;">
        This certificate is issued at the request of the party concerned to serve for
        whatever legal purpose it may serve.
    </p>

    <table class="signature">
        <tr>
            <td>
                <div class="line">Issued at {{ $school_name }}</div>
                <div class="muted">{{ $issued_at->format('d F Y') }}</div>
            </td>
            <td style="text-align: right;">
                <div class="line" style="margin-left: 38%;">{{ $principal ?? 'Principal' }}</div>
            </td>
        </tr>
    </table>
@endsection
