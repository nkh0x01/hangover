<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\UserResource\Pages;

use App\Modules\Identity\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

final class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;
}
