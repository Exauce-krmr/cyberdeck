<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="{{ $csrf }}">
    <meta name="theme-color" content="#101118">
    <title>@yield('title', 'Arène') · Cyberdeck</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=Hanken+Grotesk:wght@400;600&display=swap" rel="stylesheet">

    {{-- Versions épinglées, vérifiées sur jsDelivr le 10/10/2026 : pas d'étape de build --}}
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4.3.3"></script>
    <style type="text/tailwindcss">
        @theme {
            --color-ink: #101118;
            --color-panel: #1a1d26;
            --color-line: #353a4a;
            --color-ice: #ecebe3;
            --color-muted: #8c92a6;
            --color-you: #3d8bff;
            --color-foe: #b44dff;
            --color-accent: #8a7dff;
            --font-display: "Chakra Petch", system-ui, sans-serif;
            --font-body: "Hanken Grotesk", system-ui, sans-serif;
        }
    </style>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="/css/components/boosters.css">

    <script defer src="https://cdn.jsdelivr.net/npm/motion@14.1.0/dist/motion.js"></script>
    {{-- Version épinglée, vérifiée sur npm le 10/10/2026 (MIT, sans dépendance) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/powerglitch@2.5.0/dist/powerglitch.min.js"></script>
    <script type="module" src="/js/main.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.17.4/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen font-body text-ice antialiased">
    {{-- En partie (body.is-immersive), ce header est remplacé par la barre de jeu ; son bouton ☰ le fait apparaître en menu déroulant (body.menu-open). --}}
    <header class="site-header sticky top-0 z-20 border-b-[3px] border-line bg-ink/90 backdrop-blur" x-data @keydown.escape.window="document.body.classList.remove('menu-open')"
            @click.window="if (!$el.contains($event.target) && !$event.target.closest('.hud-menu')) document.body.classList.remove('menu-open')">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-8 gap-y-1 px-4 pt-3">
            <a href="/" class="flex items-center gap-2 pb-2 font-display text-lg font-bold tracking-wide">
                <span class="size-3 rotate-45 bg-accent" aria-hidden="true"></span><span class="glitch">Cyberdeck</span>
            </a>

            <div class="order-last flex w-full gap-6 md:order-none md:w-auto md:flex-1">
                @if ($username)
                    <a href="/game" class="navlink" :aria-current="location.pathname.startsWith('/game') || null">Jouer</a>
                    <a href="/collection" class="navlink" :aria-current="location.pathname.startsWith('/collection') || null">Collection</a>
                    <a href="/boosters" class="navlink" :aria-current="location.pathname.startsWith('/boosters') || null">Boosters</a>
                    @if ($devAccess)
                        <a href="/dev" class="navlink" :aria-current="location.pathname.startsWith('/dev') || null">Labo</a>
                    @endif
                @endif
                <a href="/leaderboard" class="navlink" :aria-current="location.pathname.startsWith('/leaderboard') || null">Classement</a>
            </div>

            <div class="ml-auto flex items-center gap-3 pb-2 text-sm">
                @if ($username)
                    <a href="/profile" class="user-chip" title="Mon profil" :aria-current="location.pathname.startsWith('/profile') || null">{{ $username }}</a>
                    <form method="post" action="/logout">
                        <input type="hidden" name="_csrf" value="{{ $csrf }}">
                        <button class="btn btn-ghost !min-h-10 !px-4 !text-sm">Déconnexion</button>
                    </form>
                @else
                    <a href="/login" class="navlink">Connexion</a>
                    <a href="/register" class="btn !min-h-10 !px-4 !text-sm">Inscription</a>
                @endif
            </div>
        </nav>
        
    </header>

    <main class="mx-auto max-w-6xl px-4 py-5 sm:py-12">
        @yield('content')
    </main>
</body>
</html>
