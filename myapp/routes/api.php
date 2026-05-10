<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\FileController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::get('/public/files/{id}/download', [FileController::class, 'publicDownload']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files/upload', [FileController::class, 'upload']);
    Route::get('/files/download/{id}', [FileController::class, 'download']);
    Route::delete('/files/{id}', [FileController::class, 'delete']);
    Route::put('/files/{id}/toggle-visibility', [FileController::class, 'toggleVisibility']);
});
