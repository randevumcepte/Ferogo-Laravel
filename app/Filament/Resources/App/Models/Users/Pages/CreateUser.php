<?php

namespace App\Filament\Resources\App\Models\Users\Pages;

use App\Filament\Resources\App\Models\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
