<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — {{ config('app.name', 'AI Hub') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="mx-auto max-w-3xl px-6 py-16">
        <a href="{{ route('home') }}" class="text-sm text-zinc-400 hover:text-zinc-200 transition-colors">&larr; Back to home</a>
        <h1 class="mt-4 text-3xl font-bold tracking-tight">Privacy Policy</h1>
        <p class="mt-2 text-sm text-zinc-500">Last updated: {{ now()->format('F j, Y') }}</p>

        <div class="prose prose-invert mt-8 max-w-none space-y-6 text-zinc-300">
            <section>
                <h2 class="text-xl font-semibold text-zinc-100">1. Information We Collect</h2>
                <p class="mt-2">We collect the following data to operate the Service:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1">
                    <li><strong>Account data:</strong> name, email, team membership</li>
                    <li><strong>Usage data:</strong> API request logs (model, tokens, latency, status), aggregated in usage ledgers</li>
                    <li><strong>Billing data:</strong> payment provider customer IDs, subscription status, invoice records</li>
                    <li><strong>Technical data:</strong> IP addresses (for security and IP allow-lists), user agent, trace IDs</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">2. How We Use Your Data</h2>
                <ul class="mt-2 list-disc pl-6 space-y-1">
                    <li>To authenticate and authorize API requests</li>
                    <li>To calculate usage charges and generate invoices</li>
                    <li>To monitor for abuse, anomalous usage, and security threats</li>
                    <li>To provide operational dashboards and usage analytics</li>
                    <li>To send billing, quota, and security notifications</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">3. Data Sharing</h2>
                <p class="mt-2">We share data with the following third parties:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1">
                    <li><strong>Stripe:</strong> for payment processing and subscription management</li>
                    <li><strong>Upstream LLM providers:</strong> your API requests are forwarded to providers (e.g., OpenAI, Anthropic) to fulfill your requests. We do not share your account credentials with them.</li>
                </ul>
                <p class="mt-2">We do not sell your data to third parties.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">4. Data Retention</h2>
                <p class="mt-2">Request logs are automatically deleted after 30 days. Usage ledgers and billing records are retained for the lifetime of your account for accounting and audit purposes. Audit logs (administrative actions) are retained indefinitely.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">5. Data Security</h2>
                <p class="mt-2">API keys are stored as one-way hashes. Upstream provider secrets are referenced via indirect references, not stored in plaintext in the database. All traffic is encrypted via TLS. Access to administrative functions is role-based and logged.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">6. Your Rights</h2>
                <p class="mt-2">You may:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1">
                    <li>Export your usage and billing data at any time via the dashboard</li>
                    <li>Delete your team and associated data (request logs are deleted; billing records are retained for legal compliance)</li>
                    <li>Revoke API keys at any time</li>
                    <li>Configure IP allow-lists to restrict API key usage</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">7. Webhooks</h2>
                <p class="mt-2">If you configure customer event webhooks, event data is delivered to your specified endpoints with HMAC signature verification. Delivery logs (including response bodies) are retained for debugging purposes.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">8. Changes to This Policy</h2>
                <p class="mt-2">We may update this Privacy Policy from time to time. Material changes will be communicated via email or dashboard notification.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">9. Contact</h2>
                <p class="mt-2">For privacy questions or data requests, contact the platform operator.</p>
            </section>
        </div>
    </div>
</body>
</html>
