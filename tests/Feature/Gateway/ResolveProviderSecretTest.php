<?php

use App\Actions\Gateway\ResolveProviderSecret;

it('resolves secret:// references from the cached secrets map', function () {
    config()->set('services.llm_gateway.secrets.OPENAI_API_KEY', 'sk-cached-value');

    $resolver = new ResolveProviderSecret;

    expect($resolver->handle('secret://OPENAI_API_KEY'))->toBe('sk-cached-value');
});

it('resolves env:// references through the secrets map for config-cache safety', function () {
    config()->set('services.llm_gateway.secrets.ANTHROPIC_API_KEY', 'sk-ant-cached');

    $resolver = new ResolveProviderSecret;

    expect($resolver->handle('env://ANTHROPIC_API_KEY'))->toBe('sk-ant-cached');
});

it('resolves legacy env: prefix through the secrets map', function () {
    config()->set('services.llm_gateway.secrets.GROQ_API_KEY', 'gsk-cached');

    $resolver = new ResolveProviderSecret;

    expect($resolver->handle('env:GROQ_API_KEY'))->toBe('gsk-cached');
});

it('returns null when the secret key is not registered in the secrets map', function () {
    config()->set('services.llm_gateway.secrets', []);

    $resolver = new ResolveProviderSecret;

    expect($resolver->handle('secret://UNREGISTERED_KEY'))->toBeNull();
});

it('returns null for null or empty secret references', function () {
    $resolver = new ResolveProviderSecret;

    expect($resolver->handle(null))->toBeNull();
    expect($resolver->handle(''))->toBeNull();
});

it('resolves config:// references via the config helper', function () {
    config()->set('custom.secret.path', 'config-derived-value');

    $resolver = new ResolveProviderSecret;

    expect($resolver->handle('config://custom.secret.path'))->toBe('config-derived-value');
});

it('returns the literal value for literal:// references', function () {
    $resolver = new ResolveProviderSecret;

    expect($resolver->handle('literal://sk-plain-literal'))->toBe('sk-plain-literal');
});

it('treats plain strings as literal secret values', function () {
    $resolver = new ResolveProviderSecret;

    expect($resolver->handle('sk-direct-key'))->toBe('sk-direct-key');
});
