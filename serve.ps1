# Local dev server — direct php -S, NOT "php artisan serve".
#
# Why not "php artisan serve": On Windows it spawns the real listening
# process 2 levels deep (artisan -> cmd.exe -> php -S) via Symfony
# Process. Ctrl+C in the terminal only kills the top-level artisan
# process; the cmd.exe + php -S underneath survive as orphans, still
# bound to port 8000 and still serving requests, even though the
# terminal looks stopped. This runs the same built-in server as a
# single direct process (no wrapper), so Ctrl+C here kills it cleanly.
#
# Why "Push-Location public" is required: vendor/laravel/framework/.../
# resources/server.php (the router script Laravel itself uses) calls
# getcwd() and requires "<cwd>/index.php" — it assumes the PHP process's
# actual working directory IS public/, not the project root. That's
# exactly how Laravel's own ServeCommand runs it (Process::new(...,
# public_path(), ...)). Passing -t public from the project root does
# NOT set this — -t only controls the built-in server's static-file
# docroot, not getcwd() — so without this Push-Location, every request
# 500s with "Failed opening required '<root>/index.php'".
#
# Usage: .\serve.ps1
$root = $PSScriptRoot
$router = Join-Path $root 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'

Push-Location (Join-Path $root 'public')
try {
    php -S 127.0.0.1:8000 $router
} finally {
    Pop-Location
}
