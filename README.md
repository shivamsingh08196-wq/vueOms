# vueOMS — Vendor Management System

A modern, streamlined Order Management System built with **Laravel 12**, **Vue 3**, and **Tailwind CSS**. The system follows a conventional Laravel architecture with a REST API and a responsive Single Page Application (SPA).

## Features

- **Vue 3 + Composition API:** Responsive SPA built with Vue 3 and Tailwind CSS.
- **REST API:** Laravel 12 API with single-action controllers.
- **Domain Terminology:** Uses Client, Item, and Purchase instead of Customer, Product, and Order.
- **Kanban Board:** Drag-and-drop Purchase status tracking.
- **Role-Based Access Control (RBAC):** Admin, Manager, and Staff roles using Spatie Laravel-Permission.
- **Authentication:** Stateless API authentication with Laravel Sanctum.
- **State Management:** Pinia and Vue Query.
- **Validation:** Laravel Form Requests, VeeValidate, and Zod.
- **Automated Testing:** Pest for backend tests and Playwright for end-to-end tests.

## Prerequisites

Ensure the following tools are installed:

- PHP 8.2 or higher
- Composer
- Node.js and npm
- MySQL or another supported database

## Installation and Setup

### 1. Clone the Repository

```bash
git clone <repository-url>
cd vueOMS
```

### 2. Install Dependencies

Install the PHP and JavaScript dependencies:

```bash
composer install
npm install
```

### 3. Configure Environment Variables

Copy the example environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

### 4. Configure the Database

Create a database in MySQL before running the migrations.

For example, create a database named `vueoms`.

Open the `.env` file in the project root and configure your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vueoms
DB_USERNAME=root
DB_PASSWORD=your_mysql_password
```

**Important:**
- Replace `vueoms` with your actual database name if different.
- Set `DB_USERNAME` to your MySQL username.
- Set `DB_PASSWORD` to your MySQL password.
- If your MySQL root account has no password, leave `DB_PASSWORD` empty.
- Ensure the MySQL service is running before proceeding.

After updating `.env`, clear the configuration cache:

```bash
php artisan config:clear
```

### 5. Run Migrations and Seeders

Run the following command to create the database tables and populate the database with default roles, permissions, users, and sample data:

```bash
php artisan migrate:fresh --seed
```

This command **drops existing tables and recreates them**. Use it only when you are comfortable deleting the existing database data.

The seeder creates the following sample data:

| Entity | Sample Data |
|---|---|
| Users | Admin, Manager, and Staff |
| Clients | 5 sample clients |
| Items | 10 sample items |
| Purchases | Sample purchase records with predefined dates |
| Roles and permissions | Default RBAC configuration |

Item prices, stock quantities, and other sample fields are populated by the seeders.

## Default Login Credentials

Use these accounts to test the application after running the seeders.

### Admin

- **Email:** `admin@vueoms.com`
- **Password:** `password`
- **Role:** Admin

### Manager

- **Email:** `manager@vueoms.com`
- **Password:** `password`
- **Role:** Manager

### Staff

- **Email:** `staff@vueoms.com`
- **Password:** `password`
- **Role:** Staff

**Security note:** These are development and testing credentials. Change the default passwords before deploying the application to production.

## Build and Run the Application

### 1. Build Frontend Assets

```bash
npm run build
```

### 2. Start the Laravel Development Server

```bash
php artisan serve
```

Open the application in your browser:

## Testing

### Backend Tests — Pest

Run the backend test suite:

```bash
php vendor/bin/pest
```

### End-to-End Tests — Playwright

Ensure the application and any required frontend server are running, then execute:

```bash
npx playwright test
```

## API Documentation

Refer to [`docs/04-api-spec.md`](docs/04-api-spec.md) for details about:

- API endpoints
- Authentication using Laravel Sanctum
- Request and response formats
- Pagination
- API validation and error responses

Protected API endpoints require a bearer token:

```http
Authorization: Bearer <token>
```

The login endpoint is exempt from authentication.

## Architecture and Technical Decisions

### Backend
- Laravel 12 with conventional Laravel application structure.
- Single-action controllers for focused request handling.
- Form Requests for server-side validation.
- Laravel Sanctum for API authentication.
- Spatie Laravel-Permission for roles and permissions.

### Frontend
- Vue 3 Composition API.
- Tailwind CSS for responsive styling.
- Pinia for client-side state management.
- Vue Query for server-state caching and synchronization.
- VeeValidate and Zod for client-side validation.

## Troubleshooting

### Database Connection Errors

- Confirm that MySQL is running.
- Verify `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.
- Ensure the database exists.
- Run:

  ```bash
  php artisan config:clear
  ```

### Missing Application Key

Run:

```bash
php artisan key:generate
```

### Frontend Assets Not Loading

Install dependencies and rebuild:

```bash
npm install
npm run build
```

### Permission or Role Issues

If you have modified the roles or permissions seeder, rerun the seeders as appropriate. Remember that `migrate:fresh --seed` deletes and recreates existing tables.
