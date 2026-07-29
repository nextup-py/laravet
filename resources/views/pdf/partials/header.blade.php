<div style="text-align: center;">
    @if($clinic->logo)
        <img src="{{ \Illuminate\Support\Facades\Storage::path($clinic->logo) }}" alt="{{ $clinic->name }}" style="max-height: 70px;">
    @endif
    <h2 class="brand-title" style="margin: 4px 0;">{{ $clinic->name }}</h2>
    @if($clinic->address || $clinic->phone || $clinic->whatsapp)
        <p style="margin: 2px 0; font-size: 10px;">
            {{ $clinic->address }}
            @if($clinic->phone)
                &nbsp;|&nbsp; Tel: {{ $clinic->phone }}
            @endif
            @if($clinic->whatsapp)
                &nbsp;|&nbsp; WhatsApp: {{ $clinic->whatsapp }}
            @endif
        </p>
    @endif
    @if($clinic->business_hours)
        <p style="margin: 2px 0; font-size: 10px;">{{ $clinic->business_hours }}</p>
    @endif
</div>
