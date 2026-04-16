@extends('layout')

@section('title', $title)

@section('content')
  <h1>{{ $title }}</h1>
  <p class="tag">{{ $tagline }}</p>

  <ul>
    @foreach($features as $feature)
      <li>{{ $feature }}</li>
    @endforeach
  </ul>

  <p class="mt-2">
    Try <code>GET /ping</code> or <code>GET /hello/world</code> or <code>GET /api/users</code>.
  </p>
@endsection
