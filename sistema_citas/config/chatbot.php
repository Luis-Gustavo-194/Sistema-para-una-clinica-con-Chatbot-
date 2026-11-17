<?php

// Asistente virtual: motor NLP de Python (intenciones) y umbrales de confianza.
// Las entidades (especialidad, médico, fecha, hora, DNI) se resuelven en Laravel (App\Chatbot).
return [

    // Servicio FastAPI que clasifica la intención (en Railway: http://chatbot.railway.internal:8001)
    'nlp_url' => env('CHATBOT_NLP_URL', 'http://127.0.0.1:8001'),

    'timeout' => (int) env('CHATBOT_NLP_TIMEOUT', 5),

    // Puntaje (margen de LinearSVC) desde el que se acepta la intención sin preguntar
    'umbral_confianza' => (float) env('CHATBOT_UMBRAL_CONFIANZA', 0.0),

    // Entre este valor y umbral_confianza el bot pregunta "¿Quisiste decir…?"; por debajo, no entendió
    'umbral_aclaracion' => (float) env('CHATBOT_UMBRAL_ACLARACION', -0.5),

    // Etiqueta de cada intención en los botones de aclaración y del menú
    'intenciones' => [
        'agendar_cita' => 'Agendar una cita',
        'cancelar_cita' => 'Cancelar una cita',
        'reprogramar_cita' => 'Reprogramar una cita',
        'consultar_especialidades' => 'Ver especialidades',
        'consultar_agenda_medico' => 'Ver mi agenda (médicos)',
        'saludo' => 'Saludar',
    ],

    // Opciones que se ofrecen cuando el bot no entiende o el motor NLP está apagado
    'menu' => ['agendar_cita', 'cancelar_cita', 'reprogramar_cita', 'consultar_especialidades'],
];
