{{-- Shared layout for backend test-stub views. Deliberately minimal —
     navbar + container + stock Bootstrap classes only. Not the real
     frontend design; that's the frontend team's job. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SyllabiHub')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">SyllabiHub</a>
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="{{ route('subjects.index') }}">Browse Subjects</a>
            </div>
            <div class="navbar-nav ms-auto align-items-lg-center">
                @auth
                    <span class="navbar-text me-3">
                        {{ auth()->user()->name }} <span class="badge bg-secondary">{{ auth()->user()->role }}</span>
                    </span>
                    <a class="nav-link d-inline-block me-2" href="{{ route('dashboard.redirect') }}">Dashboard</a>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Log out</button>
                    </form>
                @else
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('login') }}">Log in</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="container pb-5">
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
</body>
</html>
