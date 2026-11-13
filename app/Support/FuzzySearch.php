<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class FuzzySearch
{
    public const int MinTypoLength = 4;

    /**
     * @return list<string>
     */
    public static function tokens(string $search): array
    {
        $search = trim(preg_replace('/\s+/u', ' ', $search) ?? $search);

        if ($search === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $search),
            static fn (string $token): bool => $token !== '',
        ));
    }

    /**
     * @return list<string>
     */
    public static function patterns(string $token): array
    {
        $escaped = self::escapeLike($token);
        $patterns = ['%'.$escaped.'%'];

        if (mb_strlen($token) < self::MinTypoLength) {
            return $patterns;
        }

        $length = mb_strlen($token);

        for ($i = 0; $i < $length; $i++) {
            $before = self::escapeLike(mb_substr($token, 0, $i));
            $after = self::escapeLike(mb_substr($token, $i + 1));
            $patterns[] = '%'.$before.'_'.$after.'%';
        }

        return array_values(array_unique($patterns));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $columns
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function apply(Builder $query, string $search, array $columns): Builder
    {
        $tokens = self::tokens($search);

        if ($tokens === [] || $columns === []) {
            return $query;
        }

        foreach ($tokens as $token) {
            $patterns = self::patterns($token);

            $query->where(function (Builder $tokenQuery) use ($columns, $patterns): void {
                foreach ($columns as $column) {
                    foreach ($patterns as $pattern) {
                        self::whereCaseInsensitiveLike($tokenQuery, $column, $pattern, or: true);
                    }
                }
            });
        }

        return $query;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function whereCaseInsensitiveLike(
        Builder $query,
        string $column,
        string $pattern,
        bool $or = false,
    ): void {
        $driver = $query->getConnection()->getDriverName();
        $wrapped = $query->getGrammar()->wrap($column);
        $binding = mb_strtolower($pattern);

        $sql = match ($driver) {
            'pgsql' => "{$wrapped} ILIKE ? ESCAPE '\\'",
            default => "LOWER({$wrapped}) LIKE ? ESCAPE '\\'",
        };

        if ($or) {
            $query->orWhereRaw($sql, [$binding]);

            return;
        }

        $query->whereRaw($sql, [$binding]);
    }

    public static function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }
}
