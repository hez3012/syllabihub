<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="auth-user-id" content="{{ auth()->id() ?? '' }}">
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <title>@yield('title', 'SyllabiHub')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{-- Mobile top bar --}}
    <div class="sh-topbar">
        <button type="button" id="sh-topbar-hamburger" class="sh-topbar-hamburger" aria-label="Open navigation">
            <i class="bi bi-list"></i>
        </button>
        <a href="{{ url('/') }}" class="sh-topbar-logo">
            <img src="{{ asset('images/SyllabiHub - Icon.png') }}" alt="" width="28" height="28">
            <span>SyllabiHub</span>
        </a>
        <div style="width: 40px;"></div>
    </div>

    {{-- Mobile overlay --}}
    <div id="sh-overlay" class="sh-overlay"></div>

    <div class="sh-shell">
        {{-- Sidebar --}}
        <nav id="sh-sidebar" class="sh-sidebar">
            <a href="{{ url('/') }}" class="sh-sidebar-logo">
                <img src="{{ asset('images/SyllabiHub - Icon.png') }}" alt="" width="32" height="32">
                <span>SyllabiHub</span>
            </a>

            <button type="button" id="sh-sidebar-collapse" class="sh-sidebar-collapse-btn" aria-label="Collapse sidebar">
                <i class="bi bi-list"></i>
            </button>

            <div class="sh-sidebar-nav">
                <a href="{{ route('courses.index') }}" class="sh-nav-item {{ request()->routeIs('courses.*') ? 'active' : '' }}">
                    <i class="bi bi-book"></i>
                    <span>Subjects</span>
                </a>

                @auth
                <a href="{{ route('dashboard.redirect') }}" class="sh-nav-item {{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                    <i class="bi bi-grid-1x2"></i>
                    <span>Dashboard</span>
                </a>

                @if (auth()->user()->role === 'admin' || auth()->user()->role === 'intern')
                    <div class="sh-nav-divider"></div>
                    <div class="sh-nav-section-label">Admin</div>

                    <a href="{{ route('course-requests.index') }}" class="sh-nav-item {{ request()->routeIs('course-requests.*') ? 'active' : '' }}">
                        <i class="bi bi-arrow-left-right"></i>
                        <span>Change Requests</span>
                    </a>

                    <a href="{{ route('faculty-accounts.index') }}" class="sh-nav-item {{ request()->routeIs('faculty-accounts.*') ? 'active' : '' }}">
                        <i class="bi bi-people"></i>
                        <span>Faculty Accounts</span>
                    </a>
                @endif
                @endauth
            </div>

            @auth
            <div class="sh-sidebar-user">
                <div class="sh-sidebar-user-info">
                    <div class="sh-sidebar-avatar">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="sh-sidebar-user-name">{{ auth()->user()->name }}</div>
                        <div class="sh-sidebar-user-role">{{ ucfirst(auth()->user()->role) }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="sh-sidebar-logout">
                        <i class="bi bi-box-arrow-left"></i>
                        <span>Log out</span>
                    </button>
                </form>
            </div>
            @else
            <div class="sh-sidebar-user">
                <a href="{{ route('login') }}" class="sh-sidebar-logout" style="text-decoration:none;">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span>Log in</span>
                </a>
            </div>
            @endauth
        </nav>

        {{-- Content area --}}
        <div class="sh-content">
            {{-- Page header --}}
            <div class="sh-page-header">
                <div class="sh-page-header-left">
                    <h1 class="sh-page-title">@yield('title', 'SyllabiHub')</h1>
                </div>
                <div class="sh-page-header-right">
                    @yield('header-actions')
                </div>
            </div>

            {{-- Page body --}}
            <div class="sh-page-body">
                {{-- Toast notifications --}}
                @if (session('status') || $errors->any())
                    <div class="toast-stack">
                        @if (session('status'))
                            <div class="toast-item toast-success">
                                <i class="bi bi-check-circle-fill"></i>
                                <div class="toast-body">{{ session('status') }}</div>
                                <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="toast-item toast-error">
                                <i class="bi bi-exclamation-circle-fill"></i>
                                <div class="toast-body">
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                                <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
                            </div>
                        @endif
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>

    {{-- Sage chatbot --}}
    @auth
        <button type="button" id="sh-sage-fab" class="sh-sage-fab" aria-label="Open Sage">
            <i class="bi bi-chat-dots-fill"></i>
        </button>

        <div id="sh-panel-backdrop" class="sh-panel-backdrop"></div>

        <div id="sh-sage-panel" class="sh-slide-panel sh-sage-panel">
            <div class="sh-sage-panel-header">
                <div class="sh-sage-panel-header-text">
                    <div class="sh-sage-avatar"><i class="bi bi-mortarboard-fill"></i></div>
                    <div>
                        <div class="sh-sage-header-name">Sage</div>
                        <div class="sh-sage-header-status">Online</div>
                    </div>
                </div>
                <div class="sh-sage-panel-header-actions">
                    <select id="chatbot-language" class="sh-sage-lang-select" title="Sage's reply language" aria-label="Sage's reply language">
                        <option value="english" selected>EN</option>
                        <option value="tagalog">TL</option>
                        <option value="taglish">Taglish</option>
                    </select>
                    <button type="button" id="chatbot-new-chat" class="sh-sage-header-btn-icon" title="New chat" aria-label="Start a new chat">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                    <button type="button" id="sh-sage-close" class="sh-sage-header-btn-icon" aria-label="Close Sage">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div id="chatbot-messages" class="sh-sage-messages">
                <div class="sh-sage-msg sh-sage-msg-assistant sh-sage-msg-in">
                    <div class="sh-sage-msg-avatar"><i class="bi bi-mortarboard-fill"></i></div>
                    <div class="sh-sage-bubble">Hi, I'm Sage! I can help you find a course or syllabus — for example, try asking "Does COMP 016 have a syllabus available?"</div>
                </div>
            </div>

            <form id="chatbot-form" class="sh-sage-input-bar" data-no-loading>
                <input type="text" id="chatbot-input" placeholder="Aa" autocomplete="off" maxlength="1000" aria-label="Message">
                <button type="submit" class="sh-sage-send-btn" aria-label="Send">
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>
        </div>
    @endauth

    {{-- Mobile bottom tab bar --}}
    <div class="sh-bottombar">
        <div class="sh-bottombar-inner">
            <a href="{{ route('dashboard.redirect') }}" class="sh-bottombar-item {{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                <i class="bi bi-house-door"></i>
                <span>Home</span>
            </a>
            <a href="{{ route('courses.index') }}" class="sh-bottombar-item {{ request()->routeIs('courses.*') ? 'active' : '' }}">
                <i class="bi bi-book"></i>
                <span>Subjects</span>
            </a>
            @auth
            <button type="button" class="sh-bottombar-item sh-sage-mobile-btn" aria-label="Open Sage">
                <i class="bi bi-chat-dots-fill"></i>
                <span>Sage</span>
            </button>
            <a href="{{ route('dashboard.redirect') }}" class="sh-bottombar-item">
                <i class="bi bi-person"></i>
                <span>Me</span>
            </a>
            @endauth
        </div>
    </div>

    @stack('scripts')
</body>
</html>
