<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Reference — AI Hub</title>
    <meta name="description" content="Complete API reference for the AI Hub LLM gateway.">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="mx-auto max-w-5xl px-6 py-16">
        {{-- Header --}}
        <div class="mb-12">
            <a href="{{ route('home') }}" class="text-sm text-zinc-400 hover:text-zinc-200 transition-colors">&larr; Back to home</a>
            <h1 class="mt-4 text-4xl font-bold tracking-tight">API Reference</h1>
            <p class="mt-2 text-lg text-zinc-400">Complete reference for the AI Hub LLM gateway API.</p>
        </div>

        {{-- Authentication --}}
        <section class="mb-12">
            <h2 class="text-2xl font-semibold mb-4">Authentication</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 space-y-3">
                <p class="text-zinc-300">All API requests must include an API key. You can use either the <code class="text-emerald-400">Authorization: Bearer</code> header or the <code class="text-emerald-400">x-api-key</code> header.</p>
                <div class="rounded-md bg-zinc-950 p-4 font-mono text-sm text-zinc-300 overflow-x-auto">
                    <span class="text-zinc-500"># Bearer token</span><br>
                    Authorization: Bearer ahk_your_api_key<br><br>
                    <span class="text-zinc-500"># x-api-key header</span><br>
                    x-api-key: ahk_your_api_key
                </div>
            </div>
        </section>

        {{-- Base URL --}}
        <section class="mb-12">
            <h2 class="text-2xl font-semibold mb-4">Base URL</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6">
                <div class="rounded-md bg-zinc-950 p-4 font-mono text-sm text-emerald-400 overflow-x-auto">
                    {{ config('app.url') }}/api/v1
                </div>
            </div>
        </section>

        {{-- Endpoints --}}
        <section class="mb-12">
            <h2 class="text-2xl font-semibold mb-6">Endpoints</h2>

            {{-- Chat Completions --}}
            <div class="mb-8 rounded-lg border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-800 flex items-center gap-3">
                    <span class="px-2 py-1 rounded text-xs font-bold bg-blue-500/20 text-blue-400">POST</span>
                    <code class="text-sm">/v1/chat/completions</code>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-zinc-300">OpenAI-compatible chat completions endpoint. Supports streaming (SSE) and non-streaming responses.</p>
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-400 mb-2">Request Body</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-zinc-400 border-b border-zinc-800">
                                        <th class="py-2 pr-4">Parameter</th>
                                        <th class="py-2 pr-4">Type</th>
                                        <th class="py-2 pr-4">Required</th>
                                        <th class="py-2">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="text-zinc-300">
                                    <tr class="border-b border-zinc-800/50">
                                        <td class="py-2 pr-4 font-mono text-emerald-400">model</td>
                                        <td class="py-2 pr-4 text-zinc-400">string</td>
                                        <td class="py-2 pr-4">Yes</td>
                                        <td class="py-2">The model ID to use (e.g. <code>gpt-4.1</code>)</td>
                                    </tr>
                                    <tr class="border-b border-zinc-800/50">
                                        <td class="py-2 pr-4 font-mono text-emerald-400">messages</td>
                                        <td class="py-2 pr-4 text-zinc-400">array</td>
                                        <td class="py-2 pr-4">Yes</td>
                                        <td class="py-2">Array of message objects with <code>role</code> and <code>content</code></td>
                                    </tr>
                                    <tr class="border-b border-zinc-800/50">
                                        <td class="py-2 pr-4 font-mono text-emerald-400">stream</td>
                                        <td class="py-2 pr-4 text-zinc-400">boolean</td>
                                        <td class="py-2 pr-4">No</td>
                                        <td class="py-2">Set to <code>true</code> for SSE streaming response</td>
                                    </tr>
                                    <tr class="border-b border-zinc-800/50">
                                        <td class="py-2 pr-4 font-mono text-emerald-400">temperature</td>
                                        <td class="py-2 pr-4 text-zinc-400">float</td>
                                        <td class="py-2 pr-4">No</td>
                                        <td class="py-2">Sampling temperature (0-2)</td>
                                    </tr>
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-emerald-400">max_tokens</td>
                                        <td class="py-2 pr-4 text-zinc-400">integer</td>
                                        <td class="py-2 pr-4">No</td>
                                        <td class="py-2">Maximum output tokens to generate</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-zinc-400 mb-2">Example</h4>
                        <div class="rounded-md bg-zinc-950 p-4 font-mono text-sm text-zinc-300 overflow-x-auto">
<pre>curl -X POST {{ config('app.url') }}/api/v1/chat/completions \
  -H "Authorization: Bearer ahk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-4.1",
    "messages": [{"role": "user", "content": "Hello!"}],
    "stream": false
  }'</pre>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Embeddings --}}
            <div class="mb-8 rounded-lg border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-800 flex items-center gap-3">
                    <span class="px-2 py-1 rounded text-xs font-bold bg-purple-500/20 text-purple-400">POST</span>
                    <code class="text-sm">/v1/embeddings</code>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-zinc-300">OpenAI-compatible embeddings endpoint. Generates vector representations of text input.</p>
                    <div class="rounded-md bg-zinc-950 p-4 font-mono text-sm text-zinc-300 overflow-x-auto">
