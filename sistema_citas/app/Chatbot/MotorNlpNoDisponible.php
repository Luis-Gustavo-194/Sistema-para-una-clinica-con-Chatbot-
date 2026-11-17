<?php

namespace App\Chatbot;

use RuntimeException;

// El servicio de Python no respondió o devolvió un error; el mensaje es el que ve el paciente
class MotorNlpNoDisponible extends RuntimeException {}
