<?php

use App\Models\Surgery;
use App\Services\PdfGeneratorService;

it('genera un PDF válido para el certificado de cirugía', function () {
    $surgery = Surgery::factory()->create();

    $response = app(PdfGeneratorService::class)->download(
        'pdf.surgery-certificate',
        ['surgery' => $surgery],
        'certificado.pdf'
    );

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect($content)->toStartWith('%PDF-');
});
