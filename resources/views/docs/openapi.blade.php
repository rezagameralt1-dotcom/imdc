<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>IMDC API Docs</title>
  <style>
    :root { --bg:#0b1020; --fg:#e8f0ff; --muted:#9fb3c8; }
    html,body{margin:0;height:100%;background:var(--bg);color:var(--fg);font-family:Tahoma,Arial,sans-serif}
    .tabs{display:flex;gap:8px;padding:12px;border-bottom:1px solid #1b2540;position:sticky;top:0;background:var(--bg);z-index:9}
    .tab{padding:8px 14px;border-radius:10px;background:#121a33;color:var(--muted);cursor:pointer}
    .tab.active{background:#1a264a;color:#fff}
    .wrap{height:calc(100% - 52px)}
    .panel{display:none;height:100%}
    .panel.active{display:block}
    .footer{padding:10px;color:var(--muted);font-size:12px;text-align:center;border-top:1px solid #1b2540}
    .note{padding:10px 14px;color:#b7c9e6}
  </style>
  <!-- Swagger UI -->
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <!-- Redoc -->
  <script src="https://cdn.redoc.ly/redoc/v2.4.0/bundles/redoc.standalone.js"></script>
</head>
<body>
  <div class="tabs">
    <div class="tab active" data-target="#swagger">Swagger UI</div>
    <div class="tab" data-target="#redoc">Redoc</div>
    <div class="note">منبع: <code>/api/health/openapi</code> • برای بهترین نتیجه قبلش <code>php artisan optimize:clear</code> بزن</div>
  </div>
  <div class="wrap">
    <div id="swagger" class="panel active">
      <div id="swagger-ui"></div>
    </div>
    <div id="redoc" class="panel">
      <redoc spec-url="/api/health/openapi"></redoc>
    </div>
  </div>
  <div class="footer">IMDC • OpenAPI 3.1 • {{ config('app.env') }} • {{ config('app.name') }}</div>

  <script>
    const tabs = document.querySelectorAll('.tab');
    const panels = document.querySelectorAll('.panel');
    tabs.forEach(t => t.addEventListener('click', () => {
      tabs.forEach(x=>x.classList.remove('active'));
      panels.forEach(p=>p.classList.remove('active'));
      t.classList.add('active');
      document.querySelector(t.dataset.target).classList.add('active');
      // Lazy init swagger only once
      if (t.dataset.target === '#swagger' && !window._swaggerInited) {
        window._swaggerInited = true;
        window.ui = SwaggerUIBundle({
          url: '/api/health/openapi',
          dom_id: '#swagger-ui',
          layout: 'BaseLayout',
          deepLinking: true,
          presets: [SwaggerUIBundle.presets.apis, SwaggerUIBundle.SwaggerUIStandalonePreset]
        });
      }
    }));
    // Auto init swagger on load
    window.addEventListener('load', () => {
      document.querySelector('.tab.active').click();
    });
  </script>
</body>
</html>
