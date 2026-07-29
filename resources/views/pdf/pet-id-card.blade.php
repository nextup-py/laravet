@extends('pdf.layout', ['pdfTitle' => "Ficha de Identificación - {$pet->name}"])

@section('content')
    <h2 class="brand-title" style="text-align: center;">Ficha de Identificación de Mascota</h2>

    @if($pet->image)
        <img src="{{ \Illuminate\Support\Facades\Storage::path($pet->image) }}" style="max-height: 120px; display: block; margin: 0 auto 10px;">
    @endif

    <table>
        <tr>
            <th>Nombre</th>
            <td>{{ $pet->name }}</td>
            <th>Especie</th>
            <td>{{ $pet->species->getLabel() }}</td>
        </tr>
        <tr>
            <th>Raza</th>
            <td>{{ $pet->breed }}</td>
            <th>Edad</th>
            <td>{{ $pet->age }}</td>
        </tr>
        <tr>
            <th>Género</th>
            <td>{{ $pet->gender->getLabel() }}</td>
            <th>Tamaño</th>
            <td>{{ $pet->size->getLabel() }}</td>
        </tr>
        <tr>
            <th>Peso</th>
            <td>{{ $pet->weight }} kg</td>
            <th>Pelaje</th>
            <td>{{ $pet->fur }}</td>
        </tr>
        <tr>
            <th>Reproducción</th>
            <td colspan="3">{{ $pet->reproduction->getLabel() }}</td>
        </tr>
    </table>

    <h3>Datos del propietario</h3>
    <table>
        <tr>
            <th>Nombre</th>
            <td>{{ $pet->owner->full_name }}</td>
            <th>CI</th>
            <td>{{ $pet->owner->ci }}</td>
        </tr>
        <tr>
            <th>Teléfono</th>
            <td>{{ $pet->owner->phone }}</td>
            <th>Dirección</th>
            <td>{{ $pet->owner->address }}</td>
        </tr>
    </table>
@endsection
