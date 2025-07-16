<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PlaceController;

Route::get('/places', [PlaceController::class, 'getPlaces']);
Route::get('/places/export', [PlaceController::class, 'export']);