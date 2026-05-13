<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Livewire\Calendar;
use App\Livewire\Channels\Conflicts as ChannelConflicts;
use App\Livewire\Channels\Index as ChannelsIndex;
use App\Livewire\Channels\Logs as ChannelLogs;
use App\Livewire\Channels\Mappings as ChannelMappings;
use App\Livewire\Channels\Show as ChannelShow;
use App\Livewire\Dashboard;
use App\Livewire\Guests\Index as GuestsIndex;
use App\Livewire\Inventory\Dashboard as InventoryDashboard;
use App\Livewire\Inventory\Locations as InventoryLocations;
use App\Livewire\Inventory\Minibars as InventoryMinibars;
use App\Livewire\Inventory\Movements as InventoryMovements;
use App\Livewire\Inventory\Pos as InventoryPos;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Pricing\Bulk as PricingBulk;
use App\Livewire\Pricing\Calendar as PricingCalendar;
use App\Livewire\Pricing\Restrictions as PricingRestrictions;
use App\Livewire\Pricing\Rules as PricingRules;
use App\Livewire\Products\Index as ProductsIndex;
use App\Livewire\Reservations\Index as ReservationIndex;
use App\Livewire\Reservations\Show as ReservationShow;
use App\Livewire\Reservations\Wizard as ReservationWizard;
use App\Livewire\Rooms\Index as RoomsIndex;
use App\Livewire\Rooms\Minibar as RoomMinibar;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

// Language switching is available to guests AND auth'd users.
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard',     Dashboard::class)->name('dashboard');
    Route::get('/calendar',      Calendar::class)->name('calendar');

    Route::get('/reservations',                ReservationIndex::class)->name('reservations.index');
    Route::get('/reservations/create',         ReservationWizard::class)->name('reservations.create');
    Route::get('/reservations/{reservation}',  ReservationShow::class)->name('reservations.show');

    Route::get('/rooms',  RoomsIndex::class)->name('rooms.index');
    Route::get('/rooms/{room}/minibar', RoomMinibar::class)->name('rooms.minibar');

    Route::get('/guests', GuestsIndex::class)->name('guests.index');

    Route::get('/invoices/{invoice}', InvoiceShow::class)->name('invoices.show');

    // Phase 2: inventory + POS
    Route::get('/products',             ProductsIndex::class)->name('products.index');
    Route::get('/inventory',            InventoryDashboard::class)->name('inventory.index');
    Route::get('/inventory/movements',  InventoryMovements::class)->name('inventory.movements');
    Route::get('/inventory/locations',  InventoryLocations::class)->name('inventory.locations');
    Route::get('/inventory/minibars',   InventoryMinibars::class)->name('inventory.minibars');
    Route::get('/inventory/pos',        InventoryPos::class)->name('inventory.pos');

    // Phase 3: pricing
    Route::get('/pricing/rules',        PricingRules::class)->name('pricing.rules');
    Route::get('/pricing/calendar',     PricingCalendar::class)->name('pricing.calendar');
    Route::get('/pricing/bulk',         PricingBulk::class)->name('pricing.bulk');
    Route::get('/pricing/restrictions', PricingRestrictions::class)->name('pricing.restrictions');

    // Phase 4: channel manager
    Route::get('/channels',                          ChannelsIndex::class)->name('channels.index');
    Route::get('/channels/conflicts',                ChannelConflicts::class)->name('channels.conflicts');
    Route::get('/channels/{connection}',             ChannelShow::class)->name('channels.show');
    Route::get('/channels/{connection}/mappings',    ChannelMappings::class)->name('channels.mappings');
    Route::get('/channels/{connection}/logs',        ChannelLogs::class)->name('channels.logs');

    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