<pre>curl -X POST {{ config('app.url') }}/api/v1/embeddings \
  -H "Authorization: Bearer ahk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "text-embedding-3-small",
    "input": "The quick brown fox"
  }'</pre>
                    </div>
                    <p class="text-zinc-400 text-sm">The <code class="text-emerald-400">input</code> field accepts a string or an array of strings for batch embedding.</p>
                </div>
            </div>

            {{-- Messages --}}
            <div class="mb-8 rounded-lg border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-800 flex items-center gap-3">
                    <span class="px-2 py-1 rounded text-xs font-bold bg-orange-500/20 text-orange-400">POST</span>
                    <code class="text-sm">/v1/messages</code>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-zinc-300">Anthropic-compatible messages endpoint. Use this when your client speaks the Anthropic protocol.</p>
                    <div class="rounded-md bg-zinc-950 p-4 font-mono text-sm text-zinc-300 overflow-x-auto">
<pre>curl -X POST {{ config('app.url') }}/api/v1/messages \
  -H "Authorization: Bearer ahk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "claude-3-7-sonnet",
    "messages": [{"role": "user", "content": "Hello!"}],
    "max_tokens": 128
  }'</pre>
                    </div>
                </div>
            </div>

            {{-- Responses --}}
            <div class="mb-8 rounded-lg border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-800 flex items-center gap-3">
                    <span class="px-2 py-1 rounded text-xs font-bold bg-blue-500/20 text-blue-400">POST</span>
                    <code class="text-sm">/v1/responses</code>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-zinc-300">OpenAI Responses API endpoint. The newer OpenAI API surface for stateful conversations.</p>
                </div>
            </div>

            {{-- List Models --}}
            <div class="mb-8 rounded-lg border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-800 flex items-center gap-3">
                    <span class="px-2 py-1 rounded text-xs font-bold bg-green-500/20 text-green-400">GET</span>
                    <code class="text-sm">/v1/models</code>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-zinc-300">Lists all models your account is entitled to use. OpenAI-compatible format.</p>
                    <div class="rounded-md bg-zinc-950 p-4 font-mono text-sm text-zinc-300 overflow-x-auto">
<pre>curl {{ config('app.url') }}/api/v1/models \
  -H "Authorization: Bearer ahk_your_api_key"</pre>
                    </div>
                </div>
            </div>
        </section>

        {{-- Error Codes --}}
        <section class="mb-12">
            <h2 class="text-2xl font-semibold mb-4">Error Codes</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-zinc-400 border-b border-zinc-800">
                                <th class="px-6 py-3 pr-4">HTTP Status</th>
                                <th class="px-6 py-3 pr-4">Code</th>
                                <th class="px-6 py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody class="text-zinc-300">
                            <tr class="border-b border-zinc-800/50">
                                <td class="px-6 py-3 pr-4 font-mono text-red-400">401</td>
                                <td class="px-6 py-3 pr-4 font-mono text-emerald-400">unauthorized</td>
                                <td class="px-6 py-3">Missing or invalid API key</td>
                            </tr>

                            <tr class="border-b border-zinc-800/50">
                                <td class="px-6 py-3 pr-4 font-mono text-red-400">403</td>
                                <td class="px-6 py-3 pr-4 font-mono text-emerald-400">model_not_allowed</td>
                                <td class="px-6 py-3">API key is not permitted to use the requested model</td>
                            </tr>
                            <tr class="border-b border-zinc-800/50">
                                <td class="px-6 py-3 pr-4 font-mono text-red-400">409</td>
                                <td class="px-6 py-3 pr-4 font-mono text-emerald-400">idempotency_payload_mismatch</td>
                                <td class="px-6 py-3">Idempotency key reused with a different payload</td>
                            </tr>
                            <tr class="border-b border-zinc-800/50">
                                <td class="px-6 py-3 pr-4 font-mono text-red-400">422</td>
                                <td class="px-6 py-3 pr-4 font-mono text-emerald-400">model_unavailable</td>
                                <td class="px-6 py-3">Model is not available for this account</td>
                            </tr>
                            <tr class="border-b border-zinc-800/50">
                                <td class="px-6 py-3 pr-4 font-mono text-red-400">429</td>
                                <td class="px-6 py-3 pr-4 font-mono text-emerald-400">quota_exceeded</td>
                                <td class="px-6 py-3">Token quota limit reached for the current period</td>
                            </tr>
                            <tr class="border-b border-zinc-800/50">
                                <td class="px-6 py-3 pr-4 font-mono text-red-400">503</td>
                                <td class="px-6 py-3 pr-4 font-mono text-emerald-400">provider_circuit_open</td>
                                <td class="px-6 py-3">Provider circuit breaker is open; try again later</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 pr-4 font-mono text-red-400">504</td>
                                <td class="px-6 py-3 pr-4 font-mono text-emerald-400">provider_timeout</td>
                                <td class="px-6 py-3">Upstream provider timed out</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        {{-- Idempotency --}}
        <section class="mb-12">
            <h2 class="text-2xl font-semibold mb-4">Idempotency</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 space-y-3">
                <p class="text-zinc-300">For non-streaming requests, you can send an <code class="text-emerald-400">Idempotency-Key</code> header to safely retry requests without duplicate side-effects.</p>
                <p class="text-zinc-400 text-sm">If the same key is reused with a different payload, the API returns HTTP 409. Replaying with the same key and payload returns the cached response with an <code class="text-emerald-400">X-Idempotent-Replay: true</code> header.</p>
            </div>
        </section>

        {{-- Rate Limiting --}}
        <section class="mb-12">
            <h2 class="text-2xl font-semibold mb-4">Rate Limiting</h2>
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-6 space-y-3">
                <p class="text-zinc-300">Each API key can be configured with a per-minute rate limit and a daily token limit. When the rate limit is exceeded, the API returns HTTP 429.</p>
                <p class="text-zinc-400 text-sm">Rate limits are configurable per API key in the dashboard.</p>
            </div>
        </section>
    </div>
</body>
</html>
