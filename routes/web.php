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

// Todas as outras rotas retornam o app Vue principal (SPA)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
