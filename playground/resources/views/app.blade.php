<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />
    @inertiaHead
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
</head>
<body>
    @inertia
</body>
</html>
