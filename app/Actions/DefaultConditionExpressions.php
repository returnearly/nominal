<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\ConditionComparator;
use App\Enums\ConditionPlaceholder;
use App\Enums\MonitorType;
use ReturnEarly\ActionsPattern\Interfaces\ActionsPatternInterface;
use ReturnEarly\ActionsPattern\Traits\ActionsPattern;

final readonly class DefaultConditionExpressions implements ActionsPatternInterface
{
    use ActionsPattern;

    public function __construct(private ComposeConditionExpression $compose) {}

    /**
     * @return list<string>
     */
    public function handle(mixed $type): array
    {
        return array_map($this->compose->handle(...), $this->items($type));
    }

    /**
     * @return list<array{placeholder: string, path: string, comparator: string, value: string}>
     */
    private function items(mixed $type): array
    {
        return match ($this->type($type)) {
            MonitorType::Dns => [
                [
                    'placeholder' => ConditionPlaceholder::DnsRcode->value,
                    'path' => '',
                    'comparator' => ConditionComparator::Equal->value,
                    'value' => 'NOERROR',
                ],
            ],
            MonitorType::Heartbeat => [],
            MonitorType::Tls => [
                [
                    'placeholder' => ConditionPlaceholder::Connected->value,
                    'path' => '',
                    'comparator' => ConditionComparator::Equal->value,
                    'value' => 'true',
                ],
                [
                    'placeholder' => ConditionPlaceholder::CertificateExpiration->value,
                    'path' => '',
                    'comparator' => ConditionComparator::GreaterThan->value,
                    'value' => '48h',
                ],
            ],
            MonitorType::Http, MonitorType::GraphQL => [
                [
                    'placeholder' => ConditionPlaceholder::Status->value,
                    'path' => '',
                    'comparator' => ConditionComparator::GreaterThanOrEqual->value,
                    'value' => '200',
                ],
                [
                    'placeholder' => ConditionPlaceholder::Status->value,
                    'path' => '',
                    'comparator' => ConditionComparator::LessThanOrEqual->value,
                    'value' => '299',
                ],
            ],
            MonitorType::Ping, MonitorType::Tcp, MonitorType::Udp, MonitorType::WebSocket, MonitorType::Mysql, MonitorType::Redis, MonitorType::Postgres => [
                [
                    'placeholder' => ConditionPlaceholder::Connected->value,
                    'path' => '',
                    'comparator' => ConditionComparator::Equal->value,
                    'value' => 'true',
                ],
                [
                    'placeholder' => ConditionPlaceholder::ResponseTime->value,
                    'path' => '',
                    'comparator' => ConditionComparator::LessThan->value,
                    'value' => '50',
                ],
            ],
        };
    }

    private function type(mixed $type): MonitorType
    {
        if ($type instanceof MonitorType) {
            return $type;
        }

        return MonitorType::tryFrom((string) $type) ?? MonitorType::Http;
    }
}
