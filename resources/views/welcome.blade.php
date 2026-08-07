<!DOCTYPE html>
<html lang="en">

<head>
    <style>
        .custom-test-box {
            background-color: red;
            color: white;
            padding: 20px;
            font-size: 24px;
            border-radius: 10px;
        }
    </style>
    <meta charset="UTF-8">
    <title>SyllabiHub Test</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="p-4">
    <div class="custom-test-box mt-3">
        Custom CSS Test — dapat pula ito
    </div>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark rounded mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">SyllabiHub</a>
        </div>
    </nav>

    <button class="btn btn-primary">
        <i class="bi bi-house"></i> Bootstrap Button
    </button>

</body>

</html>