# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-04-16

### Added
- PSR-4 autoloading under `Spark\` namespace
- Service container with reflection-based dependency injection
- Router with parameter binding, route groups, named routes, and middleware support
- HTTP Request/Response abstraction with security headers by default
- Secure session management (HttpOnly, SameSite=Lax cookies)
- CSRF protection middleware with per-route exemption support
- CORS middleware
- ForceHttps middleware
- Fluent QueryBuilder with identifier validation and prepared statement bindings
- Active Record ORM (`Model`) with fillable/guarded mass-assignment protection
- Schema builder and migration system (SQLite, MySQL, PostgreSQL)
- Blade-lite template engine (`.spark.php`) with layout, sections, and directives
- File-based Logger with log-injection prevention
- Global error/exception handler with debug/production modes
- Artisan-like CLI (`bin/spark`) with code generation commands
- Content Security Policy with nonce support
- Helper functions: `app()`, `config()`, `env()`, `view()`, `redirect()`, `abort()`, etc.
