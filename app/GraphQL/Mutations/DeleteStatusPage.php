<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\StatusPage;

final class DeleteStatusPage
{
    /**
     * @param  array{id: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $page = StatusPage::query()->findOrFail($args['id']);

        return (bool) $page->delete();
    }
}
