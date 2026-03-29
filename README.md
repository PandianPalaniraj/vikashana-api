# Vikashana Laravel API — Setup Guide

## Prerequisites
- PHP 8.2+, Composer, MySQL 8+, XAMPP running

---

## 1. Create Laravel Project

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/
composer create-project laravel/laravel vidyasms-backend
cd vidyasms-backend
```

## 2. Install Sanctum

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

## 3. Configure .env

```env
APP_NAME=Vikashana
APP_URL=http://localhost/vidyasms-backend/public

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vikashana
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000
SESSION_DRIVER=cookie
```

## 4. Create Database

```sql
CREATE DATABASE vikashana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 5. Copy Generated Files

Copy each file from the outputs folder into your Laravel project:

```
database/migrations/  → all migration files
database/seeders/     → DatabaseSeeder.php
app/Models/           → all model files
app/Http/Controllers/Api/V1/ → all controller files
routes/api.php        → replace existing
```

## 6. Configure bootstrap/app.php (Laravel 11)

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
    $middleware->alias([
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
    ]);
})
```

## 7. Add CORS Headers (for React dev server)

In `config/cors.php`:
```php
'allowed_origins' => ['http://localhost:5173', 'http://localhost:3000'],
'supports_credentials' => true,
```

## 8. Run Migrations + Seed

```bash
php artisan migrate
php artisan db:seed
```

## 9. Test Login

```bash
curl -X POST http://localhost/vidyasms-backend/public/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@vidyaniketan.edu.in","password":"password"}'
```

Expected response:
```json
{
  "success": true,
  "data": {
    "token": "1|xxxxxxxx",
    "user": { "id": 1, "name": "Admin User", "role": "admin" }
  }
}
```

## 10. All API Endpoints

### Auth
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/v1/auth/login | Login → returns token |
| GET  | /api/v1/auth/me | Get logged-in user |
| POST | /api/v1/auth/logout | Revoke token |
| PUT  | /api/v1/auth/password | Change password |

### Students
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/students?search=&class_id=&status= |
| POST   | /api/v1/students |
| GET    | /api/v1/students/{id} |
| PUT    | /api/v1/students/{id} |
| DELETE | /api/v1/students/{id} |
| GET    | /api/v1/students/{id}/attendance |
| GET    | /api/v1/students/{id}/fees |
| GET    | /api/v1/students/{id}/marks |

### Teachers
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/teachers?search=&department= |
| POST   | /api/v1/teachers |
| GET    | /api/v1/teachers/{id} |
| PUT    | /api/v1/teachers/{id} |
| DELETE | /api/v1/teachers/{id} |

### Attendance
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/attendance?class_id=&date= |
| POST   | /api/v1/attendance (bulk mark) |
| GET    | /api/v1/attendance/report?class_id=&month=&year= |
| GET    | /api/v1/attendance/summary |
| PUT    | /api/v1/attendance/{id} |

### Fees
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/fees/invoices?student_id=&status= |
| POST   | /api/v1/fees/invoices |
| GET    | /api/v1/fees/invoices/{id} |
| PUT    | /api/v1/fees/invoices/{id} |
| DELETE | /api/v1/fees/invoices/{id} |
| POST   | /api/v1/fees/invoices/{id}/pay |
| GET    | /api/v1/fees/summary |

### Exams & Marks
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/exams?class_id= |
| POST   | /api/v1/exams |
| GET    | /api/v1/exams/{id}/marks |
| POST   | /api/v1/exams/{id}/marks (bulk save) |
| GET    | /api/v1/exams/{id}/report |
| GET    | /api/v1/exams/{id}/timetable |

### Admissions
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/admissions/enquiries?stage= |
| POST   | /api/v1/admissions/enquiries |
| GET    | /api/v1/admissions/enquiries/{id} |
| PUT    | /api/v1/admissions/enquiries/{id} |
| PUT    | /api/v1/admissions/enquiries/{id}/stage |
| POST   | /api/v1/admissions/enquiries/{id}/convert |
| GET    | /api/v1/admissions/stats |

### Communications
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/announcements |
| POST   | /api/v1/announcements |
| PUT    | /api/v1/announcements/{id} |
| DELETE | /api/v1/announcements/{id} |
| PUT    | /api/v1/announcements/{id}/pin |
| POST   | /api/v1/broadcasts |
| GET    | /api/v1/broadcasts |

### Dashboard
| Method | Endpoint |
|--------|----------|
| GET    | /api/v1/dashboard/stats |

---

## Response Format (consistent for web + mobile)

### Success
```json
{
  "success": true,
  "data": { ... },
  "message": "Optional message",
  "meta": { "page": 1, "total": 150, "per_page": 20 }
}
```

### Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": { "phone": ["The phone field is required."] }
}
```

---

## Authentication (React + Mobile)

**React web app:**
```js
// Store token in localStorage after login
localStorage.setItem('token', response.data.token)

// Add to every request
headers: { 'Authorization': `Bearer ${token}` }
```

**Mobile app (React Native / Flutter):**
```js
// Store token in SecureStore / Keychain
// Same Authorization header
// Call /api/v1/auth/me on app launch to restore session
```

---

## Next Steps (Phase 2)
1. Replace React seed data with fetch() calls to this API
2. Add file upload endpoint for student photos & documents
3. Add push notification support (FCM) for mobile
4. Add PDF receipt generation (Laravel DomPDF)