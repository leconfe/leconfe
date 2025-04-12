<?php

use App\Http\Controllers\LogoutController;
use App\Http\Controllers\OAIController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('download/{path}', function (string $path, Request $request) {
    abort_if(! $request->hasValidSignature(), 401);

    try {
        $storage = Storage::disk($request->query('disk', 'local'));
    } catch (\Throwable $th) {
        abort(404);
    }

    abort_if(! $storage->exists($path), 404);

    return $storage->download($path);
})->where('path', '.*')->name('download');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect()->route('livewirePageGroup.website.pages.home');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::get('oai', [OAIController::class, 'handleRequest'])->name('oai-pmh');

Route::any('logout', LogoutController::class)->name('logout');
