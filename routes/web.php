<?php

use Illuminate\Support\Facades\Route;

// Servir manifest PWA com MIME type correcto
Route::get('/manifest.webmanifest', function () {
    $path = public_path('manifest.webmanifest');
    if (file_exists($path)) {
        return response()->file($path, ['Content-Type' => 'application/manifest+json']);
    }
    return response()->json(['name' => 'Beconnect'], 200, ['Content-Type' => 'application/manifest+json']);
});

// Todas as rotas retornam o app Vue (SPA)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
