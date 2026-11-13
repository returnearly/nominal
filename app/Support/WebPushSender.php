<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\SendsWebPush;
use App\Models\PushSubscription;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

final class WebPushSender implements SendsWebPush
{
    /**
     * @param  array{vapid_public_key: string, vapid_private_key: string, vapid_subject: string}  $vapid
     * @param  iterable<PushSubscription>  $subscriptions
     * @param  array<string, mixed>  $payload
     * @return list<string> Subscription IDs that should be deleted (gone endpoints).
     */
    public function send(array $vapid, iterable $subscriptions, array $payload): array
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $vapid['vapid_subject'],
                'publicKey' => $vapid['vapid_public_key'],
                'privateKey' => $vapid['vapid_private_key'],
            ],
        ]);

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $byEndpoint = [];

        foreach ($subscriptions as $subscription) {
            $byEndpoint[$subscription->endpoint] = $subscription->id;

            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aes128gcm',
                ]),
                $json,
                ['TTL' => 3600],
            );
        }

        $gone = [];

        try {
            /** @var MessageSentReport $report */
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    continue;
                }

                $endpoint = $report->getEndpoint();
                $code = $report->getResponse()?->getStatusCode();

                if (($code === 404 || $code === 410) && isset($byEndpoint[$endpoint])) {
                    $gone[] = $byEndpoint[$endpoint];

                    continue;
                }

                report(new \RuntimeException(
                    'Web push failed for '.$endpoint.': '.$report->getReason(),
                ));
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $gone;
    }
}
