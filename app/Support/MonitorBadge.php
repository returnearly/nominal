<?php

declare(strict_types=1);

namespace App\Support;

final readonly class MonitorBadge
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $label,
        public string $message,
        public string $hexColor,
        public string $namedColor,
        public array $data = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'schemaVersion' => 1,
            'label' => $this->label,
            'message' => $this->message,
            'color' => $this->namedColor,
            ...$this->data,
        ];
    }
}
