# Spark

A lightweight, Laravel-inspired PHP framework. No Symfony dependencies, no heavy abstractions — just the essentials, in clean readable code.

## Requirements

- PHP 8.1+
- `ext-pdo`, `ext-mbstring`, `ext-json`

## Quick Start

```bash
cp .env.example .env
php spark key:generate
php spark migrate
php spark serve
```

Open http://127.0.0.1:8000.

No `composer install` needed — Spark ships with its own PSR-4 autoloader. Composer is supported if you prefer it.

## Directory Layout

```
app/          Your controllers, models, middleware
bootstrap/    Framework bootstrap (autoload, app registration)
config/       App + database config
core/         Framework internals (Spark\*)
database/     Migrations
public/       Web entry point (index.php)
resources/    Views (.spark.php)
routes/       web.php + api.php
storage/      Logs + compiled views
spark         CLI entry
```

## Routing

`routes/web.php`:

```php
$router->get('/', [HomeController::class, 'index']);
$router->get('/users/{id}', [UserController::class, 'show']);

$router->post('/users', [UserController::class, 'store'])
    ->middleware(App\Middleware\Auth::class);

$router->group(['prefix' => 'admin', 'middleware' => [Auth::class]], function ($router) {
    $router->get('/dashboard', [DashboardController::class, 'index']);
});

$router->get('/ping', fn() => ['pong' => true]);
```

Route params are injected into controller methods by name.

## Controllers

```php
namespace App\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;

class HomeController
{
    public function index(Request $request): Response
    {
        return view('home', ['title' => 'Hello']);
    }
}
```

Return a `Response`, an array/object (auto-JSON), or a string (HTML).

## Models (ORM)

```php
namespace App\Models;

use Spark\Database\Model;

class User extends Model
{
    protected static array $fillable = ['name', 'email'];
}

// Usage
$user = User::create(['name' => 'Ada', 'email' => 'ada@x.com']);
$all  = User::all();
$one  = User::find(1);
$adults = User::where('age', '>', 18)->orderBy('name')->limit(10)->get();
$user->update(['name' => 'Ada Lovelace']);
$user->delete();
```

Relationships: `hasOne`, `hasMany`, `belongsTo`.

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

Run: `php spark migrate` / `php spark migrate:rollback`.

## Blade-lite Views

`resources/views/home.spark.php`:

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

Compiled once to plain PHP in `storage/cache/views/` — fast on subsequent hits.

Directives: `@extends @section @endsection @yield @include @if @elseif @else @endif @foreach @endforeach @for @while @isset @empty @unless @php @endphp`. Echo: `{{ }}` (escaped) and `{!! !!}` (raw).

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

Attach per-route or per-group via `->middleware(Auth::class)`.

## Dependency Injection

Constructor-inject any class; the container auto-resolves:

```php
class ReportController
{
    public function __construct(private UserRepo $repo) {}

    public function index(): Response {
        return json($this->repo->all());
    }
}
```

Register custom bindings in `bootstrap/app.php`:

```php
app()->singleton(UserRepo::class, fn($c) => new EloquentUserRepo($c->make(Connection::class)));
```

## CLI (Spark)

```
php spark serve                     # dev server
php spark make:controller Foo       # scaffold controller
php spark make:model Foo
php spark make:middleware Foo
php spark make:migration create_foo_table
php spark migrate
php spark migrate:rollback
php spark route:list
php spark key:generate
```

## Helpers

`app()`, `config('key')`, `env('KEY')`, `base_path()`, `storage_path()`, `view()`, `json()`, `response()`, `redirect()`, `abort()`, `dd()`.

## Performance Notes

- Pure PHP, no Symfony / Illuminate.
- Container resolves on demand (no eager boot).
- Views compile once to plain PHP.
- Routes match via compiled regex.

## What Spark Removes vs Laravel

No queues, broadcasting, notifications, mail, events, facades, policies, service providers, package discovery, polymorphic relations, casts/accessors/mutators, or Symfony components. Just routing, ORM, views, middleware, DI, CLI, migrations.

## License

MIT
