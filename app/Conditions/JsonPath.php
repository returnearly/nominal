<?php

declare(strict_types=1);

namespace App\Conditions;

final class JsonPath
{
    public static function get(mixed $data, string $path): mixed
    {
        $segments = self::segments($path);
        $current = $data;

        foreach ($segments as $segment) {
            if (is_object($current)) {
                $current = (array) $current;
            }

            if (! is_array($current)) {
                throw new UnresolvedPathException($path);
            }

            $key = is_numeric($segment) && array_key_exists((int) $segment, $current)
                ? (int) $segment
                : $segment;

            if (! array_key_exists($key, $current)) {
                throw new UnresolvedPathException($path);
            }

            $current = $current[$key];
        }

        return $current;
    }

    /**
     * @return list<string>
     */
    private static function segments(string $path): array
    {
        $segments = [];
        $buffer = '';
        $length = strlen($path);

        for ($i = 0; $i < $length; $i++) {
            $character = $path[$i];

            if ($character === '.') {
                if ($buffer !== '') {
                    $segments[] = $buffer;
                    $buffer = '';
                }

                continue;
            }

            if ($character === '[') {
                if ($buffer !== '') {
                    $segments[] = $buffer;
                    $buffer = '';
                }

                $end = strpos($path, ']', $i);

                if ($end === false) {
                    throw new InvalidConditionException("Unclosed index in JSON path [{$path}].");
                }

                $segments[] = substr($path, $i + 1, $end - $i - 1);
                $i = $end;

                continue;
            }

            $buffer .= $character;
        }

        if ($buffer !== '') {
            $segments[] = $buffer;
        }

        return $segments;
    }
}
