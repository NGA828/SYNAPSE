@extends('pdf.layout')

@section('title', 'Report card')

@section('content')
    <div class="doc-title">Report Card</div>
    <div class="doc-subtitle">
        {{ $academic_year?->name ?? '—' }}
        @if ($semester) · {{ $semester->name }} @else · Full year @endif
    </div>

    <table class="panel">
        <tr>
            <td class="label">Student</td>
            <td class="bold">{{ $student->user?->name ?? '—' }}</td>
            <td class="label">Class</td>
            <td class="bold">{{ $class?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Matricule</td>
            <td>{{ $student->matricule ?? '—' }}</td>
            <td class="label">Class size</td>
            <td>{{ $class_size ?: '—' }}</td>
        </tr>
    </table>

    <table class="data" style="margin-top: 14px;">
        <thead>
            <tr>
                <th style="width: 34%;">Subject</th>
                <th style="width: 12%;">Code</th>
                @foreach ($components as $component)
                    <th class="num">{{ $component['name'] }} ({{ $component['weight'] }}%)</th>
                @endforeach
                <th class="num" style="width: 12%;">Average</th>
                <th class="center" style="width: 12%;">Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['subject'] }}</td>
                    <td class="muted">{{ $row['code'] ?? '—' }}</td>
                    @foreach ($components as $component)
                        <td class="num">{{ $row['scores'][$component['id']] ?? '—' }}</td>
                    @endforeach
                    <td class="num bold">{{ $row['average'] ?? '—' }}</td>
                    <td class="center">{{ $row['remark'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 4 + count($components) }}" class="center muted" style="padding: 16px;">
                        No grades have been recorded for this period.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr>
            <td class="cell">
                <div class="value">{{ $average !== null ? number_format($average, 2) : '—' }}</div>
                <div class="caption">Overall average / {{ $scale }}</div>
            </td>
            <td class="cell">
                <div class="value">{{ $rank ? $rank.'/'.$class_size : '—' }}</div>
                <div class="caption">Class rank</div>
            </td>
            <td class="cell">
                <div class="value">{{ $subjects_count }}</div>
                <div class="caption">Subjects graded</div>
            </td>
            <td class="cell">
                <div class="value">{{ $mention }}</div>
                <div class="caption">Mention</div>
            </td>
        </tr>
    </table>

    @if ($comment)
        <div class="panel" style="margin-top: 14px;">
            <span class="label">Remarks:</span> {{ $comment }}
        </div>
    @endif

    <table class="signature">
        <tr>
            <td><div class="line">Class teacher</div></td>
            <td style="text-align: right;">
                <div class="line" style="margin-left: 38%;">{{ $principal ?? 'Principal' }}</div>
            </td>
        </tr>
    </table>
@endsection
