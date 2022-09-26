<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
//     return Inertia::render('Welcome', [
//         'canLogin' => Route::has('login'),
//         'canRegister' => Route::has('register'),
//         'laravelVersion' => Application::VERSION,
//         'phpVersion' => PHP_VERSION,
//     ]);
// });

// Route::get('/dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

// require __DIR__.'/auth.php';

Route::get('/old', [App\Http\Controllers\RouteController::class, 'init'])->name('home');
Route::get('/', [App\Http\Controllers\RouteController::class, 'init2'])->name('home2');

Route::post('import-gpx', [App\Http\Controllers\RouteController::class, 'import']);
Route::post('import-gpx-from-filename', [App\Http\Controllers\RouteController::class, 'importFromFilename']);
Route::post('export-gpx', [App\Http\Controllers\RouteController::class, 'export']);


// VeloViewer clone for walks with Amy Lynn: squares of 200x200 m over Sittard
Route::get('/little-explorer', [App\Http\Controllers\LittleExplorerController::class, 'start']);