# Laravel 12 Project

A Laravel 12 application with API endpoints, Twilio integration, and email services.

## Prerequisites

- PHP >= 8.2
- Composer
- Node.js and npm
- MySQL/PostgreSQL/SQLite (depending on your preference)
- Git

## How to Clone

```bash
git clone <repository-url>
cd laravel-12-temp
```

## Setup Laravel Project

### 1. Install PHP Dependencies

```bash
composer install
```

### 2. Environment Configuration

Create a `.env` file from the example (if available) or create one manually:

```bash
cp .env.example .env
```

If `.env.example` doesn't exist, create a `.env` file with the following basic configuration:

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Twilio Configuration (Optional)
TWILIO_SID=
TWILIO_AUTH_TOKEN=
TWILIO_WHATSAPP_NUMBER=
TWILIO_WHATSAPP_NUMBER_TO=

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Install Node Dependencies

```bash
npm install
```

## Database Migration

### 1. Create Database

Create a database in your MySQL/PostgreSQL server with the name specified in your `.env` file.

### 2. Run Migrations

```bash
php artisan migrate
```

### 3. (Optional) Run Seeders

If you have database seeders:

```bash
php artisan db:seed
```

## Start the Project

### Development Server

Start the Laravel development server:

```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

### Frontend Assets (Development)

In a separate terminal, start Vite for frontend asset compilation:

```bash
npm run dev
```

### Production Build

For production, build the frontend assets:

```bash
npm run build
```

## Additional Commands

### Clear Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Run Tests

```bash
php artisan test
```

### Queue Worker (if using queues)

```bash
php artisan queue:work
```

## Project Structure

- `app/Http/Controllers/` - API Controllers
- `app/Models/` - Eloquent Models
- `app/Services/` - Service Classes (Twilio, Email, etc.)
- `routes/api.php` - API Routes
- `database/migrations/` - Database Migrations

## API Documentation

API endpoints are defined in `routes/api.php`. For detailed API documentation, refer to the Postman collection file (`postman_collection.json`) if available.

## Notes

- Twilio integration is optional. The application will work without Twilio credentials, but messaging features will be disabled.
- Make sure to configure your database credentials in the `.env` file before running migrations.
- For production deployment, set `APP_ENV=production` and `APP_DEBUG=false` in your `.env` file.

