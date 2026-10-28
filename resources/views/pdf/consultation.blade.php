@extends('pdf.layout', ['pdfTitle' => "Consulta - {$consultation->pet->name}"])

@section('content')
    <h2 class="brand-title" style="text-align: center;">Informe de Consulta</h2>

    <table>
        <tr>
            <th>Mascota</th>
            <td>{{ $consultation->pet->name }} ({{ $consultation->pet->species->getLabel() }}, {{ $consultation->pet->breed }})</td>
        </tr>
        <tr>
            <th>Propietario</th>
            <td>{{ $showOwnerData ? $consultation->pet->owner->full_name : $consultation->pet->owner->first_name }}</td>
        </tr>
        <tr>
            <th>Fecha de consulta</th>
            <td>{{ $consultation->consultation_date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Veterinario</th>
            <td>{{ $consultation->user->name }}</td>
        </tr>
        <tr>
            <th>Anamnesis</th>
            <td>{!! nl2br(e($consultation->anamnesis)) !!}</td>
        </tr>
        <tr>
            <th>Diagnóstico</th>
            <td>{!! nl2br(e($consultation->diagnosis)) !!}</td>
        </tr>
        <tr>
            <th>Tratamiento</th>
            <td>{!! nl2br(e($consultation->treatment)) !!}</td>
        </tr>
        <tr>
            <th>Observación</th>
            <td>{!! filled($consultation->observation) ? nl2br(e($consultation->observation)) : '—' !!}</td>
        </tr>
    </table>
@endsection
