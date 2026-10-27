<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\AdminController; // <-- Controlador del Administrador
use App\Http\Controllers\Admin\EspecialidadController;
use App\Http\Controllers\Admin\MedicoController;
use App\Http\Controllers\Medico\CitaController as MedicoCitaController;
use App\Http\Controllers\Medico\HorarioController;
use App\Http\Controllers\Medico\PanelController as MedicoPanelController;
use App\Http\Controllers\Paciente\CitaController as PacienteCitaController;
use App\Http\Controllers\Paciente\PanelController as PacientePanelController;
use App\Http\Controllers\Paciente\PerfilController;
use App\Models\Rol;

Route::get('/', [InicioController::class, 'index'])->name('inicio');

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', [AuthController::class, 'login']);

Route::get('/logout', [AuthController::class, 'logout']);

// Registro público de pacientes nuevos
Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:5,1');

Route::post('/chatbot/enviar', [ChatbotController::class, 'procesarMensaje']);

// ==========================================
// PANEL DEL MÉDICO (Protegido: solo rol Médico)
// ==========================================
Route::middleware(['auth', 'rol:' . Rol::MEDICO])->group(function () {
    // Se conserva la URL a la que redirige el login
    Route::get('/dashboard-medico', [MedicoPanelController::class, 'index'])->name('medico.panel');

    Route::prefix('medico')->name('medico.')->group(function () {
        Route::get('/citas', [MedicoCitaController::class, 'index'])->name('citas.index');
        Route::get('/citas/{cita}', [MedicoCitaController::class, 'show'])->name('citas.show');
        Route::patch('/citas/{cita}/estado', [MedicoCitaController::class, 'cambiarEstado'])->name('citas.estado');
        Route::post('/citas/{cita}/atencion', [MedicoCitaController::class, 'atender'])->name('citas.atender');

        Route::get('/horarios', [HorarioController::class, 'edit'])->name('horarios.edit');
        Route::put('/horarios', [HorarioController::class, 'update'])->name('horarios.update');
    });
});

// ==========================================
// PANEL DEL PACIENTE (Protegido: solo rol Paciente)
// ==========================================
Route::middleware(['auth', 'rol:' . Rol::PACIENTE])->prefix('paciente')->name('paciente.')->group(function () {
    Route::get('/', [PacientePanelController::class, 'index'])->name('panel');
    Route::get('/historia', [PacientePanelController::class, 'historia'])->name('historia');

    Route::get('/citas', [PacienteCitaController::class, 'index'])->name('citas.index');
    Route::get('/citas/nueva', [PacienteCitaController::class, 'create'])->name('citas.create');
    Route::post('/citas', [PacienteCitaController::class, 'store'])->name('citas.store');
    Route::get('/citas/{cita}/reprogramar', [PacienteCitaController::class, 'edit'])->name('citas.edit');
    Route::put('/citas/{cita}', [PacienteCitaController::class, 'update'])->name('citas.update');
    Route::patch('/citas/{cita}/cancelar', [PacienteCitaController::class, 'cancelar'])->name('citas.cancelar');

    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/contrasena', [PerfilController::class, 'contrasena'])->name('perfil.contrasena');
});

// ==========================================
// MÓDULO DE ADMINISTRACIÓN (Protegido: solo rol Administrador)
// ==========================================
Route::middleware(['auth', 'rol:' . Rol::ADMINISTRADOR])->prefix('admin')->name('admin.')->group(function () {
    // Panel de KPIs
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');

    // CRUD de especialidades (+ activar/desactivar)
    Route::resource('especialidades', EspecialidadController::class)
        ->except('show')
        ->parameters(['especialidades' => 'especialidad']);
    Route::patch('/especialidades/{especialidad}/estado', [EspecialidadController::class, 'cambiarEstado'])
        ->name('especialidades.estado');

    // CRUD de médicos (+ activar/desactivar)
    Route::resource('medicos', MedicoController::class)->except('show');
    Route::patch('/medicos/{medico}/estado', [MedicoController::class, 'cambiarEstado'])
        ->name('medicos.estado');
});
