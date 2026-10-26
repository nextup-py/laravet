<?php

namespace App\Http\Controllers;

use App\Models\Consultation;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\Vaccination;
use App\Services\PdfGeneratorService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega los documentos PDF de la clínica para verlos en una pestaña nueva del navegador.
 * Cada documento exige el permiso de ver el registro y el de los datos clínicos que incluye.
 */
class PdfDocumentController extends Controller
{
    public function __construct(private readonly PdfGeneratorService $pdf) {}

    public function clinicalHistory(Pet $pet): Response
    {
        Gate::authorize('view', $pet);
        Gate::authorize('viewAny', Consultation::class);

        return $this->pdf->inline('pdf.clinical-history', ['pet' => $pet], 'Historia-Clinica-'.Str::slug($pet->name).'.pdf');
    }

    public function petIdCard(Pet $pet): Response
    {
        Gate::authorize('view', $pet);

        return $this->pdf->inline('pdf.pet-id-card', ['pet' => $pet], 'Ficha-Mascota-'.Str::slug($pet->name).'.pdf');
    }

    public function vaccineCard(Pet $pet): Response
    {
        Gate::authorize('view', $pet);
        Gate::authorize('viewAny', Vaccination::class);

        return $this->pdf->inline(
            'pdf.vaccine-card',
            ['pet' => $pet, 'vaccinations' => $pet->vaccinations],
            'Carnet-Vacunacion-'.Str::slug($pet->name).'.pdf',
        );
    }

    public function consultation(Consultation $consultation): Response
    {
        Gate::authorize('view', $consultation);

        return $this->pdf->inline(
            'pdf.consultation',
            ['consultation' => $consultation],
            'Consulta-'.Str::slug($consultation->pet->name)."-{$consultation->id}.pdf",
        );
    }

    public function surgery(Surgery $surgery): Response
    {
        Gate::authorize('view', $surgery);

        return $this->pdf->inline(
            'pdf.surgery-certificate',
            ['surgery' => $surgery],
            'Certificado-Cirugia-'.Str::slug($surgery->pet->name)."-{$surgery->id}.pdf",
        );
    }

    public function test(Test $test): Response
    {
        Gate::authorize('view', $test);

        return $this->pdf->inline(
            'pdf.test-report',
            ['test' => $test],
            'Reporte-Prueba-'.Str::slug($test->pet->name)."-{$test->id}.pdf",
        );
    }
}
