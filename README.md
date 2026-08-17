# Recruitment System

A Laravel-based recruitment and agency management platform designed to streamline candidate, customer, job, and application workflows for recruitment teams. The application supports role-based access, dashboard reporting, subscriptions, invoices, notifications, and recruitment operations across agencies and recruiters.

## Overview

This project provides a backend API for managing:

- Agencies and recruiters
- Customers and subscription plans
- Jobs and vacancies
- Candidates and applications
- Status tracking and document handling
- Reports, dashboards, and notifications
- Email templates and social media integrations

The system is built with Laravel 10 and uses Vite for front-end asset compilation.

## Features

- Role-based access and agency management
- Recruitment workflow for jobs, candidates, and applications
- Application status tracking and rescheduling
- Customer management with invoice generation
- Dashboard analytics for agency and super-admin reporting
- Subscription and payment tracking
- Master-data management for countries, categories, subcategories, and pools
- Notification and email template support
- Social integration hooks for job sharing

## Tech Stack

- PHP 8.1+
- Laravel 10
- MySQL / MariaDB
- Composer
- Node.js + Vite
- Sanctum for API authentication
- Spatie permission and activity log packages

## Project Structure

```text
.
├── app/                  # Application logic and controllers
├── bootstrap/            # Laravel bootstrap files
├── config/               # Application configuration
├── database/
│   ├── factories/        # Model factories
│   ├── migrations/       # Database schema migrations
│   └── seeders/          # Seed data for development
├── public/               # Public assets and entry point
├── resources/            # Front-end assets and templates
├── routes/
│   ├── api.php           # API routes
│   ├── web.php           # Web routes
│   └── console.php       # Console commands
├── storage/              # Local storage and cache
├── tests/                # Automated tests
├── .env.example          # Example environment variables
├── artisan               # Laravel CLI
├── composer.json         # PHP dependencies and scripts
├── package.json          # Front-end dependencies and scripts
├── phpunit.xml           # PHPUnit configuration
├── vite.config.js        # Vite configuration
├── README.md             # Project documentation
└── .gitignore            # Ignored files
```

## Prerequisites

Before you begin, ensure you have the following installed:

- PHP 8.1 or higher
- Composer
- Node.js 18+ and npm
- MySQL or MariaDB
- Git

## Getting Started

### 1. Clone the repository

```bash
git clone <repository-url>
cd recruitment-system
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install front-end dependencies

```bash
npm install
```

### 4. Configure environment variables

Copy the sample environment file:

```bash
cp .env.example .env
```

Then update the values in `.env` for your local setup, especially:

- `APP_NAME`
- `APP_ENV`
- `APP_URL`
- database credentials
- mail configuration
- any storage or cloud service variables required by your environment

> Keep all secrets in your local environment only and do not commit the `.env` file.

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Run database migrations and seeders

```bash
php artisan migrate
php artisan db:seed
```

If you want a fresh setup without existing data:

```bash
php artisan migrate:fresh --seed
```

### 7. Start the application

Run the Laravel backend:

```bash
php artisan serve
```

Run the frontend dev server:

```bash
npm run dev
```

The application will typically be available at:

- Backend: `http://localhost:8000`
- Frontend assets via Vite: local dev server output from the Vite process

## Available Scripts

### PHP

```bash
php artisan serve
php artisan migrate
php artisan db:seed
php artisan test
```

### Front-end

```bash
npm run dev
npm run build
```

## Testing

Run the project test suite with:

```bash
php artisan test
```

## API Notes

This project exposes a REST-style API through `routes/api.php` and includes authenticated and public endpoints for:

- authentication and profile management
- dashboards and analytics
- agency and recruiter operations
- customer, job, candidate, and application management
- reports, templates, notifications, and integrations

## Environment and Security

- Copy `.env.example` to `.env` for local configuration.
- Never commit secrets, service credentials, or local environment files.
- Keep database, mail, and cloud credentials restricted to your development or deployment environment.

