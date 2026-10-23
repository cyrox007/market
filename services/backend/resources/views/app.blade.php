<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Светофор Мебели</title>

    <!-- Подключаем сборку Vite -->
    @viteReactRefresh
    @vite('resources/js/app.jsx')
</head>
<body>
    <div id="root"></div>
</body>
</html>