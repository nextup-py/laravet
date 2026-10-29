@php($brand = \App\Support\ClinicMail::brand())
<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<p style="font-weight: bold;">{{ $brand['name'] }}</p>
@foreach ($brand['contact'] as $line)
<p>{{ $line }}</p>
@endforeach
@if ($brand['website'])
<p>{{ $brand['website'] }}</p>
@endif
@if ($brand['email'])
<p>Puedes responder este correo: tu respuesta llega a {{ $brand['email'] }}.</p>
@endif
<p>© {{ date('Y') }} {{ $brand['name'] }}. Todos los derechos reservados.</p>
</td>
</tr>
</table>
</td>
</tr>
