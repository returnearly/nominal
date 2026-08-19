<?php

declare(strict_types=1);

namespace App\Checking;

use App\Conditions\CheckContext;
use App\Conditions\ConditionEvaluator;
use App\Conditions\ConditionOutcome;
use App\Models\Monitor;

final class ConditionRunner
{
    public function __construct(
        private readonly ConditionEvaluator $evaluator,
    ) {}

    /**
     * @return array{0: list<ConditionOutcome>, 1: bool, 2: string|null}
     */
    public function run(Monitor $monitor, CheckContext $context, ?string $error): array
    {
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

        return [$outcomes, $success, $message];
    }
}
