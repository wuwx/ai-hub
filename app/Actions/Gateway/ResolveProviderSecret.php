<?php

namespace App\Actions\Gateway;

use Illuminate\Support\Str;

class ResolveProviderSecret
{
    public function handle(?string $secretRef): ?string
    {
        if (! $secretRef) {
            return null;
        }

        // secret://KEY — recommended syntax. Resolves via the cached secrets
        // map in config/services.php so it works after `config:cache`.
        if (Str::startsWith($secretRef, 'secret://')) {
            return $this->resolveFromSecretsMap(Str::after($secretRef, 'secret://'));
        }

        // env://VAR and env:VAR — legacy syntax. Routed through the secrets map
        // because calling env() at runtime returns null once config is cached.
        // The env var must be registered in config('services.llm_gateway.secrets').
        if (Str::startsWith($secretRef, 'env://') || Str::startsWith($secretRef, 'env:')) {
            $key = Str::startsWith($secretRef, 'env://')
                ? Str::after($secretRef, 'env://')
                : Str::after($secretRef, 'env:');

            return $this->resolveFromSecretsMap($key);
        }

        if (Str::startsWith($secretRef, 'config://')) {
            return config(Str::after($secretRef, 'config://'));
        }

        if (Str::startsWith($secretRef, 'literal://')) {
            return Str::after($secretRef, 'literal://');
        }

        // Plain string — treated as a literal secret value.
        return $secretRef;
    }

    protected function resolveFromSecretsMap(string $key): ?string
    {
        return config('services.llm_gateway.secrets.'.$key);
    }
}
