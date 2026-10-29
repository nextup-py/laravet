@php($brand = \App\Support\ClinicMail::brand())
<tr>
<td class="header" style="border-bottom: 4px solid {{ $brand['color'] }};">
@if ($brand['logo'])
<img src="{{ $brand['logo'] }}" alt="{{ $brand['name'] }}" style="max-height: 64px; max-width: 240px; height: auto; border: 0;">
@else
<span style="color: #18181b; font-size: 22px; font-weight: bold;">{{ $brand['name'] }}</span>
@endif
</td>
</tr>
