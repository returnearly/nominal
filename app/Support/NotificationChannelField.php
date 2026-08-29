<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rule;

final readonly class NotificationChannelField
{
    /**
     * @param  'email'|'url'|'password'|'text'|'integer'|'select'  $kind
     * @param  list<string>  $aliases
     * @param  array<string, string>  $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $kind,
        public string $placeholder = '',
        public string $helperText = '',
        public array $aliases = [],
        public int $maxLength = 255,
        public bool $required = true,
        public bool $wide = true,
        public array $options = [],
        public ?int $min = null,
        public ?int $max = null,
    ) {}

    /**
     * @return list<mixed>
     */
    public function rules(): array
    {
        $presence = $this->required ? ['required'] : ['nullable'];

        $specific = match ($this->kind) {
            'email' => ['email', 'max:'.$this->maxLength],
            'url' => ['url', 'max:'.$this->maxLength],
            'password' => ['string', 'max:'.$this->maxLength],
            'integer' => [
                'integer',
                ...($this->min !== null ? ['min:'.$this->min] : []),
                ...($this->max !== null ? ['max:'.$this->max] : []),
            ],
            'select' => ['string', Rule::in(array_keys($this->options))],
            default => ['string', 'max:'.$this->maxLength],
        };

        return [...$presence, ...$specific];
    }
}
