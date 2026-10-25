<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        return $user->role === 'admin' ? redirect('/admin') : redirect('/teller');
    }

    return redirect('/login');
})->name('login');
