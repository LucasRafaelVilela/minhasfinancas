<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JogoDaVelhaController;

Route::post('/ia-jogada', [JogoDaVelhaController::class, 'jogadaIA']);
