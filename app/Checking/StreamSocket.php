<?php

declare(strict_types=1);

namespace App\Checking;

use App\Enums\IpFamily;

final class StreamSocket
{
    /**
     * @param  resource  $client
     */
    public static function peerIp(mixed $client): ?string
    {
        $name = stream_socket_get_name($client, true);

        if (! is_string($name) || $name === '') {
            return null;
        }

        if (str_starts_with($name, '[')) {
            $end = strpos($name, ']');

            return $end === false ? $name : substr($name, 1, $end - 1);
        }

        return explode(':', $name)[0];
    }

    /**
     * @return resource|null
     */
    public static function context(IpFamily $family, array $options = []): mixed
    {
        $socket = match ($family) {
            IpFamily::Ipv4 => ['bindto' => '0.0.0.0:0'],
            IpFamily::Ipv6 => ['bindto' => '[::]:0'],
            IpFamily::Any => [],
        };

        if ($socket !== []) {
            $options['socket'] = [...($options['socket'] ?? []), ...$socket];
        }

        if ($options === []) {
            return null;
        }

        return stream_context_create($options);
    }
}
