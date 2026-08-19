<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationChannelType: string
{
    case Mail = 'mail';
    case Slack = 'slack';
    case MicrosoftTeams = 'microsoft_teams';
    case Discord = 'discord';
    case Webhook = 'webhook';
    case Pagerduty = 'pagerduty';
}
