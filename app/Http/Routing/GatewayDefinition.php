<?php

namespace App\Http\Routing;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Configuration object passed to the closure used by {@see Route::gateway()}.
 */
class GatewayDefinition
{
    /**
     * Either a static URL string (matched {path} wildcard is appended) or a
     * closure that receives the request and returns the complete URL.
     *
     * @var string|Closure(Request): string
     */
    public string|Closure $target = '';

    /**
     * Whether to stream the upstream response back to the client.
     */
    public bool $streaming = true;

    /**
     * Optional request timeout in seconds (null = framework default).
     */
    public ?int $timeoutSeconds = null;

    /**
     * Set the upstream target URL.
     *
     * Accepts a static URL string (the matched {path} wildcard is appended)
     * or a closure that receives the request and returns the complete URL.
     */
    public function to(string|Closure $target): static
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Toggle streaming mode for the upstream response.
     *
     * Streaming is the default (suitable for chat/completions, messages).
     * Disable it for endpoints that return a complete JSON body in one shot
     * (e.g. embeddings, models list).
     */
    public function streaming(bool $streaming = true): static
    {
        $this->streaming = $streaming;

        return $this;
    }

    /**
     * Override the per-request timeout in seconds.
     */
    public function timeout(int $seconds): static
    {
        $this->timeoutSeconds = $seconds;

        return $this;
    }
}
