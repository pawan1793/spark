<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Spark')</title>
  <style nonce="{{ csp_nonce() }}">
    :root { color-scheme: light dark; }
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Inter, sans-serif;
      margin: 0; padding: 0;
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      color: #e2e8f0; min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .wrap { max-width: 720px; padding: 3rem 2rem; }
    h1 { font-size: 3.5rem; margin: 0 0 .5rem; background: linear-gradient(90deg, #fbbf24, #f472b6); -webkit-background-clip: text; background-clip: text; color: transparent; }
    footer { margin-top: 3rem; opacity: .5; font-size: .85rem; }
    code { background: rgba(255,255,255,.1); padding: .15rem .4rem; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="wrap">
    @yield('content')
    <footer>@yield('footer', 'Built with Spark.')</footer>
  </div>
</body>
</html>
