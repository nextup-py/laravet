@extends('pdf.layout', ['pdfTitle' => "Carnet de Vacunación - {$pet->name}"])

@section('content')
    <h2 class="brand-title">Carnet de Vacunación</h2>
    <p>Mascota: {{ $pet->name }} | Especie: {{ $pet->species->getLabel() }} | Raza: {{ $pet->breed }} | Fecha de nacimiento: {{ $pet->birthdate->format('d/m/Y') }}</p>
    <p>Responsable: {{ $pet->owner->full_name }} | Teléfono: {{ $pet->owner->phone }}</p>

    <table>
        <thead>
            <tr>
                <th>Vacuna</th>
                <th>Fecha de Aplicación</th>
                <th>Próxima Aplicación</th>
                <th>Lote</th>
                <th>Fabricante</th>
                <th>Veterinario</th>
            </tr>
        </thead>
        <tbody>
            @forelse($vaccinations as $vaccination)
                <tr>
                    <td>{{ $vaccination->vaccine }}</td>
                    <td>{{ \Carbon\Carbon::parse($vaccination->application_date)->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($vaccination->next_application)->format('d/m/Y') }}</td>
                    <td>{{ $vaccination->batch }}</td>
                    <td>{{ $vaccination->manufacturer }}</td>
                    <td>{{ $vaccination->user->name }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">Sin vacunaciones registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
