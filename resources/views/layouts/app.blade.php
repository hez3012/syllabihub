{{-- Shared layout for backend test-stub views. Deliberately minimal —
     sidebar + content area, stock Bootstrap classes only. Moved from a
     top navbar to a left sidebar per Rico, 2026-08-12 (functional only,
     not a design pass — that's the frontend team's job). --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                    <a href="{{ route('subjects.index') }}" class="nav-link {{ request()->routeIs('subjects.index') ? 'active' : 'link-dark' }}">Browse Subjects</a>
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
</body>
</html>
