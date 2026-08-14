{{-- Shared layout for backend test-stub views. Deliberately minimal —
     sidebar + content area, stock Bootstrap classes only. Moved from a
     top navbar to a left sidebar per Rico, 2026-08-12 (functional only,
     not a design pass — that's the frontend team's job). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Sage's sessionStorage key includes this — real bug (Rico,
         2026-08-13, flagged as a security issue): without it, switching
         accounts in the same browser tab kept showing the PREVIOUS
         account's conversation, since sessionStorage is scoped to the
         browser tab/origin, not to who's logged in. See chatbot.js. --}}
    @auth
        <meta name="auth-user-id" content="{{ auth()->id() }}">
    @endauth
    <title>@yield('title', 'SyllabiHub')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="d-flex" style="min-height: 100vh;">
        <nav class="d-flex flex-column flex-shrink-0 p-3 bg-light border-end" style="width: 240px;">
            <a href="{{ url('/') }}" class="d-flex align-items-center mb-3 text-decoration-none">
                <span class="fs-5 fw-bold">SyllabiHub</span>
            </a>
            <hr>
            <ul class="nav nav-pills flex-column mb-auto">
                <li class="nav-item">
                    <a href="{{ route('courses.index') }}" class="nav-link {{ request()->routeIs('courses.index') ? 'active' : 'link-dark' }}">Browse Courses</a>
                </li>
                @auth
                    <li class="nav-item">
                        <a href="{{ route('dashboard.redirect') }}" class="nav-link {{ request()->routeIs('dashboard.*') ? 'active' : 'link-dark' }}">Dashboard</a>
                    </li>
                @endauth
            </ul>
            <hr>
            @auth
                <div class="mb-2">
                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                    <span class="badge bg-secondary">{{ ucfirst(auth()->user()->role) }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Log out</button>
                </form>
            @else
                <a class="btn btn-sm btn-outline-primary w-100" href="{{ route('login') }}">Log in</a>
            @endauth
        </nav>

        <main class="flex-grow-1 p-4">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    {{-- "Sage" floating chat widget — POST /api/chat
         (App\Http\Controllers\ChatbotController). Auth-only, matches the
         route's own auth gate. Plain structurally (test-stub, per
         CLAUDE.md §2/§6 — the real design pass is Aivan/Denze's job) but
         with working open/close + message animation, per Rico
         2026-08-12. Named "Sage" per the team, 2026-08-13 — was
         mislabeled "Ask SyllabiHub" before that, copied from the
         unrelated plain-search feature's name (CLAUDE.md §9) by
         mistake. Behavior lives in resources/js/chatbot.js. --}}
    @auth
        <div id="chatbot-widget" class="chatbot-widget">
            <div class="chatbot-panel">
                <div class="chatbot-panel-header">
                    <span><i class="bi bi-stars"></i> Sage</span>
                    <div class="chatbot-panel-header-actions">
                        {{-- Sage's reply-language preference (2026-08-13, per Rico/
                             supervisor) — English/Tagalog/Taglish, defaults to English.
                             Controls only what language Sage REPLIES in; it still
                             understands a message typed in any of the three regardless
                             of this selection. Persisted in sessionStorage per user, same
                             lifetime as the conversation itself — see chatbot.js. --}}
                        <select id="chatbot-language" class="chatbot-language-select" title="Sage's reply language" aria-label="Sage's reply language">
                            <option value="english" selected>EN</option>
                            <option value="tagalog">TL</option>
                            <option value="taglish">Taglish</option>
                        </select>
                        <button type="button" id="chatbot-new-chat" class="btn-icon" title="New chat" aria-label="Start a new chat">
                            <i class="bi bi-plus-circle"></i>
                        </button>
                        <button type="button" id="chatbot-close" class="btn-close btn-close-white" aria-label="Close Sage"></button>
                    </div>
                </div>

                <div id="chatbot-messages" class="chatbot-messages">
                    <div class="chatbot-msg chatbot-msg-assistant chatbot-msg-in">
                        <div class="chatbot-bubble">Hi, I'm Sage! I can help you find a course or syllabus — for example, try asking "Does COMP 016 have a syllabus available?"</div>
                    </div>
                </div>

                <form id="chatbot-form" class="chatbot-form">
                    <input type="text" id="chatbot-input" class="form-control" placeholder="Ask a question..." autocomplete="off" maxlength="1000" aria-label="Message">
                    <button type="submit" class="btn btn-primary" aria-label="Send"><i class="bi bi-send"></i></button>
                </form>
            </div>

            <button type="button" id="chatbot-toggle" class="chatbot-fab" aria-label="Open Sage">
                <i class="bi bi-chat-dots-fill"></i>
            </button>
        </div>
    @endauth
</body>
</html>
