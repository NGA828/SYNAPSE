@extends('pdf.layout')

@section('title', 'Academic transcript')

@section('content')
    <div class="doc-title">Official Academic Transcript</div>
    <div class="doc-subtitle">Complete academic history</div>

    <table class="panel">
        <tr>
            <td class="label">Student</td>
            <td class="bold">{{ $student->user?->name ?? '—' }}</td>
            <td class="label">Matricule</td>
            <td>{{ $student->matricule ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Cumulative average</td>
            <td class="bold">{{ $cumulative !== null ? number_format($cumulative, 2).' / '.$scale : '—' }}</td>
            <td class="label">Years covered</td>
            <td>{{ count($years) }}</td>
        </tr>
    </table>

    @forelse ($years as $year)
        <div style="margin-top: 16px;" class="bold">
            {{ $year['academic_year']?->name ?? '—' }}
            @if (! empty($year['class'])) — {{ $year['class']->name }} @endif
        </div>
        <table class="data" style="margin-top: 5px;">
            <thead>
                <tr>
                    <th style="width: 55%;">Subject</th>
                    <th style="width: 20%;">Code</th>
                    <th class="num">Average</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($year['grades'] as $grade)
                    <tr>
                        <td>{{ $grade['subject'] }}</td>
                        <td class="muted">{{ $grade['subject_code'] ?? '—' }}</td>
                        <td class="num">{{ $grade['average'] !== null ? number_format($grade['average'], 2) : '—' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" class="bold">Year average</td>
                    <td class="num bold">
                        {{ $year['average'] !== null ? number_format($year['average'], 2) : '—' }}
                    </td>
                </tr>
            </tbody>
        </table>
    @empty
        <p class="muted center" style="margin-top: 24px;">No academic history is recorded for this student.</p>
    @endforelse

    <table class="signature">
        <tr>
            <td><div class="line">Registrar</div></td>
            <td style="text-align: right;">
                <div class="line" style="margin-left: 38%;">{{ $principal ?? 'Principal' }}</div>
            </td>
        </tr>
    </table>
@endsection
