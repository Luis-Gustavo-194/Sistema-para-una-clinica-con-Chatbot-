<?php

// Datos de la clínica que se muestran en la portada y los paneles.
// Los valores por defecto son de ejemplo: cámbialos aquí o en el archivo .env.
return [

    'nombre' => env('CLINICA_NOMBRE', 'Clínica Santa Lucía'),

    'ciudad' => env('CLINICA_CIUDAD', 'Arequipa'),

    'eslogan' => env('CLINICA_ESLOGAN', 'Cuidamos tu salud con calidez y tecnología'),

    'telefonos' => [
        'celular' => env('CLINICA_CELULAR', '900 000 000'),
        'fijo' => env('CLINICA_TELEFONO', '(054) 000 000'),
    ],

    'direccion' => env('CLINICA_DIRECCION', 'Av. Principal 123, Arequipa'),

    'correo' => env('CLINICA_CORREO', 'contacto@clinicasantalucia.pe'),

    'horario' => env('CLINICA_HORARIO', 'Lunes a viernes de 8:00 a 20:00 · Sábados de 8:00 a 13:00'),

    // Tipos de convenio que se muestran en la portada (categorías, no marcas)
    'convenios' => [
        ['nombre' => 'EPS', 'detalle' => 'Atención con tu Entidad Prestadora de Salud'],
        ['nombre' => 'Seguros privados', 'detalle' => 'Coordinamos con tu aseguradora'],
        ['nombre' => 'Convenios empresariales', 'detalle' => 'Planes para colaboradores'],
        ['nombre' => 'Particular', 'detalle' => 'Tarifas claras sin intermediarios'],
        ['nombre' => 'SCTR', 'detalle' => 'Seguro complementario de trabajo de riesgo'],
        ['nombre' => 'Programas preventivos', 'detalle' => 'Chequeos anuales y campañas'],
    ],

    // Horas mínimas de anticipación para que el paciente cancele (web y chatbot). Recepción puede cancelar siempre.
    'cancelacion_horas_minimas' => (int) env('CITAS_HORAS_MIN_CANCELACION', 24),

    // Enviar al paciente un correo de confirmación al agendar o reprogramar
    'correo_confirmacion' => (bool) env('CITAS_CORREO_CONFIRMACION', true),

    // Datos que el Administrador puede editar en "Datos de la clínica" (se guardan en la tabla configuraciones)
    'editables' => ['nombre', 'eslogan', 'ciudad', 'direccion', 'telefonos.celular', 'telefonos.fijo', 'correo', 'horario'],
];
