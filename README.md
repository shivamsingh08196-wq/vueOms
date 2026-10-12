# vueOMS (Vendor Management System)

A modern, streamlined Order Management System built with **Laravel 12** and **Vue 3**. This system uses a straightforward, conventional Laravel architecture.

## Features

- **Vue 3 + Composition API Frontend**: A responsive, interactive Single Page Application (SPA) built with Vue 3 and Tailwind CSS.
- **Robust REST API**: Built on Laravel 12 using single-action controllers for clear, maintainable business logic.
- **Domain Terminology**: Distinct vocabulary utilizing `Client`, `Item`, and `Purchase` instead of Customer, Product, and Order.
- **Kanban Board**: Drag-and-drop Purchase status tracking.
- **Role-Based Access Control (RBAC)**: Secure access tailored for Admin, Manager, and Staff roles via Spatie Laravel-Permission.
- **Authentication**: Stateless API authentication powered by Laravel Sanctum.
- **Automated Testing**: Test suites powered by Pest (Backend) and Playwright (E2E Frontend).

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js & npm
- Mysql (or another preferred database)

## Installation Process

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd vueOMS
   ```

2. **Install Dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment Setup**
   Copy the example environment file:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run Migrations and Seeders**
   This command sets up your database tables, default roles/permissions, and realistic sample data:
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Build the Frontend Assets**
   ```bash
   npm run build
   ```

6. **Start the Application**
   You can run the application using Laravel's built-in server:
   ```bash
   php artisan serve
   ```
   The application will be available at `http://localhost:8000`.

## Testing

The project includes comprehensive test suites.

**Backend Tests (Pest):**
```bash
php vendor/bin/pest
```

**End-to-End Tests (Playwright):**
First ensure your application is running, then execute:
```bash
npx playwright test
```

## API Documentation
Refer to `docs/04-api-spec.md` for a comprehensive overview of available endpoints, authentication, and request/response structures.

## Architecture & Technical Decisions
- **Backend:** Uses single-action controllers for simplicity and separation of concerns instead of complex repository patterns.
- **State Management:** Pinia is utilized for complex state, while Vue Query handles caching and server-state synchronization.
- **Validation:** Server-side uses Laravel FormRequests (in controllers); Client-side uses VeeValidate + Zod.
