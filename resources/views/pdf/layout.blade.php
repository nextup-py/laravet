<!DOCTYPE html>
<html>

<head>
    <title>{{ $pdfTitle ?? $clinic->name }}</title>
    <style>
        @page {
            margin: 140px 40px 70px 40px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        header {
            position: fixed;
            top: -120px;
            left: 0;
            right: 0;
            height: 110px;
        }

        footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 50px;
            border-top: 2px solid {{ $clinic->primary_color ?? '#f59e0b' }};
            padding-top: 8px;
            font-size: 9px;
            color: #6b7280;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 11px;
        }

        th {
            background-color: {{ $clinic->primary_color ?? '#f59e0b' }};
            color: #fff;
        }

        .brand-title {
            color: {{ $clinic->primary_color ?? '#f59e0b' }};
        }
    </style>
</head>

<body>
    <header>@include('pdf.partials.header')</header>
    <footer>
        {{ $clinic->razon_social ?? $clinic->name }}
        @if($clinic->ruc)
            &nbsp;|&nbsp; RUC: {{ $clinic->ruc }}
        @endif
        @if($clinic->pdf_footer)
            <br>{{ $clinic->pdf_footer }}
        @endif
    </footer>
    <main>
        @yield('content')

        @if($clinic->pdf_signature || $clinic->pdf_legal_text)
            <div style="margin-top: 36px;">
                @if($clinic->pdf_signature)
                    <img src="{{ \Illuminate\Support\Facades\Storage::path($clinic->pdf_signature) }}" alt="Firma" style="max-height: 70px;">
                @endif
                @if($clinic->pdf_legal_text)
                    <p style="font-size: 9px; color: #6b7280; margin-top: 8px;">{{ $clinic->pdf_legal_text }}</p>
                @endif
            </div>
        @endif
    </main>
</body>

</html>
