@extends('pdf.layout', ['pdfTitle' => $report->title])

@section('content')
    <h2 class="brand-title">{{ $report->title }}</h2>
    @if($report->subtitle)
        <p>{{ $report->subtitle }} | Generado el {{ now()->format('d/m/Y H:i') }} por {{ $generatedBy }}</p>
    @endif

    <table>
        <thead>
            <tr>
                @foreach($report->headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report->rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report->headers) }}">Sin datos para el período elegido.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
