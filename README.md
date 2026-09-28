# Polaris Soft

Sistema web para la gestión y administración de procesos académicos, desarrollado con **Laravel** como backend y **React + Vite** como frontend.

## Tecnologías

### Backend

- PHP
- Laravel
- MySQL
- Composer
- Laravel API

### Frontend

- React
- Vite
- JavaScript
- Axios
- CSS

---

# Estructura del proyecto

```text
Polaris/
│
├── iniciar-polaris.bat
│
├── Polaris_Soft/
│   ├── app/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── artisan
│   ├── composer.json
│   └── .env.example
│
├── polaris-frontend/
│   ├── src/
│   ├── public/
│   ├── package.json
│   └── vite.config.js
│
└── README.md
```

---

# Requisitos

Antes de instalar el proyecto se necesita:

- Git
- PHP
- Composer
- Node.js
- npm
- MySQL

Se recomienda utilizar una versión de Node.js compatible con la versión actual de Vite utilizada por el proyecto.

---

# Instalación

## 1. Clonar el repositorio

```bash
git clone URL_DEL_REPOSITORIO
```

Entrar al proyecto:

```bash
cd Polaris
```

---

# Configuración del Backend

Entrar a Laravel:

```bash
cd Polaris_Soft
```

Instalar las dependencias:

```bash
composer install
```

Crear el archivo `.env`:

### Windows

```bash
copy .env.example .env
```

Generar la clave de Laravel:

```bash
php artisan key:generate
```

---

# Configuración de la base de datos

Crear una base de datos MySQL llamada:

```text
polaris
```

Después configurar el archivo:

```text
Polaris_Soft/.env
```

Ejemplo:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=polaris
DB_USERNAME=root
DB_PASSWORD=
```

> Ajustar `DB_USERNAME` y `DB_PASSWORD` según la configuración de MySQL de la computadora.

Ejecutar las migraciones:

```bash
php artisan migrate
```

Si el proyecto incluye datos iniciales mediante seeders:

```bash
php artisan db:seed
```

O directamente:

```bash
php artisan migrate --seed
```

---

# Configuración del Frontend

Desde la carpeta raíz del proyecto:

```bash
cd ../polaris-frontend
```

Instalar las dependencias:

```bash
npm install
```

Axios ya está incluido como dependencia del proyecto.

---

# Comunicación entre React y Laravel

El frontend utiliza Axios para comunicarse con Laravel.

La API está configurada en:

```text
polaris-frontend/src/services/api.js
```

La URL base utilizada actualmente es:

```text
http://127.0.0.1:8000/api
```

Por ejemplo:

```text
GET http://127.0.0.1:8000/api/estudiantes
```

Las principales rutas disponibles son:

```text
GET     /api/estudiantes
POST    /api/estudiantes
GET     /api/estudiantes/{id}
PUT     /api/estudiantes/{id}
DELETE  /api/estudiantes/{id}

GET     /api/examenes
POST    /api/examenes
GET     /api/examenes/{id}
PUT     /api/examenes/{id}
DELETE  /api/examenes/{id}

GET     /api/materias
```

---

# Ejecutar el proyecto

El proyecto incluye un script:

```text
iniciar-polaris.bat
```

Este archivo utiliza rutas relativas, por lo que **no depende de la ubicación específica de la computadora**.

La estructura debe mantenerse de esta forma:

```text
Polaris/
├── iniciar-polaris.bat
├── Polaris_Soft/
└── polaris-frontend/
```

Para iniciar todo:

### Opción 1 — Doble clic

Ejecutar:

```text
iniciar-polaris.bat
```

### Opción 2 — Terminal

Desde la carpeta raíz:

```bash
.\iniciar-polaris.bat
```

El script iniciará:

```text
Laravel
http://127.0.0.1:8000

