<?php

namespace App\Controllers;

use App\Models\User;
use Spark\Http\HttpException;
use Spark\Http\Request;
use Spark\Http\Response;

class UserController
{
    public function index(): Response
    {
        return json(array_map(fn($u) => $u->toArray(), User::all()));
    }

    public function show(string $id): Response
    {
        $user = User::find((int) $id) ?? throw new HttpException(404, 'User not found');
        return json($user->toArray());
    }

    public function store(Request $request): Response
    {
        $data = $request->only(['name', 'email']);
        if (empty($data['name']) || empty($data['email'])) {
            throw new HttpException(422, 'name and email are required');
        }
        $user = User::create($data);
        return json($user->toArray(), 201);
    }

    public function update(Request $request, string $id): Response
    {
        $user = User::find((int) $id) ?? throw new HttpException(404, 'User not found');
        $user->update($request->only(['name', 'email']));
        return json($user->toArray());
    }

    public function destroy(string $id): Response
    {
        $user = User::find((int) $id) ?? throw new HttpException(404, 'User not found');
        $user->delete();
        return json(['deleted' => true]);
    }
}
