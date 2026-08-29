<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;

final class ChannelMailerFactory implements Factory
{
    public function __construct(private Mailer $mailer) {}

    public function mailer($name = null): Mailer
    {
        return $this->mailer;
    }
}
