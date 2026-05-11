<?php
use Framework\Router\Route;

Route::get("/", function () {
    return view("home");
});

Route::socialAuth();