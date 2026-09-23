<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Autenticación') · SCIEM</title>
    @vite(['resources/css/login.css', 'resources/js/auth/login.js'])
</head>
<body class="login-page">
    @yield('content')
</body>
</html>
