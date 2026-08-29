<?php

declare(strict_types=1);

namespace App\Actions;

use App\Conditions\CheckContext;
use App\Conditions\ConditionEvaluator;
use App\Conditions\ConditionOutcome;
use App\Models\Monitor;
use DateTimeImmutable;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class EvaluateCheckConditions implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private ConditionEvaluator $evaluator,
        private LookupDomainExpiration $domains,
    ) {}

    /**
     * @return array{0: list<ConditionOutcome>, 1: bool, 2: string|null, 3: DateTimeImmutable|null}
     */
    public function handle(Monitor $monitor, CheckContext $context, ?string $error): array
    {
        $domainExpiresAt = $this->domains->handle($monitor);
        $context = $this->withDomainExpiration($context, $domainExpiresAt);

        $expressions = $monitor->conditions->pluck('expression')->all();
        $outcomes = $this->evaluator->evaluateAll($expressions, $context);

        $success = $expressions === []
            ? $context->connected && ($context->status === null || $context->status < 400)
            : $this->evaluator->allPassed($outcomes);

        $message = $error;

        if ($message === null && ! $success) {
            $message = 'Check failed.';

            foreach ($outcomes as $outcome) {
                if (! $outcome->passed) {
                    $message = "Condition failed: {$outcome->expression}";
                    break;
                }
            }
        }

        return [$outcomes, $success, $message, $domainExpiresAt];
    }

    private function withDomainExpiration(CheckContext $context, ?DateTimeImmutable $expiresAt): CheckContext
    {
        return new CheckContext(
            status: $context->status,
            responseTimeMs: $context->responseTimeMs,
            ip: $context->ip,
            connected: $context->connected,
            certificateExpirationSeconds: $context->certificateExpirationSeconds,
            domainExpirationSeconds: $expiresAt instanceof DateTimeImmutable
                ? $expiresAt->getTimestamp() - time()
                : null,
            body: $context->body,
            rawBody: $context->rawBody,
            bodyPathExisted: $context->bodyPathExisted,
            dnsRcode: $context->dnsRcode,
            redirectUrl: $context->redirectUrl,
        );
    }
}
