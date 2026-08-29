<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Settings\Resources\Users\Pages;

use App\Enums\InterfaceAuth;
use App\Filament\Clusters\Settings\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Schema;

final class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        $schema = parent::content($schema);

        return $schema->components([
            Callout::make('Interface authentication is disabled')
                ->description('Visitors are signed in as the local operator. User accounts and passwords on this page do not control who can access the admin interface.')
                ->warning()
                ->visible(InterfaceAuth::current() === InterfaceAuth::None),
            ...$schema->getComponents(withHidden: true),
        ]);
    }
}
