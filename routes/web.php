<?php

use App\Http\Controllers\ProfileController;
use App\Livewire\Calendar;
use App\Livewire\Dashboard;
use App\Livewire\Guests\Index as GuestsIndex;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Reservations\Index as ReservationIndex;
use App\Livewire\Reservations\Show as ReservationShow;
use App\Livewire\Reservations\Wizard as ReservationWizard;
use App\Livewire\Rooms\Index as RoomsIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',     Dashboard::class)->name('dashboard');
    Route::get('/calendar',      Calendar::class)->name('calendar');

    Route::get('/reservations',                ReservationIndex::class)->name('reservations.index');
    Route::get('/reservations/create',         ReservationWizard::class)->name('reservations.create');
    Route::get('/reservations/{reservation}',  ReservationShow::class)->name('reservations.show');

    Route::get('/rooms',  RoomsIndex::class)->name('rooms.index');
    Route::get('/guests', GuestsIndex::class)->name('guests.index');

    Route::get('/invoices/{invoice}', InvoiceShow::class)->name('invoices.show');

    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
