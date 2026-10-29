@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
    $brand = \App\Support\ClinicMail::brand();
    // Solo el botón principal toma el color de la clínica; éxito y error mantienen el suyo.
    $style = in_array($color, ['primary', 'blue'], true)
        ? "background-color: {$brand['color']}; border-color: {$brand['color']}; color: {$brand['on_color']};"
        : '';
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" style="{{ $style }}" target="_blank" rel="noopener">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
