<?php

use Illuminate\Support\Facades\Route;


Route::view('/', 'welcome')->name('home'); // varsa dokunma
Route::get('/prep-list', \App\Livewire\PrepList::class);