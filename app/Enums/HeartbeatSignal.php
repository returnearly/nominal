<?php

declare(strict_types=1);

namespace App\Enums;

enum HeartbeatSignal: string
{
    case Start = 'start';
    case Finish = 'finish';
    case Error = 'error';

    public static function fromRoute(?string $signal): self
    {
        if ($signal === null || $signal === '') {
            return self::Finish;
        }

        return self::from($signal);
    }

    public function succeeded(): bool
    {
        return $this === self::Finish;
    }
}
