<?php

declare(strict_types=1);

namespace App\Actions;

use App\Conditions\CheckContext;
use App\Conditions\ConditionEvaluator;
use App\Conditions\ConditionOutcome;
use App\Models\Monitor;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class EvaluateCheckConditions implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(
        private ConditionEvaluator $evaluator,
    ) {}

    /**
     * @return array{0: list<ConditionOutcome>, 1: bool, 2: string|null}
     */
    public function handle(Monitor $monitor, CheckContext $context, ?string $error): array
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
