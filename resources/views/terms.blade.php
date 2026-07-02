<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Service — {{ config('app.name', 'AI Hub') }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
    <div class="mx-auto max-w-3xl px-6 py-16">
        <a href="{{ route('home') }}" class="text-sm text-zinc-400 hover:text-zinc-200 transition-colors">&larr; Back to home</a>
        <h1 class="mt-4 text-3xl font-bold tracking-tight">Terms of Service</h1>
        <p class="mt-2 text-sm text-zinc-500">Last updated: {{ now()->format('F j, Y') }}</p>

        <div class="prose prose-invert mt-8 max-w-none space-y-6 text-zinc-300">
            <section>
                <h2 class="text-xl font-semibold text-zinc-100">1. Acceptance of Terms</h2>
                <p class="mt-2">By accessing or using {{ config('app.name', 'AI Hub') }} ("the Service"), you agree to be bound by these Terms of Service ("Terms"). If you do not agree, do not use the Service.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">2. Description of Service</h2>
                <p class="mt-2">The Service provides a unified API gateway that routes requests to large language model providers. We handle authentication, billing, usage tracking, and rate limiting on your behalf.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">3. Acceptable Use</h2>
                <p class="mt-2">You agree NOT to use the Service to:</p>
                <ul class="mt-2 list-disc pl-6 space-y-1">
                    <li>Generate, distribute, or store illegal, harmful, or abusive content</li>
                    <li>Create content that exploits or harms minors</li>
                    <li>Develop weapons, explosives, or controlled substances</li>
                    <li>Spread misinformation, spam, or phishing content</li>
                    <li>Violate intellectual property rights of others</li>
                    <li>Attempt to overwhelm, reverse-engineer, or attack the Service infrastructure</li>
                    <li>Resell or redistribute API access without authorization</li>
                </ul>
                <p class="mt-2">Violations may result in immediate account suspension.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">4. Billing and Payment</h2>
                <p class="mt-2">The Service is billed as a recurring monthly subscription via Stripe. Each plan grants a fixed token quota; usage beyond your plan's limits is rejected until you upgrade or your quota resets. Subscriptions renew automatically each billing cycle until cancelled.</p>
                <p class="mt-2">Failed or overdue payments may result in service suspension. Refunds are processed according to our refund policy and applicable law.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">5. API Keys and Security</h2>
                <p class="mt-2">You are responsible for safeguarding your API keys. Keep them confidential and use IP allow-lists where possible. You are liable for all usage under your keys until you revoke them. Report compromised keys immediately.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">6. Service Availability</h2>
                <p class="mt-2">We strive for high availability but do not guarantee uninterrupted service. Upstream provider outages, circuit breaker activations, and maintenance windows may cause temporary unavailability. We are not liable for losses resulting from service downtime.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">7. Data Retention</h2>
                <p class="mt-2">Request logs are retained for 30 days and then automatically deleted. Usage ledgers and billing records are retained indefinitely for accounting purposes. You may export your data at any time via the dashboard.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">8. Limitation of Liability</h2>
                <p class="mt-2">The Service is provided "as is" without warranties of any kind. We are not liable for indirect, incidental, or consequential damages. Our total liability shall not exceed the amount you paid in the preceding 30 days.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">9. Changes to Terms</h2>
                <p class="mt-2">We may update these Terms from time to time. Material changes will be communicated via email or dashboard notification. Continued use after changes constitutes acceptance.</p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-zinc-100">10. Contact</h2>
                <p class="mt-2">For questions about these Terms, contact your account manager or the platform operator.</p>
            </section>
        </div>
    </div>
</body>
</html>
