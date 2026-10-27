<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CitaController;

Route::post('/citas/agendar', [CitaController::class, 'agendar']);

// El chatbot se atiende solo desde routes/web.php (/chatbot/enviar),
// porque necesita la sesión del navegador para recordar el paso de la conversación.
