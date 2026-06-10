# FichaTime (Salamadra Group)

Aplicación de registro horario para Salamadra Group SMD, construida con **Laravel + Inertia.js + Vue 3**.

## Requisitos

- PHP 8.3+
- Composer
- Node.js 20+
- MySQL 8.x (recomendado: [Laragon](https://laragon.org/))

## Instalación

```bash
cd fichaje-smd

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Configura la base de datos en `.env`:

```env
APP_NAME=FichaTime
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fichaje_smd
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
FICHAJE_RESTRICCION_IP=false
```

Crea la base de datos `fichaje_smd` en MySQL e importa el esquema:

```bash
mysql -u root fichaje_smd < database/fichaje_smd.sql
mysql -u root fichaje_smd -e "ALTER TABLE employees ADD COLUMN remember_token VARCHAR(100) NULL AFTER password_hash;"
```

Luego ejecuta las migraciones de Laravel y el seeder de prueba:

```bash
php artisan migrate --force
php artisan db:seed --force
```

## Arrancar en desarrollo

En dos terminales:

```bash
php artisan serve
npm run dev
```

Abre http://localhost:8000

**Usuario de prueba** (seeder):

- Email: `admin@fichaje.test`
- Contraseña: `password`

## Estructura principal

| Ruta | Descripción |
|------|-------------|
| `/login` | Inicio de sesión |
| `/fichaje` | Pantalla de fichaje (entrada/salida) |
| `/profile` | Perfil del empleado (solo lectura + cambio de contraseña) |

## Notas

- La tabla de usuarios es `employees` (no `users`).
- El esquema de negocio está en inglés; la interfaz en español.
- No subas `.env` al repositorio (contiene credenciales locales).
