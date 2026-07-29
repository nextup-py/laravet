@extends('pdf.layout', ['pdfTitle' => "Certificado de Cirugía - {$surgery->pet->name}"])

@section('content')
    <h2 class="brand-title" style="text-align: center;">Certificado de Cirugía</h2>

    <p>
        Se certifica que la mascota <strong>{{ $surgery->pet->name }}</strong>
        ({{ $surgery->pet->species->getLabel() }}, {{ $surgery->pet->breed }}),
        propiedad de <strong>{{ $surgery->pet->owner->full_name }}</strong>,
        fue sometida a la siguiente intervención quirúrgica:
    </p>

    <table>
        <tr>
            <th>Fecha</th>
            <td>{{ \Carbon\Carbon::parse($surgery->date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Tipo de cirugía</th>
            <td>{{ $surgery->type }}</td>
        </tr>
        <tr>
            <th>Veterinario responsable</th>
            <td>{{ $surgery->user->name }}</td>
        </tr>
        <tr>
            <th>Observaciones</th>
            <td>{{ $surgery->observation ?: '—' }}</td>
        </tr>
    </table>
@endsection
