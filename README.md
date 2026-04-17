# Spark

[![Tests](https://github.com/pawan1793/spark/actions/workflows/tests.yml/badge.svg)](https://github.com/pawan1793/spark/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/spark-php/framework.svg)](https://packagist.org/packages/spark-php/framework)
[![PHP Version](https://img.shields.io/packagist/php-v/spark-php/framework.svg)](https://packagist.org/packages/spark-php/framework)
[![License](https://img.shields.io/github/license/pawan1793/spark.svg)](LICENSE)

A lightweight, Laravel-inspired PHP framework. No Symfony dependencies, no heavy abstractions — just routing, ORM, templating, middleware, DI, migrations, and a CLI in clean PHP 8.1+ code.

---

## Quick Start

```bash
composer create-project spark-php/skeleton my-app
cd my-app
php spark serve
```

Open [http://localhost:8000](http://localhost:8000).

No extra configuration needed — the app key is generated automatically and SQLite is configured out of the box.

---

## Requirements

- PHP 8.1+
- Extensions: `pdo`, `mbstring`, `json`
- No other dependencies

---

## Routing

`routes/web.php`:

```php
$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);
$router->get('/posts/{slug?}', [PostController::class, 'show']); // optional param

$router->post('/users', [UserController::class, 'store'])
    ->middleware(App\Middleware\Auth::class);

$router->group(['prefix' => 'admin', 'middleware' => [Auth::class]], function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});

$router->get('/ping', fn() => ['pong' => true]);

// API routes (routes/api.php) — no CSRF, prefixed /api
$router->get('/users', [UserController::class, 'index'])->name('api.users');

// Webhook — opt out of CSRF individually
$router->post('/webhook/github', [WebhookController::class, 'handle'])->withoutCsrf();
```

Route params are injected into controller methods by name.

---

## Controllers

```php
namespace App\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;

class UserController
{
    public function show(Request $request, string $id): Response
    {
        $user = User::find($id);
        return json($user);
    }

    public function store(Request $request): Response
    {
        $user = User::create($request->only(['name', 'email']));
        return json($user, 201);
    }
}
```

Return a `Response`, an array/object (auto-JSON), or a string (HTML).

---

## Models (ORM)

```php
namespace App\Models;

use Spark\Database\Model;

class User extends Model
{
    protected static array $fillable = ['name', 'email'];
    protected static bool  $timestamps = true;
}

// CRUD
$user   = User::create(['name' => 'Ada', 'email' => 'ada@x.com']);
$all    = User::all();
$one    = User::find(1);
$adults = User::where('age', '>', 18)->orderBy('name')->limit(10)->get();
$user->update(['name' => 'Ada Lovelace']);
$user->delete();
```

Relationships: `hasOne`, `hasMany`, `belongsTo`.

---

## Migrations

```php
use Spark\Database\{Migration, Schema, Blueprint};

return new class extends Migration {
    public function up(): void {
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('body');
            $t->foreignId('user_id');
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('posts');
    }
};
```

Supports SQLite, MySQL, and PostgreSQL.

```bash
php spark migrate
php spark migrate:rollback
```

---

## Blade-lite Views (`.spark.php`)

```blade
@extends('layout')
@section('title', 'Home')
@section('content')
  <h1>{{ $title }}</h1>
  @foreach($items as $item)
    <li>{{ $item }}</li>
  @endforeach
  @if($user)
    Welcome, {{ $user->name }}
  @endif
@endsection
```

Templates compile once to plain PHP in `storage/cache/views/`. Subsequent requests use the cached version.

Directives: `@extends @section @endsection @yield @include @if @elseif @else @endif @foreach @endforeach @for @while @isset @empty @unless @php @endphp`  
Echo: `{{ $var }}` (HTML-escaped), `{!! $html !!}` (raw)

---

## Middleware

```php
namespace App\Middleware;

use Closure;
use Spark\Http\Request;
use Spark\Http\Response;

class Auth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->header('authorization')) {
            abort(401, 'Token required');
        }
        return $next($request);
    }
}
```

Built-in: `StartSession`, `VerifyCsrfToken`, `Cors`, `ForceHttps`.

---

## Service Providers

```php
namespace App\Providers;

use Spark\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn() => new StripeGateway(
            config('services.stripe.key')
        ));
    }

    public function boot(): void
    {
        // Runs after all providers are registered
    }
}
```

Register in `bootstrap/app.php`:

```php
$app->register(\App\Providers\AppServiceProvider::class);
```

---

## Dependency Injection

Constructor-inject any class; the container resolves dependencies automatically:

```php
class ReportController
{
    public function __construct(private UserRepository $repo) {}

    public function index(): Response
    {
        return json($this->repo->all());
    }
}
```

---

## CLI

```bash
php spark serve                   # dev server (localhost:8000)
php spark make:controller Foo     # scaffold controller
php spark make:model Foo
php spark make:middleware Foo
php spark make:migration create_foo_table
php spark migrate
php spark migrate:rollback
php spark route:list
php spark key:generate
```

---

## Security defaults

Every response gets these headers automatically:

| Header | Value |
|--------|-------|
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Content-Security-Policy` | strict + nonce for inline scripts/styles |
| `Strict-Transport-Security` | set on HTTPS |
| `Permissions-Policy` | `geolocation=(), microphone=(), camera=()` |

CSRF tokens, secure session cookies, SQL prepared statements, mass-assignment protection, and open-redirect prevention are all on by default.

---

## Helpers

`app()`, `config()`, `env()`, `base_path()`, `storage_path()`, `view()`, `json()`, `response()`, `redirect()`, `abort()`, `csrf_token()`, `csrf_field()`, `bcrypt()`, `csp_nonce()`, `e()`, `dd()`

---

## License

[MIT](LICENSE) — Copyright (c) 2026 Pawan More
