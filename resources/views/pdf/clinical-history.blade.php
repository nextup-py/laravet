@extends('pdf.layout', ['pdfTitle' => "Historia Clínica - {$pet->name}"])

@section('content')
    <h2 class="brand-title">Historia Clínica</h2>
    <p>Mascota: {{ $pet->name }} | Especie: {{ $pet->species->getLabel() }} | Raza: {{ $pet->breed }} | Fecha de nacimiento: {{ $pet->birthdate->format('d/m/Y') }}</p>
    <p>Propietario: {{ $showOwnerData ? $pet->owner->full_name : $pet->owner->first_name }} @if($showOwnerData)| Teléfono: {{ $pet->owner->phone }}@endif</p>

    <p>Peso actual: {{ \App\Support\Measure::kg($pet->weight) }}</p>

    @if($pet->weights->count() > 1)
        <h3>Evolución del peso (últimos {{ $pet->weights->count() }} pesajes)</h3>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Peso</th>
                    <th>Origen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pet->weights as $weight)
                    <tr>
                        <td>{{ $weight->weighed_at->format('d/m/Y') }}</td>
                        <td>{{ \App\Support\Measure::kg($weight->weight) }}</td>
                        <td>{{ $weight->source->getLabel() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>Consultas</h3>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Veterinario</th>
                <th>Peso / Temp.</th>
                <th>Anamnesis</th>
                <th>Diagnóstico</th>
                <th>Tratamiento</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pet->consultations as $consultation)
                <tr>
                    <td>{{ ($consultation->consultation_date ?? $consultation->created_at)->format('d/m/Y') }}</td>
                    <td>{{ $consultation->user->name }}</td>
                    <td>
                        @if(filled($consultation->weight)){{ \App\Support\Measure::kg($consultation->weight) }}@endif
                        @if(filled($consultation->weight) && filled($consultation->temperature))<br>@endif
                        @if(filled($consultation->temperature)){{ \App\Support\Measure::celsius($consultation->temperature) }}@endif
                        @if(blank($consultation->weight) && blank($consultation->temperature))—@endif
                    </td>
                    <td>{{ $consultation->anamnesis }}</td>
                    <td>{{ $consultation->diagnosis }}</td>
                    <td>{{ $consultation->treatment }}</td>
                    <td>{{ $consultation->observation ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Sin consultas registradas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
