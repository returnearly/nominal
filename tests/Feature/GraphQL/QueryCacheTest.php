<?php

declare(strict_types=1);

use GraphQL\Language\Parser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->queryCachePath = storage_path('framework/cache/lighthouse-query-cache-test');
    File::deleteDirectory($this->queryCachePath);
    File::ensureDirectoryExists($this->queryCachePath);

    // Production cache refuses to unserialize objects (`serializable_classes` is false).
    // The database store matches that path; the array store used by the rest of the suite does not.
    config([
        'cache.serializable_classes' => false,
        'cache.stores.serializing' => [
            'driver' => 'database',
            'connection' => null,
            'table' => 'cache',
            'lock_connection' => null,
            'lock_table' => null,
        ],
        'lighthouse.query_cache.enable' => true,
        'lighthouse.query_cache.store' => 'serializing',
        'lighthouse.query_cache.opcache_path' => $this->queryCachePath,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->queryCachePath);
});

it('reuses a shared query cache entry across requests', function () {
    $query = '{ __typename }';

    graphql($query)
        ->assertSuccessful()
        ->assertJsonPath('data.__typename', 'Query');

    graphql($query)
        ->assertSuccessful()
        ->assertJsonPath('data.__typename', 'Query');
});

it('serves later requests when the shared query cache is an incomplete class', function () {
    $query = '{ __typename }';
    $key = 'lighthouse:query:'.hash('sha256', $query);

    Cache::store('serializing')->put($key, Parser::parse($query), 60);

    expect(Cache::store('serializing')->get($key))->toBeInstanceOf(__PHP_Incomplete_Class::class);

    graphql($query)
        ->assertSuccessful()
        ->assertJsonPath('data.__typename', 'Query');

    expect(Cache::store('serializing')->get($key))->toBeString();

    File::deleteDirectory($this->queryCachePath);
    File::ensureDirectoryExists($this->queryCachePath);

    graphql($query)
        ->assertSuccessful()
        ->assertJsonPath('data.__typename', 'Query');
});
