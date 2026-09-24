{{-- Minimal layout for auth pages (login, forgot/reset password) —
     intentionally no navbar/nav links here: you're not "in" the app yet,
     so there's nothing to navigate to and no reason for a second
     "Log in" button next to the one already on the form. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <title>@yield('title', 'SyllabiHub')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth-shell-stacked">
        <div class="auth-header-banner">
            <img src="{{ asset('images/syllabihub-header.jpg') }}" alt="SyllabiHub — Polytechnic University of the Philippines, Taguig" class="auth-header-img">
        </div>

        <div class="auth-form-panel">
            <div class="auth-form-inner">
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
                                <div class="toast-body">{{ $errors->first() }}</div>
                                <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
                            </div>
                        @endif
                    </div>
                @endif

                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
