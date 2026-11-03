<?php

use App\Http\Controllers\Admin\AuditoriaController;
use App\Http\Controllers\Admin\ClinicaController;
use App\Http\Controllers\Admin\EspecialidadController;
use App\Http\Controllers\Admin\HorarioMedicoController;
use App\Http\Controllers\Admin\MedicoController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatbotController; // <-- Controlador del Administrador
use App\Http\Controllers\DirectorioController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\Medico\CitaController as MedicoCitaController;
use App\Http\Controllers\Medico\HorarioController;
use App\Http\Controllers\Medico\PanelController as MedicoPanelController;
use App\Http\Controllers\Paciente\CitaController as PacienteCitaController;
use App\Http\Controllers\Paciente\PanelController as PacientePanelController;
use App\Http\Controllers\Paciente\PerfilController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\Recepcion\CitaController as RecepcionCitaController;
use App\Http\Controllers\Recepcion\PacienteController as RecepcionPacienteController;
use App\Http\Controllers\RegistroController;
use App\Models\Rol;
use Illuminate\Support\Facades\Route;

Route::get('/', [InicioController::class, 'index'])->name('inicio');

// Directorio público de médicos con sus días de atención
Route::get('/directorio', [DirectorioController::class, 'index'])->name('directorio');

Route::get('/login', function () {
    return view('login');
})->name('login');

Route::post('/login', [AuthController::class, 'login']);

Route::get('/logout', [AuthController::class, 'logout']);

// Registro público de pacientes nuevos
Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:5,1');

// Política de tratamiento de datos personales (Ley N.° 29733)
Route::view('/politica-de-privacidad', 'politica-privacidad')->name('politica-privacidad');

// Recuperación de contraseña con el Password broker de Laravel (HU04)
Route::middleware('guest')->group(function () {
    Route::get('/olvide-contrasena', [PasswordController::class, 'solicitar'])->name('password.request');
    Route::post('/olvide-contrasena', [PasswordController::class, 'enviarEnlace'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [PasswordController::class, 'formulario'])->name('password.reset');
    Route::post('/restablecer-contrasena', [PasswordController::class, 'restablecer'])->middleware('throttle:5,1')->name('password.update');
});

Route::post('/chatbot/enviar', [ChatbotController::class, 'procesarMensaje']);

// ==========================================
// PANEL DEL MÉDICO (Protegido: solo rol Médico)
// ==========================================
Route::middleware(['auth', 'rol:'.Rol::MEDICO])->group(function () {
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
Route::middleware(['auth', 'rol:'.Rol::PACIENTE])->prefix('paciente')->name('paciente.')->group(function () {
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
Route::middleware(['auth', 'rol:'.Rol::ADMINISTRADOR])->prefix('admin')->name('admin.')->group(function () {
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

    // Gestión de usuarios y asignación de rol (HU03)
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::patch('/usuarios/{usuario}/rol', [UsuarioController::class, 'asignarRol'])->name('usuarios.rol');

    // Auditoría filtrable: accesos, fallos y acciones (HU17)
    Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

    // Mantenimiento: horarios de cualquier médico y datos de la clínica
    Route::get('/medicos/{medico}/horarios', [HorarioMedicoController::class, 'edit'])->name('medicos.horarios.edit');
    Route::put('/medicos/{medico}/horarios', [HorarioMedicoController::class, 'update'])->name('medicos.horarios.update');
    Route::get('/clinica', [ClinicaController::class, 'edit'])->name('clinica.edit');
    Route::put('/clinica', [ClinicaController::class, 'update'])->name('clinica.update');
});

// ==========================================
// RECEPCIÓN (Protegido: Recepcionista y Administrador)
// ==========================================
Route::middleware(['auth', 'rol:'.Rol::RECEPCIONISTA.','.Rol::ADMINISTRADOR])->prefix('recepcion')->name('recepcion.')->group(function () {
    // Búsqueda, ficha, alta y edición de pacientes (HU05, HU06)
    Route::resource('pacientes', RecepcionPacienteController::class)->except('destroy');

    // Reserva y cancelación en nombre del paciente (mismo CitaService que la web y el chat)
    Route::get('/pacientes/{paciente}/citas/create', [RecepcionCitaController::class, 'create'])->name('pacientes.citas.create');
    Route::post('/pacientes/{paciente}/citas', [RecepcionCitaController::class, 'store'])->name('pacientes.citas.store');
    Route::patch('/citas/{cita}/cancelar', [RecepcionCitaController::class, 'cancelar'])->name('citas.cancelar');
});
