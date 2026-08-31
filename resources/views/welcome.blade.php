<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $appName }} — Sistema de gestión y punto de venta</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: var(--dark); background: var(--light); line-height: 1.6; }
        .nav { display: flex; justify-content: space-between; align-items: center; padding: 18px 6%; background: #fff; box-shadow: 0 1px 3px rgb(0 0 0 / 8%); position: sticky; top: 0; z-index: 10; }
        .brand { font-size: 1.35rem; font-weight: 800; color: var(--primary); text-decoration: none; }
        .hero { text-align: center; padding: 90px 6% 70px; background: linear-gradient(180deg, #eff6ff 0%, var(--light) 100%); }
        .hero h1 { font-size: clamp(2rem, 5vw, 3.2rem); font-weight: 900; max-width: 850px; margin: 0 auto 18px; }
        .hero p { font-size: 1.15rem; color: var(--gray); max-width: 640px; margin: 0 auto 34px; }
        .btn { display: inline-block; padding: 14px 34px; border-radius: 10px; font-weight: 700; text-decoration: none; font-size: 1.02rem; transition: transform .15s, box-shadow .15s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgb(37 99 235 / 30%); }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-outline { border: 2px solid var(--primary); color: var(--primary); margin-left: 12px; }
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 22px; padding: 30px 6% 80px; max-width: 1200px; margin: 0 auto; }
        .card { background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 2px 10px rgb(0 0 0 / 5%); }
        .card .icon { font-size: 2rem; }
        .card h3 { margin: 12px 0 8px; font-size: 1.12rem; }
        .card p { color: var(--gray); font-size: .95rem; }
        footer { text-align: center; padding: 26px; color: var(--gray); font-size: .88rem; border-top: 1px solid #e2e8f0; background: #fff; }
    </style>
</head>
<body>
    <nav class="nav">
        <a class="brand" href="/">{{ $appName }}</a>
        <div>
            @auth
                <a class="btn btn-primary" href="/admin">Ir al panel</a>
            @endauth
            @guest
                <a class="btn btn-outline" href="/admin/login" style="margin-left:0">Iniciar sesión</a>
                <a class="btn btn-primary" href="/admin/login">Acceder al sistema</a>
            @endguest
        </div>
    </nav>

    <section class="hero">
        <h1>Gestione su negocio de principio a fin</h1>
        <p>Inventario, compras, ventas, punto de venta con cálculo de cambio, facturación y contabilidad — todo en una sola plataforma.</p>
        <div>
            @auth
                <a class="btn btn-primary" href="/admin/pos">Abrir punto de venta</a>
            @endauth
            @guest
                <a class="btn btn-primary" href="/admin/login">Comenzar ahora</a>
            @endguest
        </div>
    </section>

    <section class="features">
        <div class="card"><div class="icon">📦</div><h3>Control de inventario</h3><p>Costeo por promedio ponderado, kardex detallado, transferencias entre almacenes y alertas automáticas de stock.</p></div>
        <div class="card"><div class="icon">🛒</div><h3>Compras y ventas</h3><p>Registre compras a proveedores y ventas a clientes con actualización automática de existencias y costos.</p></div>
        <div class="card"><div class="icon">🖥️</div><h3>Punto de venta</h3><p>Cobro rápido con búsqueda por nombre, SKU o código de barras y cálculo inmediato del cambio a entregar.</p></div>
        <div class="card"><div class="icon">🧾</div><h3>Facturación</h3><p>Genere facturas numeradas desde las ventas, imprímalas y mantenga el control de sus documentos.</p></div>
        <div class="card"><div class="icon">📊</div><h3>Reportes</h3><p>Valorización del inventario, resumen de operaciones y balance de comprobación siempre al día.</p></div>
        <div class="card"><div class="icon">🔐</div><h3>Seguro y auditable</h3><p>Permisos por rol, registro de auditoría de cada acción y protección integrada en cada módulo.</p></div>
    </section>

    <footer>&copy; {{ date('Y') }} {{ $appName }}. Todos los derechos reservados.</footer>
</body>
</html>
