# AGENTS.md - FichaTime (Salamandra Group)

## Project Overview

Time tracking system for **Salamandra Group SMD**. Employees can clock in/out, manage absence requests, and view their work history.

**Application name**: FichaTime

## Core Setup & Development

### Prerequisites
- PHP 8.3+
- Composer
- Node.js 20+
- MySQL 8.x

### Commands
- **Full setup**: `cd fichaje-smd && composer run setup`
- **Development mode**: `cd fichaje-smd && composer run dev` (Starts Laravel server, Reverb WebSocket, queue listener, pail logs, and Vite concurrently)
- **Run tests**: `cd fichaje-smd && composer run test`
- **Build for production**: `cd fichaje-smd && npm run build`

### Manual Setup (if `composer run setup` fails)
```bash
cd fichaje-smd
composer install
npm install
cp .env.example .env
php artisan key:generate
mysql -u root fichaje_smd < database/fichaje_smd.sql
php artisan migrate --force
php artisan db:seed --force
```

### Development Server
```bash
# Recomendado: todo en un solo comando
composer run dev
```

O manualmente en terminales separadas:
```bash
php artisan serve          # http://127.0.0.1:8000
php artisan reverb:start   # WebSocket en puerto 8080 (tiempo real)
npm run dev                # Vite / frontend
```

Access: http://localhost:8000

**Test user** (seeder):
- Email: `admin@fichaje.test`
- Password: `password`

## Critical Database Info

### Main Table
- **Table name**: `employees` (NOT the default `users` table)
- **Primary Keys**: ULIDs (char 26), not auto-increment
- **Soft Deletes**: Employees use `deleted_at` for logical deletion
- **Auth**: Employees use `password_hash` as password (via `getAuthPassword()`)

### Schema Setup
Requires manual SQL import for existing structure:
```bash
mysql -u root fichaje_smd < database/fichaje_smd.sql
```

### Key Tables
| Table | Description |
|-------|-------------|
| `employees` | Users/employees (main table) |
| `companies` | Companies |
| `time_records` | Clock-in/out records (entries) |
| `work_sessions` | Work sessions (clock in/out pairs) |
| `absence_requests` | Absence requests |
| `correction_requests` | Correction requests |
| `clock_zones` | Clock zones (IP restrictions) |
| `work_calendars` | Work calendars |
| `holidays` | Holidays |

### Initial Seeding
```bash
php artisan migrate --force && php artisan db:seed --force
```

## Architecture & Workflow

### Tech Stack
- **Backend**: Laravel 13.x + PHP 8.3
- **Frontend**: Inertia.js + Vue 3 + Tailwind CSS
- **Build Tool**: Vite 8.x
- **Real-time**: Laravel Reverb (WebSockets) + Laravel Echo
- **State Management**: Pinia (Vue 3)
- **Authentication**: Laravel Breeze + Sanctum
- **PDF Generation**: Laravel DomPDF
- **Excel Export**: Maatwebsite Excel

### Language Convention
- **Business logic/DB schema**: English (fields in English)
- **Frontend/UI**: Spanish (interface text in Spanish)

### Important Routes
| Route | Method | Description |
|-------|--------|-------------|
| `/fichaje` | GET | Clock-in/out screen |
| `/fichaje` | POST | Register entry or exit |
| `/profile` | GET | Employee profile (read-only/password change) |
| `/calendario` | GET | Company calendar |
| `/solicitudes` | GET/POST | Absence requests |
| `/dashboard` | GET | Main dashboard |
| `/api/notifications` | GET | Notifications API |
| `/api/calendar-events` | GET | Calendar events |

### Project Structure
```
fichaje-smd/
├── app/
│   ├── Http/Controllers/     # Controllers
│   ├── Models/               # Eloquent Models
│   └── Services/             # Business Services
├── resources/js/
│   ├── Pages/                # Vue page components
│   ├── Components/           # Reusable components
│   └── Layouts/              # Layouts (Authenticated, Guest)
├── database/
│   ├── fichaje_smd.sql       # Main SQL schema
│   └── migrations/           # Laravel migrations
└── routes/
    └── web.php               # Main routes
```

### Key Models
- **Employee** (`employees`): Main user model with roles (admin/manager/employee)
- **TimeRecord** (`time_records`): Clock records (type: 1=entry, 0=exit)
- **WorkSession** (`work_sessions`): Work sessions with entry/exit pairs
- **AbsenceRequest** (`absence_requests`): Absence/vacation requests
- **CorrectionRequest** (`correction_requests`): Hour correction requests

### Services
- **AttendanceService**: Clock logic and hour calculations
- **NotificationService**: Notification management
- **CorrectionRequestFileService**: Correction request file attachments

## Testing & Verification
- Always run `composer run test` after changes to ensure no regressions in business logic or clocking mechanics.
- When modifying migrations, always verify the database state against the expected `employees` table structure.
- Test coverage: Auth, Profile, Registration, Password management

## Environment
- **PHP**: 8.3+
- **Node.js**: 20+
- **MySQL**: 8.x (Docker)
- **OS**: Windows + WSL2 (Ubuntu recommended)
- **Database**: Docker MySQL container
- **Web server**: Laravel built-in / Artisan

## Common Tasks

### Adding a new Vue page
1. Create component in `resources/js/Pages/YourPage.vue`
2. Add route in `routes/web.php`
3. Return Inertia render from controller

### Adding a new model
1. Create model in `app/Models/YourModel.php`
2. Use ULIDs: `$incrementing = false`, `$keyType = 'string'`
3. Create migration or add to `fichaje_smd.sql`

### Modifying the database
- **Primary schema**: Edit `database/fichaje_smd.sql`
- **New tables**: Add CREATE TABLE to `fichaje_smd.sql`
- **Column changes**: Create a new migration file
- Always run `php artisan migrate --force` after changes