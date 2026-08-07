{{-- Minimal layout for auth pages (login, forgot/reset password) —
     intentionally no navbar/nav links here: you're not "in" the app yet,
     so there's nothing to navigate to and no reason for a second
     "Log in" button next to the one already on the form. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SyllabiHub')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light">
    <div class="container py-5">
        <p class="text-center mb-4">
            <a href="{{ url('/') }}" class="h4 text-decoration-none">SyllabiHub</a>
        </p>

        @if (session('status'))
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="alert alert-success">{{ session('status') }}</div>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @yield('content')
    </div>
</body>
</html>
