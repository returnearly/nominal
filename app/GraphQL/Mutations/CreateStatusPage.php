<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Actions\SaveStatusPage;
use App\Models\StatusPage;

final class CreateStatusPage
{
    public function __construct(
        private readonly SaveStatusPage $saveStatusPage,
    ) {}

    /**
     * @param  array{input: array<string, mixed>}  $args
     */
    public function __invoke(mixed $root, array $args): StatusPage
    {
        return $this->saveStatusPage->handle($args['input']);
    }
}
