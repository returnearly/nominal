<?php

declare(strict_types=1);

use App\Actions\DefaultConditionFormState;
use App\Actions\FillConditionForm;
use App\Actions\NewConditionExpression;
use App\Actions\RecordConditionExpression;
use App\Enums\MonitorType;

it('fills picker fields from a stored expression', function () {
    expect(FillConditionForm::make()->handle([
        'id' => '1',
        'expression' => '[BODY].status == UP',
    ]))->toMatchArray([
        'id' => '1',
        'expression' => '[BODY].status == UP',
        'placeholder' => '[BODY]',
        'path' => '.status',
        'comparator' => '==',
        'value' => 'UP',
    ]);
});

it('records picker fields as a stored expression', function () {
    expect(RecordConditionExpression::make()->handle([
        'id' => '1',
        'placeholder' => '[STATUS]',
        'path' => '',
        'comparator' => '==',
        'value' => '200',
    ]))->toBe([
        'id' => '1',
        'expression' => '[STATUS] == 200',
    ]);
});

it('returns the first default condition for a type', function () {
    expect(NewConditionExpression::make()->handle(MonitorType::Http))->toBe([
        'placeholder' => '[STATUS]',
        'path' => '',
        'comparator' => '>=',
        'value' => '200',
    ])
        ->and(NewConditionExpression::make()->handle(MonitorType::Heartbeat))->toBe([
            'placeholder' => '[CONNECTED]',
            'path' => '',
            'comparator' => '==',
            'value' => 'true',
        ]);
});

it('builds keyed form state from default expressions', function () {
    $state = DefaultConditionFormState::make()->handle(MonitorType::Dns);

    expect($state)->toHaveCount(1)
        ->and(array_values($state)[0])->toBe([
            'placeholder' => '[DNS_RCODE]',
            'path' => '',
            'comparator' => '==',
            'value' => 'NOERROR',
        ]);
});
