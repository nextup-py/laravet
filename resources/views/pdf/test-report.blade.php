@extends('pdf.layout', ['pdfTitle' => "Reporte de Prueba Laboratorial - {$test->pet->name}"])

@section('content')
    <h2 class="brand-title">Reporte de Prueba de Laboratorio</h2>

    <table>
        <tr>
            <th>Mascota</th>
            <td>{{ $test->pet->name }}</td>
        </tr>
        <tr>
            <th>Fecha</th>
            <td>{{ \Carbon\Carbon::parse($test->date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Tipo de examen</th>
            <td>{{ $test->type }}</td>
        </tr>
        <tr>
            <th>Veterinario</th>
            <td>{{ $test->user->name }}</td>
        </tr>
        <tr>
            <th>Observaciones</th>
            <td>{{ $test->observation ?: '—' }}</td>
        </tr>
        <tr>
            <th>Archivos adjuntos</th>
            <td>{{ count($test->result ?? []) }} archivo(s) — disponibles en el sistema, no incluidos en este documento</td>
        </tr>
    </table>
@endsection
