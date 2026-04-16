<?php

namespace App\Models;

use Spark\Database\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email'];
}
