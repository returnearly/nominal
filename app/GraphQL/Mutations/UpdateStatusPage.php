<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveStatusPage;
use App\Models\StatusPage;

final class UpdateStatusPage
{
    public function __construct(
        private readonly SaveStatusPage $saveStatusPage,
    ) {}

    /**
     * @param  array{id: string, input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): StatusPage
    {
        $page = StatusPage::query()->findOrFail($args['id']);

        return $this->saveStatusPage->handle($args['input'], $page);
    }
}
