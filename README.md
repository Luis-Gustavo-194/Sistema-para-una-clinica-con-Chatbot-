# Sistema de citas para una clínica con chatbot

Aplicación web para gestionar las citas médicas de una clínica, con tres tipos de usuario (paciente, médico y administrador) y un **asistente virtual** que entiende mensajes en español y permite agendar, cancelar o reprogramar citas conversando.

El proyecto tiene dos partes que se ejecutan a la vez:

| Carpeta | Qué es | Tecnología | Puerto |
|---|---|---|---|
| [`sistema_citas/`](sistema_citas) | Aplicación web (pantallas, lógica de citas, base de datos) | Laravel 13 (PHP 8.3), Tailwind CSS, Alpine.js, MySQL | `8000` |
| [`Clinica/`](Clinica) | Motor de lenguaje natural (NLP) del chatbot | Python, FastAPI, spaCy, scikit-learn | `8001` |

```
Navegador ──► Laravel (localhost:8000) ──► MySQL (sistema_citas_medicas)
                    │
                    └──► API del chatbot (127.0.0.1:8001/predecir)
                         devuelve la intención del mensaje
```

Laravel recibe cada mensaje del chat y se lo envía a la API de Python, que responde con la **intención** detectada (por ejemplo `agendar_cita`). Con esa intención, Laravel guía la conversación (pide el DNI, la especialidad, el médico, el horario…) y guarda la cita en la base de datos.

---

## Requisitos

