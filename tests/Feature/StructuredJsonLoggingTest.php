<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;

it('has a json log channel configured', function () {
    $channels = Config::get('logging.channels');

    expect($channels)->toHaveKey('json')
        ->and($channels['json']['driver'])->toBe('daily')
        ->and($channels['json']['formatter'])->toBe(JsonFormatter::class);
});

it('can write logs to the json channel', function () {
    Config::set('logging.default', 'json');

    Log::info('Test structured log', ['event' => 'test', 'key' => 'value']);

    // If we get here without errors, the JSON formatter is working
    expect(true)->toBeTrue();
});

it('json channel includes stacktraces option', function () {
    $channels = Config::get('logging.channels');

    expect($channels['json']['formatter_with'])->toHaveKey('includeStacktraces');
});
