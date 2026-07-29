@extends('pdf.layout', ['pdfTitle' => "Historia Clínica - {$pet->name}"])

@section('content')
    <h2 class="brand-title">Historia Clínica</h2>
    <p>Mascota: {{ $pet->name }} | Especie: {{ $pet->species->getLabel() }} | Raza: {{ $pet->breed }} | Fecha de nacimiento: {{ $pet->birthdate->format('d/m/Y') }}</p>
    <p>Propietario: {{ $pet->owner->full_name }} | Teléfono: {{ $pet->owner->phone }}</p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Veterinario</th>
                <th>Anamnesis</th>
                <th>Diagnóstico</th>
                <th>Tratamiento</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pet->consultations as $consultation)
                <tr>
                    <td>{{ $consultation->created_at->format('d/m/Y') }}</td>
                    <td>{{ $consultation->user->name }}</td>
                    <td>{{ $consultation->anamnesis }}</td>
                    <td>{{ $consultation->diagnosis }}</td>
                    <td>{{ $consultation->treatment }}</td>
                    <td>{{ $consultation->observation ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Sin consultas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
