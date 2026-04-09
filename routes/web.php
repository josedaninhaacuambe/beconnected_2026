<?php

use Illuminate\Support\Facades\Route;

// Servir manifest PWA principal com MIME type correcto
Route::get('/manifest.webmanifest', function () {
    $path = public_path('manifest.webmanifest');
    if (file_exists($path)) {
        return response()->file($path, ['Content-Type' => 'application/manifest+json']);
    }
    return response()->json(['name' => 'Beconnect'], 200, ['Content-Type' => 'application/manifest+json']);
});

// Servir manifest PWA do POS com MIME type correcto
Route::get('/pos-manifest.json', function () {
    $path = public_path('pos-manifest.json');
    return response()->file($path, ['Content-Type' => 'application/manifest+json']);
});

// PWA POS — serve a view dedicada com manifest landscape
// Quando instalado como PWA, abre directamente no terminal de vendas
Route::get('/pos-app/{any?}', function () {
    return view('pos');
})->where('any', '.*');

// Serve assets Vite/PWA disponíveis no build public/build
Route::get('/assets/{path}', function (string $path) {
    $file = public_path("build/assets/{$path}");
    abort_unless(file_exists($file), 404);
    return response()->file($file);
})->where('path', '.*');

Route::get('/registerSW.js', function () {
    $file = public_path('build/registerSW.js');
    abort_unless(file_exists($file), 404);
    return response()->file($file, ['Content-Type' => 'application/javascript']);
});

Route::get('/sw.js', function () {
    $file = public_path('build/sw.js');
    abort_unless(file_exists($file), 404);
    return response()->file($file, ['Content-Type' => 'application/javascript']);
});

Route::get('/workbox-{hash}.js', function (string $hash) {
    $file = public_path("build/workbox-{$hash}.js");
    abort_unless(file_exists($file), 404);
    return response()->file($file, ['Content-Type' => 'application/javascript']);
});

Route::get('/manifest.json', function () {
    $file = public_path('manifest.webmanifest');
    abort_unless(file_exists($file), 404);
    return response()->file($file, ['Content-Type' => 'application/manifest+json']);
});

// Todas as outras rotas retornam o app Vue principal (SPA)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
