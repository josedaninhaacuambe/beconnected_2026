<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Beconnect - O maior mercado virtual de Moçambique. Compra nas melhores lojas do teu país. Entrega em casa.">
    <meta name="theme-color" content="#D4A017">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Beconnect">

    <!-- Open Graph -->
    <meta property="og:title" content="Beconnect - Mercado Virtual de Moçambique">
    <meta property="og:description" content="Compra nas melhores lojas do teu país. Entrega em casa.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">

    <title>Beconnect - Mercado Virtual de Moçambique</title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/png" href="/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Força desregisto do Service Worker antigo ao detectar nova versão do build -->
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                registrations.forEach(function(sw) { sw.unregister(); });
            });
        }
    </script>

    @vite(['resources/js/app.js'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
