<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trainerabrechnung & Trainingsplanung</title>
    <link rel="stylesheet" href="/assets/mobile.css">
</head>
<body class="bg-surface">
<header class="app-bar">
    <div class="brand">Vereinsplanung</div>
    <div class="user-menu">@auth {{ auth()->user()->name }} @endauth</div>
</header>

<main class="container">
    <section class="card">
        <div class="card-head">
            <div>
                <p class="eyebrow">Halbjahr</p>
                <h2>@yield('title', 'H1 / H2 Übersicht')</h2>
            </div>
            <div class="tags">
                <span class="tag tag-success">frei</span>
                <span class="tag tag-warning">Ausfall</span>
                <span class="tag">Turnier</span>
            </div>
        </div>
        <div class="card-body">
            @yield('content')
        </div>
    </section>

    @hasSection('plan')
        <section class="card">
            <div class="card-head">
                <div>
                    <p class="eyebrow">Trainingsdetails</p>
                    <h3>@yield('plan_title', 'Aktueller Trainingsplan')</h3>
                </div>
                <div class="tags">
                    <span class="tag tag-success">sichtbar für Trainer:innen</span>
                    <span class="tag tag-warning">Pflege nur Admin</span>
                </div>
            </div>
            <div class="card-body">
                @yield('plan')
            </div>
        </section>
    @endhasSection
</main>

<nav class="bottom-nav">
    <a href="/dashboard" class="nav-item">Dashboard</a>
    <a href="/trainings" class="nav-item">Trainings</a>
    <a href="/turniere" class="nav-item">Turniere</a>
    <a href="/abrechnung" class="nav-item">Abrechnung</a>
</nav>
</body>
</html>
