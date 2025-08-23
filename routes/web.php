<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/test', function () {
    $test = "hi";
    echo $test;
    return view('welcome');
});
Route::get('/phpinfo', function () {
    phpinfo();
});
