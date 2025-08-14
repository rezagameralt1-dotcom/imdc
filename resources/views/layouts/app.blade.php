<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DigitalCity</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body>
<header class="header">
  <div class="container" style="display:flex;align-items:center;gap:14px;">
    <div class="brand">DigitalCity</div>
    @include('partials.nav')
  </div>
</header>

<main class="container">
  @yield('content')
  <footer class="footer">© <span>{{ date('Y') }}</span> DigitalCity – UI Kit v3</footer>
</main>
</body>
</html>
---

