<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Hub - Unified API Gateway for Large Language Models</title>
    <meta name="description" content="One API key, every LLM provider. AI Hub routes, balances, and monitors all your AI model calls through a single OpenAI-compatible endpoint.">

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse-glow {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.8; }
        }
        @keyframes typing {
            from { width: 0; }
            to { width: 100%; }
        }
        @keyframes blink-caret {
            50% { border-color: transparent; }
        }
        @keyframes slide-in-right {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .animate-gradient { animation: gradient-shift 8s ease infinite; background-size: 200% 200%; }
        .animate-float { animation: float 6s ease-in-out infinite; }
        .animate-fade-in-up { animation: fade-in-up 0.8s ease-out both; }
        .animate-fade-in-up-delay-1 { animation: fade-in-up 0.8s ease-out 0.2s both; }
        .animate-fade-in-up-delay-2 { animation: fade-in-up 0.8s ease-out 0.4s both; }
        .animate-fade-in-up-delay-3 { animation: fade-in-up 0.8s ease-out 0.6s both; }
        .animate-slide-in { animation: slide-in-right 0.8s ease-out 0.5s both; }
        .animate-pulse-glow { animation: pulse-glow 3s ease-in-out infinite; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.06); }
        .glass-hover:hover { background: rgba(255, 255, 255, 0.06); border-color: rgba(255, 255, 255, 0.12); }
        .gradient-text { background: linear-gradient(135deg, #a78bfa, #818cf8, #6366f1, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; background-size: 200% 200%; animation: gradient-shift 4s ease infinite; }
        .gradient-border { position: relative; }
        .gradient-border::before { content: ''; position: absolute; inset: 0; border-radius: inherit; padding: 1px; background: linear-gradient(135deg, rgba(167,139,250,0.3), rgba(99,102,241,0.1), rgba(167,139,250,0.3)); -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite: xor; mask-composite: exclude; pointer-events: none; }
        .code-window { background: #0d1117; border: 1px solid rgba(255,255,255,0.08); }
        .code-dots span { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
        .hero-glow { position: absolute; width: 600px; height: 600px; border-radius: 50%; filter: blur(120px); opacity: 0.15; pointer-events: none; }
        .feature-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .pricing-popular { border: 1px solid rgba(167,139,250,0.4); background: linear-gradient(to bottom, rgba(167,139,250,0.08), transparent); }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-zinc-950 text-zinc-100 antialiased overflow-x-hidden">

    {{-- Navigation --}}
    <nav class="fixed top-0 inset-x-0 z-50 border-b border-white/5 bg-zinc-950/80 backdrop-blur-xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="/" class="flex items-center gap-3 group">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-violet-500/25 group-hover:shadow-violet-500/40 transition-shadow">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </div>
                    <span class="text-lg font-semibold tracking-tight">AI Hub</span>
                </a>

                <div class="hidden md:flex items-center gap-8 text-sm text-zinc-400">
                    <a href="#features" class="hover:text-white transition-colors">Features</a>
                    <a href="#how-it-works" class="hover:text-white transition-colors">How It Works</a>
                    <a href="#pricing" class="hover:text-white transition-colors">Pricing</a>
                </div>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-500 rounded-lg transition-colors shadow-lg shadow-violet-600/25">
                            Dashboard
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 text-sm text-zinc-300 hover:text-white transition-colors">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-violet-600 hover:bg-violet-500 rounded-lg transition-colors shadow-lg shadow-violet-600/25">
                                Get Started
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    {{-- Hero Section --}}
    <section class="relative min-h-screen flex items-center pt-16 overflow-hidden">
        {{-- Background glows --}}
        <div class="hero-glow bg-violet-600 top-1/4 -left-48 animate-pulse-glow" style="animation-delay: 0s;"></div>
        <div class="hero-glow bg-indigo-600 top-1/3 right-0 animate-pulse-glow" style="animation-delay: 1.5s;"></div>
        <div class="hero-glow bg-purple-700 bottom-0 left-1/3 animate-pulse-glow" style="animation-delay: 3s;"></div>

        {{-- Grid pattern overlay --}}
        <div class="absolute inset-0 opacity-[0.03]" style="background-image: url('data:image/svg+xml,%3Csvg width=%2260%22 height=%2260%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Crect width=%2260%22 height=%2260%22 fill=%22none%22 stroke=%22white%22 stroke-width=%220.5%22/%3E%3C/svg%3E'); background-size: 60px 60px;"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-32">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                {{-- Left: Copy --}}
                <div class="text-center lg:text-left">
                    <div class="animate-fade-in-up inline-flex items-center gap-2 px-3 py-1.5 rounded-full glass text-xs text-violet-300 mb-6">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        OpenAI-compatible API &middot; All major LLM providers
                    </div>

                    <h1 class="animate-fade-in-up text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.1]">
                        One API to
                        <span class="gradient-text">rule them all</span>
                    </h1>

                    <p class="animate-fade-in-up-delay-1 mt-6 text-lg text-zinc-400 leading-relaxed max-w-xl mx-auto lg:mx-0">
                        AI Hub is a unified gateway that routes, load-balances, and monitors your LLM API calls.
                        One endpoint, one API key, every model from OpenAI, Anthropic, Google, and more.
                    </p>

                    <div class="animate-fade-in-up-delay-2 mt-10 flex flex-col sm:flex-row items-center gap-4 justify-center lg:justify-start">
                        @guest
                            <a href="{{ route('register') }}" class="group inline-flex items-center gap-2 px-6 py-3 text-base font-medium text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 rounded-xl transition-all shadow-xl shadow-violet-600/25 hover:shadow-violet-500/40">
                                Start for Free
                                <svg class="w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                            </a>
                        @endguest
                        <a href="#how-it-works" class="inline-flex items-center gap-2 px-6 py-3 text-base font-medium text-zinc-300 glass rounded-xl hover:bg-white/5 transition-all">
                            <svg class="w-5 h-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" /></svg>
                            See How It Works
                        </a>
                    </div>

                    <div class="animate-fade-in-up-delay-3 mt-10 flex items-center gap-6 justify-center lg:justify-start text-sm text-zinc-500">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            Free tier available
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            No credit card required
                        </div>
                        <div class="hidden sm:flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                            OpenAI compatible
                        </div>
                    </div>
                </div>

                {{-- Right: Code Example --}}
                <div class="animate-slide-in">
                    <div class="code-window rounded-2xl shadow-2xl shadow-black/50 overflow-hidden">
                        {{-- Window chrome --}}
                        <div class="flex items-center gap-2 px-4 py-3 border-b border-white/5 bg-white/[0.02]">
                            <div class="code-dots flex gap-2">
                                <span class="bg-red-500/80"></span>
                                <span class="bg-yellow-500/80"></span>
                                <span class="bg-green-500/80"></span>
                            </div>
                            <div class="flex-1 text-center">
                                <span class="text-xs text-zinc-500 font-mono">chat_completion.py</span>
                            </div>
                        </div>
                        {{-- Code content --}}
                        <div class="p-5 font-mono text-sm leading-7 overflow-x-auto">
                            <div class="text-zinc-500"># Drop-in replacement for OpenAI SDK</div>
                            <div>
                                <span class="text-violet-400">from</span> <span class="text-emerald-300">openai</span> <span class="text-violet-400">import</span> <span class="text-yellow-200">OpenAI</span>
                            </div>
                            <div class="mt-2">
                                <span class="text-zinc-400">client</span> <span class="text-zinc-500">=</span> <span class="text-yellow-200">OpenAI</span><span class="text-zinc-300">(</span>
                            </div>
                            <div class="pl-6">
                                <span class="text-zinc-400">base_url</span><span class="text-zinc-500">=</span><span class="text-emerald-300">"https://api.aihub.dev/v1"</span><span class="text-zinc-300">,</span>
                            </div>
                            <div class="pl-6">
                                <span class="text-zinc-400">api_key</span><span class="text-zinc-500">=</span><span class="text-emerald-300">"sk-hub-***"</span><span class="text-zinc-300">,</span>
                            </div>
                            <div><span class="text-zinc-300">)</span></div>
                            <div class="mt-3">
                                <span class="text-zinc-400">response</span> <span class="text-zinc-500">=</span> <span class="text-zinc-400">client</span><span class="text-zinc-300">.</span><span class="text-zinc-400">chat</span><span class="text-zinc-300">.</span><span class="text-zinc-400">completions</span><span class="text-zinc-300">.</span><span class="text-blue-300">create</span><span class="text-zinc-300">(</span>
                            </div>
                            <div class="pl-6">
                                <span class="text-zinc-400">model</span><span class="text-zinc-500">=</span><span class="text-emerald-300">"gpt-4o"</span><span class="text-zinc-300">,</span>
                            </div>
                            <div class="pl-6">
                                <span class="text-zinc-400">messages</span><span class="text-zinc-500">=</span><span class="text-zinc-300">[{</span>
                            </div>
                            <div class="pl-12">
                                <span class="text-emerald-300">"role"</span><span class="text-zinc-300">:</span> <span class="text-emerald-300">"user"</span><span class="text-zinc-300">,</span>
                            </div>
                            <div class="pl-12">
                                <span class="text-emerald-300">"content"</span><span class="text-zinc-300">:</span> <span class="text-emerald-300">"Hello, AI!"</span>
                            </div>
                            <div class="pl-6"><span class="text-zinc-300">}]</span></div>
                            <div><span class="text-zinc-300">)</span></div>
                            <div class="mt-3 text-zinc-500"># AI Hub auto-routes to the best provider</div>
                            <div class="text-zinc-500"># with failover, rate limiting & usage tracking</div>
                            <div class="mt-2">
                                <span class="text-violet-400">print</span><span class="text-zinc-300">(</span><span class="text-zinc-400">response</span><span class="text-zinc-300">.</span><span class="text-zinc-400">choices</span><span class="text-zinc-300">[</span><span class="text-orange-300">0</span><span class="text-zinc-300">].</span><span class="text-zinc-400">message</span><span class="text-zinc-300">.</span><span class="text-zinc-400">content</span><span class="text-zinc-300">)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Scroll indicator --}}
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce hidden lg:block">
            <svg class="w-6 h-6 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </div>
    </section>

    {{-- Features Section --}}
    <section id="features" class="relative py-24 lg:py-32">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass text-xs text-violet-300 mb-4">
                    Platform Features
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">
                    Everything you need to
                    <span class="gradient-text">ship AI faster</span>
                </h2>
                <p class="mt-4 text-zinc-400 text-lg">
                    Stop juggling multiple API keys, SDKs, and billing dashboards. AI Hub handles the complexity so you can focus on building.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                {{-- Feature 1 --}}
                <div class="glass rounded-2xl p-6 glass-hover transition-all duration-300 group">
                    <div class="feature-icon bg-violet-500/10 text-violet-400 mb-4">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 group-hover:text-violet-300 transition-colors">Unified API Gateway</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">One OpenAI-compatible endpoint for all providers. Switch models with a single parameter change. Zero SDK lock-in.</p>
                </div>

                {{-- Feature 2 --}}
                <div class="glass rounded-2xl p-6 glass-hover transition-all duration-300 group">
                    <div class="feature-icon bg-blue-500/10 text-blue-400 mb-4">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 group-hover:text-blue-300 transition-colors">Smart Failover</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Automatic provider failover and load balancing. If one provider goes down, requests route to the next available one instantly.</p>
                </div>

                {{-- Feature 3 --}}
                <div class="glass rounded-2xl p-6 glass-hover transition-all duration-300 group">
                    <div class="feature-icon bg-emerald-500/10 text-emerald-400 mb-4">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 group-hover:text-emerald-300 transition-colors">Real-time Analytics</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Track tokens, costs, latency, and error rates per model and API key. Beautiful dashboards included.</p>
                </div>

                {{-- Feature 4 --}}
                <div class="glass rounded-2xl p-6 glass-hover transition-all duration-300 group">
                    <div class="feature-icon bg-amber-500/10 text-amber-400 mb-4">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 group-hover:text-amber-300 transition-colors">API Key Management</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Generate, rotate, and revoke API keys instantly. Scope keys to specific models and providers with fine-grained permissions.</p>
                </div>

                {{-- Feature 5 --}}
                <div class="glass rounded-2xl p-6 glass-hover transition-all duration-300 group">
                    <div class="feature-icon bg-rose-500/10 text-rose-400 mb-4">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 group-hover:text-rose-300 transition-colors">Usage Analytics</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Monitor token usage, track costs, and analyze model performance. Real-time dashboards with detailed insights.</p>
                </div>

                {{-- Feature 6 --}}
                <div class="glass rounded-2xl p-6 glass-hover transition-all duration-300 group">
                    <div class="feature-icon bg-cyan-500/10 text-cyan-400 mb-4">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2 group-hover:text-cyan-300 transition-colors">Quota & Cost Control</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Set token quotas and get alerts before you overspend. Predictable monthly subscription billing.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- How It Works --}}
    <section id="how-it-works" class="relative py-24 lg:py-32 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass text-xs text-violet-300 mb-4">
                    How It Works
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">
                    Up and running in
                    <span class="gradient-text">3 minutes</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                {{-- Step 1 --}}
                <div class="relative text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-500/20 to-violet-600/10 border border-violet-500/20 flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold gradient-text">1</span>
                    </div>
                    <h3 class="text-lg font-semibold mb-3">Create an Account</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Sign up for free. Generate your first API key in seconds.</p>
                    {{-- Connector line (hidden on mobile) --}}
                    <div class="hidden md:block absolute top-8 left-[60%] w-[80%] border-t border-dashed border-white/10"></div>
                </div>

                {{-- Step 2 --}}
                <div class="relative text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-500/20 to-indigo-600/10 border border-indigo-500/20 flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold gradient-text">2</span>
                    </div>
                    <h3 class="text-lg font-semibold mb-3">Point Your SDK</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">Change your OpenAI base URL to our endpoint. Works with any OpenAI-compatible SDK or library.</p>
                    <div class="hidden md:block absolute top-8 left-[60%] w-[80%] border-t border-dashed border-white/10"></div>
                </div>

                {{-- Step 3 --}}
                <div class="text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-500/20 to-purple-600/10 border border-purple-500/20 flex items-center justify-center mx-auto mb-6">
                        <span class="text-2xl font-bold gradient-text">3</span>
                    </div>
                    <h3 class="text-lg font-semibold mb-3">Ship & Scale</h3>
                    <p class="text-zinc-400 text-sm leading-relaxed">We handle routing, failover, and billing. You focus on building amazing AI products.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Supported Providers --}}
    <section class="relative py-24 lg:py-32 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <p class="text-sm text-zinc-500 uppercase tracking-widest font-medium">Works with every major LLM provider</p>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-6 lg:gap-10">
                @php
                    $providers = [
                        'OpenAI' => 'from-emerald-500/10 to-emerald-600/5 border-emerald-500/20 text-emerald-400',
                        'Anthropic' => 'from-orange-500/10 to-orange-600/5 border-orange-500/20 text-orange-400',
                        'Google AI' => 'from-blue-500/10 to-blue-600/5 border-blue-500/20 text-blue-400',
                        'Mistral' => 'from-violet-500/10 to-violet-600/5 border-violet-500/20 text-violet-400',
                        'Cohere' => 'from-pink-500/10 to-pink-600/5 border-pink-500/20 text-pink-400',
                        'DeepSeek' => 'from-cyan-500/10 to-cyan-600/5 border-cyan-500/20 text-cyan-400',
                        'Meta AI' => 'from-indigo-500/10 to-indigo-600/5 border-indigo-500/20 text-indigo-400',
                        'xAI' => 'from-zinc-500/10 to-zinc-600/5 border-zinc-500/20 text-zinc-400',
                    ];
                @endphp
                @foreach($providers as $name => $classes)
                    <div class="px-5 py-3 rounded-xl bg-gradient-to-br {{ $classes }} border text-sm font-medium tracking-wide">
                        {{ $name }}
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Pricing Section --}}
    <section id="pricing" class="relative py-24 lg:py-32 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full glass text-xs text-violet-300 mb-4">
                    Simple Pricing
                </div>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">
                    Start free,
                    <span class="gradient-text">scale as you grow</span>
                </h2>
                <p class="mt-4 text-zinc-400 text-lg">No hidden fees. No surprise bills. Pay only for what you use.</p>
            </div>

            @php
                $plans = config('services.billing.plans', []);
            @endphp

            <div class="grid md:grid-cols-3 gap-6 lg:gap-8 max-w-5xl mx-auto">
                @foreach($plans as $code => $plan)
                    @php
                        $isPopular = $code === 'pro';
                        $price = $plan['monthly_price_cents'] / 100;
                    @endphp
                    <div class="rounded-2xl p-8 {{ $isPopular ? 'pricing-popular relative' : 'glass' }} transition-all duration-300 hover:-translate-y-1">
                        @if($isPopular)
                            <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-violet-600 text-white text-xs font-medium rounded-full shadow-lg shadow-violet-600/25">
                                Most Popular
                            </div>
                        @endif

                        <div class="mb-6">
                            <h3 class="text-xl font-semibold">{{ $plan['name'] }}</h3>
                            <p class="text-sm text-zinc-400 mt-1">{{ $plan['description'] }}</p>
                        </div>

                        <div class="mb-8">
                            @if($price > 0)
                                <span class="text-4xl font-bold">${{ number_format($price) }}</span>
                                <span class="text-zinc-400 text-sm">/month</span>
                            @else
                                <span class="text-4xl font-bold">Free</span>
                            @endif
                        </div>

                        <ul class="space-y-3 mb-8">
                            @foreach($plan['features'] as $feature)
                                <li class="flex items-start gap-3 text-sm">
                                    <svg class="w-5 h-5 text-violet-400 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                    <span class="text-zinc-300">{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @guest
                            <a href="{{ route('register') }}" class="block w-full text-center px-4 py-3 rounded-xl text-sm font-medium transition-all {{ $isPopular ? 'bg-violet-600 hover:bg-violet-500 text-white shadow-lg shadow-violet-600/25' : 'glass text-zinc-200 hover:bg-white/5' }}">
                                {{ $code === 'free' ? 'Get Started Free' : 'Start with ' . $plan['name'] }}
                            </a>
                        @else
                            <a href="{{ route('billing.index') }}" class="block w-full text-center px-4 py-3 rounded-xl text-sm font-medium transition-all {{ $isPopular ? 'bg-violet-600 hover:bg-violet-500 text-white shadow-lg shadow-violet-600/25' : 'glass text-zinc-200 hover:bg-white/5' }}">
                                View Plans
                            </a>
                        @endguest
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Stats / Social Proof --}}
    <section class="relative py-24 border-t border-white/5">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="text-3xl lg:text-4xl font-bold gradient-text">8+</div>
                    <div class="text-sm text-zinc-400 mt-2">LLM Providers</div>
                </div>
                <div>
                    <div class="text-3xl lg:text-4xl font-bold gradient-text">99.9%</div>
                    <div class="text-sm text-zinc-400 mt-2">Uptime SLA</div>
                </div>
                <div>
                    <div class="text-3xl lg:text-4xl font-bold gradient-text">&lt;50ms</div>
                    <div class="text-sm text-zinc-400 mt-2">Routing Latency</div>
                </div>
                <div>
                    <div class="text-3xl lg:text-4xl font-bold gradient-text">OpenAI</div>
                    <div class="text-sm text-zinc-400 mt-2">Compatible API</div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA Section --}}
    <section class="relative py-24 lg:py-32 border-t border-white/5 overflow-hidden">
        <div class="hero-glow bg-violet-600 top-0 left-1/2 -translate-x-1/2 animate-pulse-glow"></div>

        <div class="relative max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight">
                Ready to unify your
                <span class="gradient-text">AI infrastructure?</span>
            </h2>
            <p class="mt-6 text-lg text-zinc-400 max-w-2xl mx-auto">
                Join developers who use AI Hub to simplify their LLM operations. Get started in minutes with our free tier.
            </p>
            <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                @guest
                    <a href="{{ route('register') }}" class="group inline-flex items-center gap-2 px-8 py-4 text-lg font-medium text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 rounded-xl transition-all shadow-xl shadow-violet-600/25 hover:shadow-violet-500/40">
                        Create Free Account
                        <svg class="w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="group inline-flex items-center gap-2 px-8 py-4 text-lg font-medium text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 rounded-xl transition-all shadow-xl shadow-violet-600/25 hover:shadow-violet-500/40">
                        Go to Dashboard
                        <svg class="w-5 h-5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                    </a>
                @endguest
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-white/5 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-violet-500 to-indigo-600 flex items-center justify-center">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-zinc-400">AI Hub</span>
                </div>

                <div class="flex items-center gap-6 text-sm text-zinc-500">
                    <a href="#features" class="hover:text-zinc-300 transition-colors">Features</a>
                    <a href="#pricing" class="hover:text-zinc-300 transition-colors">Pricing</a>
                    <a href="#how-it-works" class="hover:text-zinc-300 transition-colors">How It Works</a>
                    @auth
                        <a href="{{ route('dashboard') }}" class="hover:text-zinc-300 transition-colors">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-zinc-300 transition-colors">Login</a>
                    @endauth
                </div>

                <div class="text-sm text-zinc-600">
                    &copy; {{ date('Y') }} AI Hub. All rights reserved.
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
