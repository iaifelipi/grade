<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title','Grade')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>

<body class="grade-body grade-auth-body">

<div class="grade-auth-shell">
    <aside class="grade-auth-aside">
        <div class="grade-auth-brand">
            <div class="grade-auth-logo">PX</div>
            <div>
                <div class="grade-auth-name">Grade</div>
                <div class="grade-auth-tagline">Inteligência operacional para times de dados.</div>
            </div>
        </div>

        <div class="grade-auth-aside-card">
            <h3>O que você ganha aqui</h3>
            <ul>
                <li>Importação de registros em segundos</li>
                <li>Exploração e segmentação rápida</li>
                <li>Controle completo por conta</li>
            </ul>
        </div>

        <div class="grade-auth-metrics">
            <div>
                <span>+2M</span>
                <small>Registros processados</small>
            </div>
            <div>
                <span>99.9%</span>
                <small>Tempo de disponibilidade</small>
            </div>
        </div>
    </aside>

    <main class="grade-auth-main">
        <div class="grade-auth-card">
            <div class="grade-auth-card-body">
                @yield('content')
            </div>
        </div>
        <div class="grade-auth-footer">© {{ date('Y') }} Grade • Dados protegidos</div>
    </main>
</div>

<script>
    document.addEventListener('click', (event)=>{
        const trigger = event.target.closest('[data-auth-switch]')
        if(!trigger) return
        const target = trigger.getAttribute('data-auth-switch')
        const routes = {
            login: "{{ route('login') }}",
            forgot: "{{ route('password.request') }}"
        }
        if(routes[target]){
            window.location.href = routes[target]
        }
    })
</script>

</body>
</html>
