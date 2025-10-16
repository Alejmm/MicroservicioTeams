<?php

use Illuminate\Support\Facades\Route;

Route::get('/{any}', fn () => view('welcome'))
    ->where('any', '^(?!api).*$');
