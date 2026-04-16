<?php

namespace App\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;

class HomeController
{
    public function index(): Response
    {
        return view('home', [
            'title' => 'Welcome to Spark',
            'tagline' => 'A lightweight, Laravel-inspired PHP framework.',
            'features' => [
                'Laravel-like routing',
                'Eloquent-inspired ORM',
                'Blade-lite templating',
                'Middleware pipeline',
                'Dependency injection',
                'Artisan-style CLI',
            ],
        ]);
    }

    public function hello(Request $request, string $name): Response
    {
        return json(['hello' => $name]);
    }
}
