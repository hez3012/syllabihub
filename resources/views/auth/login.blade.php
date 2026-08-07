{{-- Backend test stub — NOT final UI. Frontend team owns the real design. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login stub — SyllabiHub</title>
</head>
<body>
    <h1>Login (test stub)</h1>

    @if ($errors->any())
        <ul style="color:red">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}">
        @csrf
        <div>
            <label>Email: <input type="email" name="email" value="{{ old('email') }}" required></label>
        </div>
        <div>
            <label>Password: <input type="password" name="password" required></label>
        </div>
        <div>
            <label><input type="checkbox" name="remember"> Remember me</label>
        </div>
        <button type="submit">Log in</button>
    </form>
</body>
</html>
