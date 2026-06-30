<?php

namespace App\Actions\Gateway;

/**
 * Lightweight keyword-based content filter.
 *
 * This is a first-line defense against obvious policy violations before
 * forwarding requests to upstream providers. It is NOT a replacement for
 * the upstream provider's own moderation systems — it catches the most
 * blatant cases to protect our API keys from being banned.
 *
 * The blocklist is configurable via config/services.php and can be
 * supplemented with per-team custom rules in the future.
 */
class ContentFilter
{
    /**
     * Default blocklist patterns. These are intentionally broad — they catch
     * the kind of content that would get an API key banned by upstream
     * providers immediately.
     */
    protected const DEFAULT_BLOCKED_PATTERNS = [
        // CSAM-related (zero tolerance)
        'child porn',
        'csam',
        'underage nude',
        // Weapons of mass destruction instructions
        'how to make a bomb',
        'how to build a nuclear weapon',
        'how to make methamphetamine',
        'how to synthesize fentanyl',
        // Self-harm instructions (detailed)
        'how to commit suicide',
        'how to kill yourself',
    ];

    /**
     * Check if the given text contains blocked content.
     *
     * @param  array<string, mixed>  $canonical
     * @return array{blocked: bool, reason: ?string}
     */
    public function check(array $canonical): array
    {
        $patterns = config('services.llm_gateway.content_filter_blocklist', self::DEFAULT_BLOCKED_PATTERNS);

        if (! is_array($patterns) || empty($patterns)) {
            return ['blocked' => false, 'reason' => null];
        }

        $text = $this->extractText($canonical);

        if ($text === '') {
            return ['blocked' => false, 'reason' => null];
        }

        $textLower = mb_strtolower($text);

        foreach ($patterns as $pattern) {
            $patternLower = mb_strtolower((string) $pattern);

            if (str_contains($textLower, $patternLower)) {
                return [
                    'blocked' => true,
                    'reason' => 'Request contains prohibited content.',
                ];
            }
        }

        return ['blocked' => false, 'reason' => null];
    }

    /**
     * Extract all user-facing text from the canonical request payload.
     *
     * @param  array<string, mixed>  $canonical
     */
    protected function extractText(array $canonical): string
    {
        $parts = [];

        $messages = $canonical['messages'] ?? [];

        if (is_array($messages)) {
            foreach ($messages as $message) {
                if (is_array($message) && isset($message['content']) && is_string($message['content'])) {
                    $parts[] = $message['content'];
                }
            }
        }

        $system = $canonical['system'] ?? null;

        if (is_string($system)) {
            $parts[] = $system;
        }

        $input = $canonical['input'] ?? null;

        if (is_string($input)) {
            $parts[] = $input;
        } elseif (is_array($input)) {
            foreach ($input as $item) {
                if (is_string($item)) {
                    $parts[] = $item;
                }
            }
        }

        return implode("\n", $parts);
    }
}
