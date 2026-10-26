<?php

use App\Models\Surgery;
use App\Services\PdfGeneratorService;

it('genera un PDF válido para el certificado de cirugía', function () {
    $surgery = Surgery::factory()->create();

    $response = app(PdfGeneratorService::class)->inline(
        'pdf.surgery-certificate',
        ['surgery' => $surgery],
        'certificado.pdf'
    );

    expect($response->getContent())->toStartWith('%PDF-')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');
});
