<?php

use Illuminate\Support\Facades\Route;

// Todas as rotas retornam o app Vue (SPA)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
