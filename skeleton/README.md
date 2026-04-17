# Spark Framework

A lightweight PHP MVC framework with routing, templating, ORM, sessions, and a built-in dev server.

## Quick Start

```bash
composer create-project spark-php/skeleton my-app
cd my-app
php spark serve
```

Visit `http://127.0.0.1:8000`.

---

## Table of Contents

- [Directory Structure](#directory-structure)
- [Environment & Configuration](#environment--configuration)
- [Routing](#routing)
- [Controllers](#controllers)
- [Requests](#requests)
- [Responses](#responses)
- [Views & Templating](#views--templating)
- [Database & ORM](#database--orm)
- [Query Builder](#query-builder)
- [Migrations](#migrations)
- [Sessions](#sessions)
- [Middleware](#middleware)
- [Service Container](#service-container)
- [Helper Functions](#helper-functions)
- [CLI Commands](#cli-commands)
- [Error Handling & Logging](#error-handling--logging)
- [Security](#security)
- [Deployment (Apache)](#deployment-apache)

---

## Directory Structure

```
my-app/
├── app/
│   ├── Controllers/
│   ├── Models/
│   └── Middleware/
├── bootstrap/
│   ├── app.php          # Service bindings
│   └── autoload.php
├── config/
│   ├── app.php
│   └── database.php
├── database/
│   └── migrations/
├── public/
│   ├── index.php        # Front controller
│   └── .htaccess        # Apache rewrite rules
├── resources/
│   └── views/
├── routes/
│   ├── web.php
│   └── api.php
├── storage/
│   ├── logs/
│   └── database.sqlite
├── .env
└── spark               # CLI entry point
```

---

## Environment & Configuration

Copy `.env.example` to `.env` (done automatically by `composer create-project`):

```bash
cp .env.example .env
php spark key:generate
```

### .env Keys

```ini
APP_NAME=Spark
APP_ENV=production          # local | production
APP_DEBUG=false
APP_URL=http://localhost:8000
APP_KEY=                    # auto-filled by key:generate

LOG_LEVEL=debug

DB_CONNECTION=sqlite        # sqlite | mysql | pgsql
DB_DATABASE=storage/database.sqlite
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
```

### Reading Config

Config files live in `config/`. Access values with dot notation:

```php
config('app.name');              // "Spark"
config('app.debug');             // false
config('database.default');      // "sqlite"
config('app.name', 'Default');   // fallback if missing
```

Add your own config file at `config/mail.php` and access it as `config('mail.host')`.

### Reading ENV Directly

```php
env('APP_ENV');              // "production"
env('MISSING_KEY', 'default');
```

---

## Routing

Routes are defined in `routes/web.php` (HTML pages) and `routes/api.php` (JSON API).

### Basic Routes

```php
// routes/web.php — $router is available in scope

use App\Controllers\PostController;

$router->get('/', [HomeController::class, 'index']);
$router->post('/contact', [ContactController::class, 'store']);
$router->put('/posts/{id}', [PostController::class, 'update']);
$router->patch('/posts/{id}', [PostController::class, 'patch']);
$router->delete('/posts/{id}', [PostController::class, 'destroy']);
$router->any('/webhook', [WebhookController::class, 'handle']); // all methods
```

### Closure Routes

```php
use Spark\Http\Request;

$router->get('/ping', function (Request $request) {
    return json(['pong' => true]);
});
```

### Route Parameters

```php
// Required parameter
$router->get('/users/{id}', [UserController::class, 'show']);

// Optional parameter — matches /posts and /posts/42
$router->get('/posts/{id?}', [PostController::class, 'index']);
```

Access parameters in the controller via `$request->attribute('id')`.

### Named Routes

```php
$router->get('/users/{id}', [UserController::class, 'show'])->name('users.show');
```

### Route Groups

```php
// Prefix group
$router->group(['prefix' => '/admin'], function ($router) {
    $router->get('/dashboard', [AdminController::class, 'dashboard']);
    $router->get('/users', [AdminController::class, 'users']);
});

// Middleware group
$router->group(['middleware' => [\App\Middleware\Auth::class]], function ($router) {
    $router->get('/profile', [ProfileController::class, 'show']);
});

// Combined
$router->group(['prefix' => '/api/v1', 'middleware' => [\App\Middleware\ApiAuth::class]], function ($router) {
    $router->get('/me', [ApiController::class, 'me']);
});
```

### Per-Route Middleware

```php
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(\App\Middleware\Auth::class);

// Multiple middleware
$router->post('/admin/users', [AdminController::class, 'store'])
    ->middleware([\App\Middleware\Auth::class, \App\Middleware\AdminOnly::class]);
```

### Skip CSRF on a Route

```php
$router->post('/webhook/stripe', [WebhookController::class, 'stripe'])->withoutCsrf();
```

### API Routes

`routes/api.php` routes are automatically prefixed with `/api` and use `api_middleware` from config.

```php
// routes/api.php — accessible at /api/status
$router->get('/status', function (Request $request) {
    return ['status' => 'ok', 'timestamp' => time()];
});
```

---

## Controllers

Create a controller:

```bash
php spark make:controller PostController
```

```php
namespace App\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
use App\Models\Post;

class PostController
{
    public function index(Request $request): Response
    {
        $posts = Post::all();
        return view('posts.index', ['posts' => $posts]);
    }

    public function show(Request $request): Response
    {
        $id = $request->attribute('id');
        $post = Post::find($id);
        return view('posts.show', ['post' => $post]);
    }

    public function store(Request $request): Response
    {
        $post = Post::create($request->only(['title', 'body']));
        return redirect('/posts/' . $post->id);
    }
}
```

### Dependency Injection

Constructor dependencies are auto-resolved from the service container:

```php
class PostController
{
    public function __construct(private MailService $mail) {}

    public function store(Request $request): Response
    {
        // $this->mail is auto-injected
    }
}
```

---

## Requests

The `Request` object is injected into controller methods and closures.

### Reading Input

```php
$request->input('name');               // POST or GET value
$request->input('name', 'default');    // with fallback
$request->all();                       // all POST + GET + JSON data
$request->only(['title', 'body']);     // subset of fields
$request->has('email');                // check key existence

// Query string only
$request->query['page'];

// JSON body (for API requests)
$request->json();                      // full decoded body
$request->json('user.name');           // nested key
```

### Route Parameters

```php
// Route: /users/{id}
$id = $request->attribute('id');
```

### Request Info

```php
$request->method();    // "GET", "POST", etc.
$request->path();      // "/users/42"
$request->url();       // "https://example.com/users/42"
$request->ip();        // "203.0.113.1"
$request->header('Authorization');
$request->body();      // raw request body
$request->isJson();    // true if Content-Type: application/json
$request->wantsJson(); // true if Accept: application/json
```

---

## Responses

### Views

```php
return view('posts.index', ['posts' => $posts]);
```

### JSON

```php
return json(['status' => 'ok']);
return json(['error' => 'Not found'], 404);
```

### Redirects

```php
return redirect('/dashboard');
return redirect('/login', 302);
```

### Raw Response

```php
return response()
    ->status(201)
    ->header('X-Custom', 'value')
    ->html('<h1>Hello</h1>');
```

### Abort with Error

```php
abort(404);
abort(403, 'Forbidden');
```

---

## Views & Templating

View files live in `resources/views/` with the `.spark.php` extension. Use dot notation for subdirectories: `view('posts.index')` maps to `resources/views/posts/index.spark.php`.

### Outputting Variables

```php
{{ $variable }}      {{-- HTML-escaped output --}}
{!! $html !!}        {{-- Raw/unescaped output --}}
{{-- comment --}}    {{-- Removed from output --}}
```

### Layouts

**Layout file** (`resources/views/layout.spark.php`):
```html
<!doctype html>
<html>
<head>
  <title>@yield('title', 'My App')</title>
</head>
<body>
  @yield('content')
  <footer>@yield('footer', 'My App')</footer>
</body>
</html>
```

**Child view** (`resources/views/home.spark.php`):
```php
@extends('layout')

@section('title', 'Home Page')

@section('content')
  <h1>{{ $title }}</h1>
  <p>Welcome!</p>
@endsection
```

### Including Partials

```php
@include('partials.nav')
@include('partials.alert', ['type' => 'success', 'message' => 'Saved!'])
```

### Control Structures

```php
@if($user)
  Hello, {{ $user->name }}
@elseif($guest)
  Hello, guest
@else
  Please log in
@endif

@unless($loggedIn)
  <a href="/login">Login</a>
@endunless

@isset($title)
  <title>{{ $title }}</title>
@endisset

@empty($posts)
  <p>No posts yet.</p>
@endempty
```

### Loops

```php
@foreach($posts as $post)
  <h2>{{ $post->title }}</h2>
@endforeach

@for($i = 0; $i < 5; $i++)
  <p>Item {{ $i }}</p>
@endfor

@while($condition)
  ...
@endwhile
```

### Raw PHP

```php
@php
  $formatted = number_format($price, 2);
@endphp
```

### Forms (CSRF & Method Spoofing)

```html
<form method="POST" action="/posts">
  @csrf
  <input name="title">
  <button>Submit</button>
</form>

<form method="POST" action="/posts/1">
  @csrf
  @method('PUT')
  <input name="title" value="{{ $post->title }}">
  <button>Update</button>
</form>
```

---

## Database & ORM

### Connecting

Set your driver and credentials in `.env`. Then run migrations:

```bash
php spark migrate
```

### Defining a Model

```bash
php spark make:model Post
```

```php
namespace App\Models;

use Spark\Database\Model;

class Post extends Model
{
    protected static string $table = 'posts';           // defaults to plural of class name
    protected static string $primaryKey = 'id';
    protected static array $fillable = ['title', 'body', 'user_id'];
    protected static bool $timestamps = true;           // auto-manages created_at/updated_at
}
```

### Creating Records

```php
$post = Post::create(['title' => 'Hello', 'body' => 'World']);

// Or instantiate and save
$post = new Post();
$post->title = 'Hello';
$post->body  = 'World';
$post->save();
```

### Reading Records

```php
Post::all();                    // all rows
Post::find(1);                  // by primary key, or null
Post::first();                  // first row
Post::count();                  // row count

Post::where('published', '=', true)->get();
Post::where('user_id', '=', $id)->orderBy('created_at', 'DESC')->limit(10)->get();
```

### Updating Records

```php
$post = Post::find(1);
$post->update(['title' => 'Updated']);

// Or
$post->title = 'Updated';
$post->save();
```

### Deleting Records

```php
$post = Post::find(1);
$post->delete();
```

### Relationships

```php
class Post extends Model
{
    public function author(): ?Model
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): array
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}

class User extends Model
{
    public function profile(): ?Model
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}

// Usage
$post = Post::find(1);
$author   = $post->author();
$comments = $post->comments();
```

### Converting to Array

```php
$post->toArray();
```

---

## Query Builder

Use the query builder directly for more complex queries:

```php
use Spark\Database\Model;

$results = Post::query()
    ->select(['id', 'title', 'created_at'])
    ->where('published', '=', true)
    ->where('user_id', '=', $userId)
    ->orWhere('featured', '=', true)
    ->whereIn('category_id', [1, 2, 3])
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->offset(20)
    ->get();

// Check existence
$exists = Post::query()->where('slug', '=', 'hello-world')->exists();

// Count
$total = Post::query()->where('user_id', '=', $id)->count();

// Raw insert/update/delete (returns affected rows or insert ID)
Post::query()->insert(['title' => 'New', 'body' => 'Text']);
Post::query()->where('id', '=', 5)->update(['title' => 'Changed']);
Post::query()->where('id', '=', 5)->delete();

// Inspect generated SQL
$sql = Post::query()->where('id', '=', 1)->toSql();
```

**Supported operators:** `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `IS`, `IS NOT`

---

## Migrations

```bash
php spark make:migration create_posts_table
```

Edit the generated file in `database/migrations/`:

```php
use Spark\Database\Migration;
use Spark\Database\Schema;
use Spark\Database\Blueprint;

class CreatePostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->foreignId('user_id');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('posts');
    }
}
```

```bash
php spark migrate           # run all pending migrations
php spark migrate:rollback  # rollback last batch
```

**Column types:** `id()`, `string()`, `text()`, `integer()`, `bigInteger()`, `boolean()`, `timestamp()`, `timestamps()`, `foreignId()`, `unique()`, `index()`

---

## Sessions

```php
use Spark\Http\Session;

Session::put('user_id', 42);
Session::get('user_id');
Session::get('user_id', null);     // with default
Session::forget('user_id');
Session::regenerate();             // regenerate session ID (call after login)
Session::destroy();                // destroy session (call on logout)
```

---

## Middleware

### Creating Middleware

```bash
php spark make:middleware Auth
```

```php
namespace App\Middleware;

use Closure;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Http\Session;

class Auth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Session::get('user_id')) {
            return redirect('/login');
        }

        return $next($request);
    }
}
```

### Registering Middleware

**Global web middleware** — runs on every web route (`config/app.php`):

```php
'web_middleware' => [
    \Spark\Middleware\StartSession::class,
    \Spark\Middleware\VerifyCsrfToken::class,
    \App\Middleware\ForceHttps::class,
],
```

**Global API middleware** — runs on every `/api/*` route:

```php
'api_middleware' => [
    \App\Middleware\ApiAuth::class,
],
```

**Per-route or group middleware** — see [Routing](#routing).

### Built-in Middleware

| Class | Purpose |
|---|---|
| `Spark\Middleware\StartSession` | Starts session, generates CSRF token |
| `Spark\Middleware\VerifyCsrfToken` | Validates CSRF token on POST/PUT/PATCH/DELETE |
| `Spark\Middleware\ForceHttps` | Redirects HTTP → HTTPS (non-local envs) |
| `Spark\Middleware\Cors` | Handles CORS preflight and headers |

---

## Service Container

Register bindings in `bootstrap/app.php`. The `$app` variable is in scope.

```php
// Bind a new instance each time
$app->bind(MyService::class, fn() => new MyService());

// Singleton — same instance for the entire request
$app->singleton(MailService::class, function ($app) {
    return new MailService(
        config('mail.host'),
        config('mail.port')
    );
});

// Register a pre-existing instance
$app->instance(Config::class, $myConfig);
```

### Resolving

```php
$service = app(MailService::class);

// Or via the container
$service = app()->make(MailService::class);
```

Auto-wiring is supported — type-hinted constructor parameters are resolved automatically:

```php
class PostController
{
    public function __construct(
        private MailService $mail,
        private PostRepository $posts
    ) {}
}
```

---

## Helper Functions

| Function | Description |
|---|---|
| `view($name, $data)` | Render a view and return a Response |
| `response()` | Create a blank Response instance |
| `json($data, $status)` | Return a JSON Response |
| `redirect($url, $status)` | Return a redirect Response |
| `request()` | Get the current Request instance |
| `config($key, $default)` | Read config with dot notation |
| `env($key, $default)` | Read an environment variable |
| `app($abstract)` | Resolve from the service container |
| `csrf_token()` | Get the current CSRF token string |
| `csrf_field()` | Get `<input type="hidden" name="_token" ...>` |
| `csp_nonce()` | Get per-request CSP nonce (use in `<script nonce="">`) |
| `bcrypt($password)` | Hash a password |
| `e($value)` | HTML-escape a string |
| `abort($status, $msg)` | Throw an HTTP exception |
| `base_path($path)` | Absolute path from project root |
| `storage_path($path)` | Absolute path to `storage/` |
| `logger($msg, $ctx)` | Log a message or get the Logger instance |
| `dd(...$vars)` | Dump variables and exit (debug mode only) |

---

## CLI Commands

```bash
php spark <command>
```

| Command | Description |
|---|---|
| `serve` | Start dev server at `http://127.0.0.1:8000` |
| `key:generate` | Generate `APP_KEY` and write to `.env` |
| `make:controller Name` | Scaffold a controller in `app/Controllers/` |
| `make:model Name` | Scaffold a model in `app/Models/` |
| `make:middleware Name` | Scaffold middleware in `app/Middleware/` |
| `make:migration name` | Create a migration file in `database/migrations/` |
| `migrate` | Run all pending migrations |
| `migrate:rollback` | Rollback the last batch of migrations |
| `route:list` | List all registered routes |

### Custom Commands

```php
namespace App\Console;

use Spark\Console\Command;

class SendEmails extends Command
{
    protected string $name = 'emails:send';

    public function handle(array $args, array $options): int
    {
        $this->info('Sending emails...');
        // ...
        $this->line('Done.');
        return 0;
    }
}
```

Register in the kernel or `bootstrap/app.php`:

```php
app()->bind('command.emails:send', \App\Console\SendEmails::class);
```

---

## Error Handling & Logging

Errors are handled automatically. In debug mode (`APP_DEBUG=true`) a full stack trace is shown. In production, a generic error page or JSON response is returned.

```php
// Log messages
logger('User logged in', ['user_id' => 42]);
logger()->info('Something happened');
logger()->warning('Low memory', ['free' => $free]);
logger()->error('Payment failed', ['order' => $id]);
```

Logs are written to `storage/logs/spark.log`.

---

## Security

The framework includes the following security features out of the box:

- **CSRF protection** — all POST/PUT/PATCH/DELETE routes require `@csrf` token in forms.
- **Content Security Policy** — auto-generated CSP header with per-request nonces for inline `<script>` and `<style>` tags. Customize in `config/csp.php`.
- **Secure session cookies** — `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS.
- **HSTS** — `Strict-Transport-Security` header sent on HTTPS responses.
- **Safe redirects** — `redirect()` is same-origin by default. Pass `true` as third argument to allow external URLs.
- **SQL injection prevention** — all queries use PDO prepared statements.
- **Default security headers** — `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `X-XSS-Protection`, `Permissions-Policy`.

---

## Deployment (Apache)

Ensure `public/.htaccess` exists with the rewrite rules (included in this skeleton):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

Your Apache VirtualHost must point to the `public/` directory and have `AllowOverride All`:

```apache
<VirtualHost *:443>
    ServerName example.com
    DocumentRoot /var/www/myapp/public

    SSLEngine On
    SSLCertificateFile    /etc/ssl/myapp/origin.crt
    SSLCertificateKeyFile /etc/ssl/myapp/origin.key

    <Directory /var/www/myapp/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Enable the rewrite module and restart Apache:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

Set correct file permissions:

```bash
sudo chown -R www-data:www-data /var/www/myapp
sudo chmod -R 755 /var/www/myapp
sudo chmod -R 775 /var/www/myapp/storage
```