- [PHP 8.3 o superior](https://www.php.net/downloads) y [Composer](https://getcomposer.org/)
- [Node.js 20 o superior](https://nodejs.org/) (incluye npm)
- [MySQL 8 o superior](https://dev.mysql.com/downloads/) (por ejemplo con MySQL Workbench). Si usas XAMPP/MariaDB, lee la nota del paso 2.
- [Python 3.12](https://www.python.org/downloads/)
- [Git](https://git-scm.com/)

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/Luis-Gustavo-194/Sistema-para-una-clinica-con-Chatbot-.git
cd Sistema-para-una-clinica-con-Chatbot-
```

### 2. Crear la base de datos

El archivo [`sistema_citas/Dump20261026.sql`](sistema_citas/Dump20261026.sql) crea la base de datos `sistema_citas_medicas` con todas sus tablas y datos de prueba (especialidades, médicos, horarios, pacientes y citas).

**Opción A — MySQL Workbench:** *Server → Data Import → Import from Self-Contained File*, elige `Dump20261026.sql` y pulsa *Start Import*.

**Opción B — consola:**

```bash
mysql -u root -p < sistema_citas/Dump20261026.sql
```

> **XAMPP / MariaDB:** MariaDB no reconoce la collation `utf8mb4_0900_ai_ci` de MySQL 8. Antes de importar, abre el `.sql` en un editor y reemplaza todas las apariciones de `utf8mb4_0900_ai_ci` por `utf8mb4_unicode_ci`. Luego impórtalo desde phpMyAdmin (*Importar*).

> No ejecutes `php artisan migrate`: el dump ya trae la estructura que usa el sistema.

### 3. Configurar y levantar Laravel

```bash
cd sistema_citas
composer install
npm install
cp .env.example .env          # en Windows (cmd): copy .env.example .env
php artisan key:generate
```

Abre `.env` y revisa los datos de conexión a MySQL (normalmente solo hace falta poner tu contraseña):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistema_citas_medicas
DB_USERNAME=root
DB_PASSWORD=tu_contraseña
```

Compila los estilos y scripts, y arranca el servidor:

```bash
npm run build
php artisan serve
```

La web queda en **http://localhost:8000**. (Si vas a modificar estilos o JavaScript, usa `npm run dev` en otra terminal en lugar de `npm run build`.)

### 4. Instalar y levantar el chatbot

En **otra terminal**, desde la raíz del repositorio:

```bash
cd Clinica
python -m venv venv
venv\Scripts\activate          # Windows
# source venv/bin/activate     # macOS / Linux
pip install -r requirements.txt
python -m spacy download es_core_news_sm
uvicorn api_chatbot:app --port 8001
```

Ejecuta `uvicorn` **desde la carpeta `Clinica/`**, porque la API carga `modelo_chatbot.pkl` y `vectorizador.pkl` desde el directorio actual. Cuando veas `¡Modelos cargados correctamente!`, la API está lista. Puedes probarla sola en **http://127.0.0.1:8001/docs** (endpoint `POST /predecir` con `{"texto": "quiero sacar una cita"}`).

Con las dos terminales abiertas (Laravel en 8000 y el chatbot en 8001) el sistema está completo.

---

## Cuentas de prueba

Todas las cuentas del dump usan la contraseña **`Prueba123*`**. Algunas para empezar:

| Rol | Correo |
|---|---|
| Administrador | `admin@clinica.com` |
| Médico | `carlos.perez@clinica.com` |
| Médico | `ana.torres@clinica.com` |
| Paciente | `mario.quispe@pacientes.clinica.com` (DNI `70000001`) |
| Paciente | `ana.gonzales@pacientes.clinica.com` (DNI `45871236`) |

También puedes crear un paciente nuevo desde **Registrarse** en la página de inicio.

## Cómo funciona

Entra a http://localhost:8000 e inicia sesión. Cada rol ve su propio panel:

- **Paciente:** ve sus próximas citas, agenda una cita nueva eligiendo especialidad, médico y horario disponible, reprograma o cancela citas, consulta su historia clínica y edita su perfil y contraseña.
- **Médico:** ve su agenda del día y sus citas, cambia el estado de una cita, registra la atención (diagnóstico, indicaciones y receta, que pasan a la historia clínica del paciente) y define sus horarios de atención por día de la semana.
- **Administrador:** ve un tablero con indicadores (citas, médicos, especialidades) y administra especialidades y médicos (crear, editar, activar o desactivar). Lo que se desactiva deja de ofrecerse en el chatbot.

### El chatbot

El asistente aparece como un botón flotante en la página de inicio y en los paneles de paciente y médico. Prueba frases como:

| Escribes… | Intención detectada | Qué hace el bot |
|---|---|---|
| "hola", "buenas tardes" | `saludo` | Se presenta y dice qué puede hacer |
| "quiero sacar una cita médica", "necesito cita" | `agendar_cita` | Pide tu DNI (si no iniciaste sesión), la especialidad, el médico y el horario, y registra la cita |
| "quiero cancelar mi turno" | `cancelar_cita` | Muestra tus citas vigentes y cancela la que elijas |
| "necesito cambiar la fecha de mi cita" | `reprogramar_cita` | Muestra tus citas vigentes y te ofrece nuevos horarios |
| "qué especialidades tienen" | `consultar_especialidades` | Lista las especialidades activas |
| "muéstrame mi agenda" (como médico) | `consultar_agenda_medico` | Muestra las próximas citas del médico con sesión iniciada |

Si iniciaste sesión como paciente, el bot no te pide el DNI. En cualquier momento puedes escribir **cancelar** o **salir** para detener el proceso en curso.

### Reentrenar el modelo

Las frases de ejemplo de cada intención están en [`Clinica/dataset.json`](Clinica/dataset.json). Para añadir frases o intenciones nuevas:

1. Edita `dataset.json`.
2. Desde `Clinica/` (con el entorno virtual activo) ejecuta `python entrenamiento.py`, que regenera `modelo_chatbot.pkl` y `vectorizador.pkl`.
3. Reinicia `uvicorn`.
4. Puedes probar el modelo en consola con `python prueba_bot.py`.

Si agregas una intención nueva, también hay que manejarla en `sistema_citas/app/Http/Controllers/ChatbotController.php`.

---

## Problemas comunes

| Problema | Solución |
|---|---|
| El chat responde *"El motor NLP de Python está apagado."* | La API no está corriendo en el puerto 8001. Levántala con `uvicorn api_chatbot:app --port 8001` desde `Clinica/`. |
| `Unknown collation: 'utf8mb4_0900_ai_ci'` al importar | Estás usando MariaDB (XAMPP). Reemplaza la collation por `utf8mb4_unicode_ci` como se indica en el paso 2. |
| `Vite manifest not found` | Falta compilar el front: ejecuta `npm run build` en `sistema_citas/`. |
| `SQLSTATE[HY000] [1045] Access denied` | Revisa `DB_USERNAME` y `DB_PASSWORD` en `sistema_citas/.env`. |
| `No application encryption key has been specified` | Ejecuta `php artisan key:generate`. |
| `Can't find model 'es_core_news_sm'` | Ejecuta `python -m spacy download es_core_news_sm` con el entorno virtual activo. |
| Error o advertencia al cargar los `.pkl` | Instala la versión exacta de scikit-learn de `requirements.txt` o reentrena el modelo con `python entrenamiento.py`. |
