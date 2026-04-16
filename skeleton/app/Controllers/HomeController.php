<?php

namespace App\Controllers;

use Spark\Http\Response;

class HomeController
{
    public function index(): Response
    {
        return view('home', [
            'title' => 'Welcome to Spark',
        ]);
    }
}
