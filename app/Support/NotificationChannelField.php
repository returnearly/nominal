<?php

declare(strict_types=1);

namespace App\Support;

final readonly class NotificationChannelField
{
    /**
     * @param  'email'|'url'|'password'|'text'  $kind
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $kind,
        public string $placeholder = '',
        public string $helperText = '',
        public array $aliases = [],
        public int $maxLength = 255,
    ) {}

    /**
     * @return list<string>
     */
    public function rules(): array
    {
        $max = 'max:'.$this->maxLength;

        return match ($this->kind) {
            'email' => ['required', 'email', $max],
            'url' => ['required', 'url', $max],
            'password' => ['required', 'string', $max],
            default => ['required', 'string', $max],
        };
    }
}
