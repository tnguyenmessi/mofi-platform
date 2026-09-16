# Laravel and Project Technology Guide

## PHP and Laravel

PHP is the programming language. Laravel is the web framework that provides routing, controllers, validation, authentication, queues, scheduling, caching, mail, and database access through Eloquent.

The main request flow is:

```text
Browser -> Route -> Middleware -> Controller -> Service/Model -> Response
```

## Important Laravel concepts

- **Routes:** map URLs and HTTP methods to application actions.
- **Controllers:** coordinate a request without holding complex financial rules.
- **Models:** represent database records and relationships.
- **Migrations:** version the database schema.
- **Form Requests:** validate incoming data.
- **Policies:** authorize access to a user's records.
- **Jobs and queues:** run market imports and notifications outside the request.
- **Scheduler:** trigger recurring imports and portfolio snapshots.
- **Eloquent:** query PostgreSQL using models and relationships.

## Frontend choice

React and TypeScript will handle interactive dashboard screens. Inertia.js connects React pages to Laravel without requiring a separate frontend application during the first release. Tailwind CSS provides responsive styling. Charts should receive clean, typed data from Laravel rather than querying the database directly.

## Supabase and PostgreSQL

Supabase hosts the PostgreSQL database and provides an operational dashboard, backups, pooling, and optional storage. Laravel remains the application boundary and connects using the PostgreSQL connection string. Supabase Auth is optional; the first version should use Laravel authentication to avoid two competing identity systems.

## Learning order

1. PHP syntax, classes, namespaces, Composer, and exceptions.
2. Laravel routes, controllers, requests, Blade/Inertia pages, and configuration.
3. Migrations, Eloquent relationships, factories, seeders, and policies.
4. Validation, queues, scheduling, caching, tests, and deployment.
5. React hooks, TypeScript types, Inertia forms, and chart components.
