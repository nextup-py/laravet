<!-- resources/views/pdf/vaccine-card.blade.php -->

<!DOCTYPE html>
<html>

<head>
    <title>Carnet de Vacunación - {{ $pet->name }}</title>
    <style>
        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="header">
        @if($clinic->logo)
            <img src="{{ Illuminate\Support\Facades\Storage::path($clinic->logo) }}" alt="{{ $clinic->name }}" style="max-height: 80px;">
        @endif
        <h2>{{ $clinic->name }}</h2>
        @if($clinic->address || $clinic->phone)
            <p>{{ $clinic->address }}{{ $clinic->address && $clinic->phone ? ' | ' : '' }}{{ $clinic->phone }}</p>
        @endif
        @if($clinic->ruc)
            <p>RUC: {{ $clinic->ruc }}</p>
        @endif
        <h3>Carnet de Vacunación</h3>
        <p>Mascota: {{ $pet->name }} | Especie: {{ $pet->species->getLabel() }} | Raza: {{ $pet->breed }} | Fecha de nacimiento: {{ $pet->birthdate->format('d/m/Y') }}</p>
        <p>Responsable: {{ $pet->owner->full_name }} | Telefono: {{ $pet->owner->phone }}</p>
    </div>

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
            @foreach($vaccinations as $vaccination)
            <tr>
                <td>{{ $vaccination->vaccine }}</td>
                <td>{{ \Carbon\Carbon::parse($vaccination->application_date)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($vaccination->next_application)->format('d/m/Y') }}</td>
                <td>{{ $vaccination->batch }}</td>
                <td>{{ $vaccination->manufacturer }}</td>
                <td>{{ $vaccination->user->name }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>