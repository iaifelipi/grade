<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tenant {{ $title }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="grade-body">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Tenant {{ $title }}</h1>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-muted">
            Module access granted by tenant group permission.
        </div>
    </div>
</div>
</body>
</html>
