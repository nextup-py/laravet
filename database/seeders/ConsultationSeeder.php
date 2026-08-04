<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConsultationSeeder extends Seeder
{
    public function run(): void
    {
        $pets = Pet::all();
        $vets = User::all();

        // [pet_index, anamnesis, diagnóstico, tratamiento, observación, días_atrás]
        $consultations = [
            [
                0,
                'Propietario refiere que el paciente presenta vómitos y diarrea desde hace 2 días. No ha comido desde ayer. Estuvo en contacto con basura en el patio.',
                'Gastroenteritis aguda de probable origen alimentario.',
                'Metronidazol 250mg c/12hs por 5 días. Omeprazol 10mg c/24hs por 5 días. Dieta blanda por 5 días (arroz con pollo hervido). Suero oral si continúan vómitos.',
                'Se realizó hidratación subcutánea en consultorio. Control en 5 días.',
                14,
            ],
            [
                1,
                'Paciente con prurito intenso en región dorsal y lateral. Propietaria nota pérdida de pelo y costras en la piel desde hace 3 semanas. Nunca fue desparasitada externamente.',
                'Dermatitis alérgica con infección bacteriana secundaria. Posible sarna sarcóptica.',
                'Raspado cutáneo (ver resultado en exámenes). Ivermectina 0,2mg/kg SC. Cefalexina 25mg/kg c/12hs por 10 días. Shampoo clorhexidina 3 veces por semana.',
                'Se toma muestra para raspado cutáneo. Uso de collar isabelino indicado.',
                20,
            ],
            [
                2,
                'Paciente con sacudidas de cabeza frecuentes y rascado de oído derecho. Olor fétido al examen del conducto auditivo. Lleva 1 semana con el síntoma.',
                'Otitis externa bacteriana-levadura en oído derecho.',
                'Limpieza auricular con solución de clorhexidina. Otoclean 10 gotas en oído afectado c/12hs por 10 días. Control en 10 días.',
                'Otoscopia revela abundante cerumen oscuro. Muestra tomada para cultivo.',
                7,
            ],
            [
                3,
                'Cachorra con tos productiva, secreción nasal serosa y fiebre de 39.8°C. Propietaria refiere que tuvo contacto con otro perro en el barrio que falleció.',
                'Traqueobronquitis infecciosa canina (Tos de las perreras). Descartar Distemper.',
                'Doxiciclina 5mg/kg c/12hs por 7 días. Bromhexina jarabe 0.5ml c/8hs. Reposo absoluto. Aislamiento de otros animales.',
                'Se indica titulación de anticuerpos para Distemper. Pendiente resultado.',
                5,
            ],
            [
                4,
                'Paciente presenta cojera en miembro posterior derecho luego de saltar una reja. Al examen se observa herida lacerante de 4cm en la región tibial.',
                'Herida lacerante en miembro posterior derecho. Descartada fractura ósea al examen.',
                'Limpieza quirúrgica y sutura de herida bajo sedación (xilazina + ketamina). Amoxicilina-clavulánico 20mg/kg c/12hs por 7 días. Curación diaria con clorhexidina.',
                'Se realizaron 5 puntos de sutura. Control en 7 días para retirar puntos.',
                3,
            ],
            [
                5,
                'Hembra sin castrar con secreción vulvar purulenta abundante, polidipsia, poliuria y distensión abdominal. Temperatura 40.1°C. Último celo hace 8 semanas.',
                'Piómetra abierta. Caso urgente de resolución quirúrgica.',
                'Ovariohisterectomía de urgencia. Amoxicilina-clavulánico 20mg/kg c/12hs por 10 días. Analgesia postoperatoria con meloxicam 0.2mg/kg c/24hs por 3 días.',
                'Cirugía realizada sin complicaciones. Ver registro en sección Cirugías.',
                30,
            ],
            [
                6,
                'Cachorro con apatía, vómitos con sangre, diarrea hemorrágica y fiebre de 40.2°C. No está vacunado. Encontrado en la calle hace 1 mes.',
                'Parvovirus canino. Pronóstico reservado.',
                'Hospitalización. Suero fisiológico IV 60ml/kg/día. Metoclopramida 0.5mg/kg SC c/8hs. Ampicilina 22mg/kg IV c/8hs. Omeprazol 1mg/kg IV c/24hs.',
                'Paciente en estado crítico. Se explicó pronóstico al propietario. Se inician vacunaciones al alta.',
                10,
            ],
            [
                7,
                'Paciente adulto con anorexia y pérdida de peso progresiva de 2 meses. Al examen, mucosas pálidas y ganglios linfáticos aumentados de tamaño.',
                'Anemia moderada. Linfadenopatía generalizada. Pendiente diagnóstico etiológico.',
                'Hemograma completo urgente. Ehrlichia canis (ELISA). Suplemento de hierro y vitamina B12.',
                'Se sospecha Ehrlichiosis canina por epidemiología local. Aguardar resultados.',
                2,
            ],
            [
                10,
                'Gato con inapetencia y vómitos biliosos desde hace 4 días. Al examen, ictericia leve en mucosas y escleras.',
                'Lipidosis hepática felina secundaria a inapetencia prolongada.',
                'Alimentación forzada con dieta alta en proteínas. Vitamina B12 inyectable. Silimarina 70mg c/24hs. Ácido ursodesoxicólico 10mg/kg c/24hs.',
                'Se indica sonda esofágica si no mejora la ingesta voluntaria en 48hs.',
                8,
            ],
            [
                11,
                'Gata con estornudos frecuentes, secreción nasal y ocular serosa, y úlceras en la cavidad oral. Temperatura 39.5°C.',
                'Complejo respiratorio felino. Probable Calicivirus o Rinotraqueítis viral.',
                'Interferón felino recombinante 1MU SC c/24hs por 3 días. Amoxicilina 20mg/kg c/12hs por 7 días (prevenir sobreinfección bacteriana). Limpieza ocular con suero fisiológico.',
                'Aislamiento de otros felinos. Verificar estado vacunal. Propietaria refiere vacunación desactualizada.',
                4,
            ],
            [
                12,
                'Gato castrado con dificultad para orinar, llanto al intentar micción y lamido excesivo del prepucio. Sin antecedentes previos. Dieta basada en croquetas secas.',
                'Obstrucción uretral. Urolitiasis por cristales de estruvita.',
                'Desobstrucción uretral bajo anestesia (propofol IV). Sonda uretral por 48hs. Dieta húmeda exclusiva. Cloruro de amonio como acidificante urinario.',
                'Se indica cambio permanente a dieta húmeda o dieta veterinaria específica para urinario.',
                15,
            ],
            [
                13,
                'Cachorra con prurito generalizado, costras en la cabeza, cuello y orejas. Otras mascotas en el hogar presentan los mismos síntomas.',
                'Sarna notoédrica (Notoedres cati). Contagioso para otros animales y humanos.',
                'Selamectina spot-on 6mg/kg. Repetir en 21 días. Baño con shampoo acaricida. Tratar todos los animales en contacto.',
                'Se alertó al propietario sobre posible transmisión a humanos. Visita veterinaria urgente si aparecen lesiones en la familia.',
                6,
            ],
        ];

        foreach ($consultations as [$petIdx, $anamnesis, $diagnosis, $treatment, $observation, $daysAgo]) {
            $pet = $pets[$petIdx % $pets->count()];
            $vet = $vets->random();

            Consultation::create([
                'anamnesis' => $anamnesis,
                'diagnosis' => $diagnosis,
                'treatment' => $treatment,
                'observation' => $observation,
                'consultation_date' => now()->subDays($daysAgo)->toDateString(),
                'pet_id' => $pet->id,
                'user_id' => $vet->id,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }
    }
}
