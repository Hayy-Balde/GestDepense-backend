<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'GestDepense') }} API</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;
            background:#0a0a0a;color:#e5e5e5;min-height:100vh;
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            padding:2rem;position:relative;overflow-x:hidden
        }
        body::before{
            content:'';position:fixed;inset:0;
            background:radial-gradient(ellipse 80% 80% at 50% -20%,rgba(120,119,198,0.15),rgba(255,255,255,0));
            pointer-events:none;z-index:0
        }
        .container{position:relative;z-index:1;width:100%;max-width:800px;text-align:center}
        .badge{
            display:inline-flex;align-items:center;gap:.5rem;
            padding:.4rem 1rem;border-radius:999px;
            background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);
            font-size:.8rem;color:#a1a1aa;margin-bottom:2rem
        }
        .badge .dot{
            width:8px;height:8px;border-radius:50%;display:inline-block
        }
        .badge .dot.online{background:#22c55e;box-shadow:0 0 8px rgba(34,197,94,.5)}
        h1{
            font-size:clamp(2.5rem,6vw,4rem);font-weight:800;
            background:linear-gradient(135deg,#f5f5f5 0%,#a1a1aa 100%);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent;
            line-height:1.1;margin-bottom:.75rem
        }
        .subtitle{font-size:1.15rem;color:#a1a1aa;margin-bottom:3rem;line-height:1.6}
        .subtitle strong{color:#e5e5e5}
        .cards{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
            gap:1rem;margin-bottom:3rem;text-align:left
        }
        .card{
            background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);
            border-radius:12px;padding:1.25rem;transition:.2s
        }
        .card:hover{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.15)}
        .card .icon{font-size:1.5rem;margin-bottom:.5rem;display:block}
        .card .label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:#71717a;margin-bottom:.25rem}
        .card .value{font-size:1.1rem;font-weight:600;color:#e5e5e5}
        .card .value.green{color:#22c55e}
        .card .value.yellow{color:#eab308}
        .card .value a{color:#818cf8;text-decoration:none}
        .card .value a:hover{color:#a5b4fc;text-decoration:underline}
        .links{display:flex;flex-wrap:wrap;gap:1rem;justify-content:center;margin-bottom:3rem}
        .links a{
            display:inline-flex;align-items:center;gap:.5rem;
            padding:.75rem 1.5rem;border-radius:8px;font-size:.9rem;font-weight:500;
            text-decoration:none;transition:.2s
        }
        .links a.primary{
            background:#6366f1;color:#fff
        }
        .links a.primary:hover{background:#4f46e5}
        .links a.secondary{
            background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#e5e5e5
        }
        .links a.secondary:hover{background:rgba(255,255,255,.1)}
        .endpoints{
            background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.08);
            border-radius:12px;padding:1.5rem;margin-bottom:3rem;text-align:left
        }
        .endpoints h3{font-size:.9rem;text-transform:uppercase;letter-spacing:.05em;color:#71717a;margin-bottom:1rem}
        .endpoint{
            display:flex;align-items:center;gap:1rem;padding:.6rem 0;
            border-bottom:1px solid rgba(255,255,255,.04);font-size:.85rem
        }
        .endpoint:last-child{border-bottom:none}
        .method{
            font-size:.7rem;font-weight:700;padding:.15rem .5rem;border-radius:4px;
            text-transform:uppercase;min-width:48px;text-align:center
        }
        .method.get{background:rgba(34,197,94,.15);color:#22c55e}
        .method.post{background:rgba(99,102,241,.15);color:#818cf8}
        .method.put{background:rgba(234,179,8,.15);color:#eab308}
        .method.delete{background:rgba(239,68,68,.15);color:#ef4444}
        .path{font-family:'SF Mono','Fira Code','Menlo',monospace;color:#a1a1aa;flex:1}
        .desc{color:#71717a;font-size:.8rem}
        footer{font-size:.8rem;color:#52525b}
        footer a{color:#71717a;text-decoration:none}
        footer a:hover{color:#a1a1aa;text-decoration:underline}
        @media(max-width:600px){
            .cards{grid-template-columns:1fr}
            .endpoint{flex-wrap:wrap;gap:.4rem}
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="badge">
            <span class="dot online"></span>
            API opérationnelle
            <span style="color:#52525b">·</span>
            v{{ app()->version() }}
        </div>

        <h1>{{ config('app.name', 'GestDepense') }}</h1>
        <p class="subtitle">
            API de gestion des dépenses personnelles<br>
            <strong>{{ config('app.url') }}</strong>
        </p>

        <div class="cards">
            <div class="card">
                <span class="icon">📊</span>
                <div class="label">Statut</div>
                <div class="value green">● En ligne</div>
            </div>
            <div class="card">
                <span class="icon">🐘</span>
                <div class="label">Base de données</div>
                @php
                    $dbOk = false;
                    try { DB::connection()->getPdo(); $dbOk = true; } catch (\Throwable) {}
                @endphp
                <div class="value {{ $dbOk ? 'green' : 'yellow' }}">{{ $dbOk ? '● Connectée' : '● Déconnectée' }}</div>
            </div>
            <div class="card">
                <span class="icon">🔗</span>
                <div class="label">Frontend</div>
                <div class="value"><a href="{{ env('FRONTEND_URL', '#') }}" target="_blank" rel="noopener">gestdepense.vercel.app ↗</a></div>
            </div>
        </div>

        <div class="links">
            <a href="https://gestdepense.vercel.app" target="_blank" rel="noopener" class="primary">
                Accéder à l'application
            </a>
            <a href="{{ route('testbd') }}" class="secondary">
                Tester la base de données
            </a>
        </div>

        <div class="endpoints">
            <h3>Points d'entrée principaux</h3>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/up</span>
                <span class="desc">Health check</span>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/v1/auth/register</span>
                <span class="desc">Inscription</span>
            </div>
            <div class="endpoint">
                <span class="method post">POST</span>
                <span class="path">/api/v1/auth/login</span>
                <span class="desc">Connexion</span>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/v1/expenses</span>
                <span class="desc">Dépenses (authentifié)</span>
            </div>
            <div class="endpoint">
                <span class="method get">GET</span>
                <span class="path">/api/v1/dashboard</span>
                <span class="desc">Tableau de bord (authentifié)</span>
            </div>
        </div>

        <footer>
            GestDepense &copy; {{ date('Y') }} &middot;
            <a href="https://github.com/Hayy-Balde/GestDepense-backend" target="_blank" rel="noopener">GitHub</a>
        </footer>
    </div>
</body>
</html>