React
http://localhost:5173
```

La aplicación frontend estará disponible en:

```text
http://localhost:5173
```

---

# Ejecución manual

Si no se desea utilizar el script, ambos proyectos pueden ejecutarse manualmente.

## Laravel

```bash
cd Polaris_Soft
php artisan serve
```

Laravel estará disponible en:

```text
http://127.0.0.1:8000
```

## React

En otra terminal:

```bash
cd polaris-frontend
npm run dev
```

React estará disponible en:

```text
http://localhost:5173
```

---

# Módulos actuales

## HU-001 — Registro y gestión de estudiantes

Permite al administrador:

- Registrar estudiantes.
- Consultar estudiantes.
- Buscar estudiantes.
- Editar información.
- Eliminar estudiantes.
- Validar código universitario.
- Validar documento de identidad.
- Validar correo institucional.
- Gestionar el estado del estudiante.

Campos principales:

```text
Código universitario
Documento de identidad
Nombres
Apellidos
Carrera
Correo institucional
Estado
```

---

## HU-003 — Registro de exámenes

Permite al administrador:

- Registrar exámenes.
- Consultar exámenes.
- Editar exámenes.
- Eliminar exámenes.
- Seleccionar la materia.
- Definir fecha y hora.
- Definir duración.
- Definir ambiente.
- Registrar normas generales.
- Registrar normas particulares.

---

# Arquitectura

El proyecto utiliza una arquitectura separada:

```text
                 ┌─────────────────────┐
                 │       Usuario       │
                 └──────────┬──────────┘
                            │
                            ▼
                 ┌─────────────────────┐
                 │   React + Vite      │
                 │  localhost:5173     │
                 └──────────┬──────────┘
                            │
                         Axios
                            │
                            ▼
                 ┌─────────────────────┐
                 │ Laravel REST API    │
                 │ 127.0.0.1:8000     │
                 └──────────┬──────────┘
                            │
                            ▼
                 ┌─────────────────────┐
                 │       MySQL         │
                 │      polaris        │
                 └─────────────────────┘
```

---

# Git

Para obtener los últimos cambios:

```bash
git pull
```

Después de actualizar el backend:

```bash
cd Polaris_Soft
composer install
php artisan migrate
```

Después de actualizar el frontend:

```bash
cd ../polaris-frontend
npm install
```

Finalmente:

```bash
cd ..
.\iniciar-polaris.bat
```

---

# Archivos que NO deben subirse al repositorio

Por seguridad y tamaño, los siguientes archivos/directorios no deben formar parte del repositorio:

```text
Polaris_Soft/.env
Polaris_Soft/vendor/
polaris-frontend/node_modules/
polaris-frontend/dist/
```

El archivo `.env.example` sí debe mantenerse en Git para indicar qué variables necesita el proyecto.

---

# Solución de problemas

## Laravel no inicia

Verificar PHP:

```bash
php -v
```

Verificar Composer:

```bash
composer -V
```

Instalar dependencias:

```bash
cd Polaris_Soft
composer install
```

---

## React no inicia

Verificar Node.js:

```bash
node -v
```

Verificar npm:

```bash
npm -v
```

Instalar dependencias:

```bash
cd polaris-frontend
npm install
```

Después:

```bash
npm run dev
```

---

## Error de conexión con MySQL

Verificar que MySQL esté ejecutándose y comprobar las credenciales del archivo:

```text
Polaris_Soft/.env
```

También comprobar que exista la base de datos:

```text
polaris
```

---

## React no recibe datos de Laravel

Comprobar que Laravel esté ejecutándose:

```text
http://127.0.0.1:8000
```

Y probar una ruta de la API:

```text
http://127.0.0.1:8000/api/estudiantes
```

También comprobar la configuración de Axios:

```text
polaris-frontend/src/services/api.js
```

Debe apuntar a:

```text
http://127.0.0.1:8000/api
```

---

# Flujo de desarrollo

El flujo recomendado es:

```text
1. git pull
       ↓
2. composer install
       ↓
3. npm install
       ↓
4. php artisan migrate
       ↓
5. iniciar-polaris.bat
       ↓
6. Abrir localhost:5173
```

---

# Estado del proyecto

**Estado:** Desarrollo / Prototipo

El