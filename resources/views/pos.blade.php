<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="description" content="Beconnect POS — Terminal de ponto de venda">
    <meta name="theme-color" content="#1A1A1A">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="BC POS">

    <title>Beconnect POS</title>

    <!-- PWA Manifest dedicado ao POS (landscape, start_url: /pos/terminal) -->
    <link rel="manifest" href="/pos-manifest.json">
    <link rel="icon" type="image/png" href="/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/js/app.js'])

    <script>
        // Registar Service Worker dedicado ao POS
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/build/sw.js', { scope: '/' })
                    .catch(() => {});
            });
        }

        // Forçar modo POS na app Vue — a rota /pos/* activa automaticamente o PosLayout
        window.__BECONNECT_MODE__ = 'pos';
    </script>
</head>
<body class="antialiased bg-gray-100">
    <div id="app"></div>
</body>
</html>
