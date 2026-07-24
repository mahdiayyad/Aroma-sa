<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * A conversational assistant for the storefront concierge widget.
 * Kept behind a contract so the provider can be swapped without touching the
 * controller or the front-end.
 */
interface ChatAssistant
{
    /** Is the assistant usable (credentials present)? */
    public function isConfigured(): bool;

    /**
     * Answer a shopper's message.
     *
     * @param  string                              $message  the shopper's question
     * @param  array<int,array{role:string,text:string}> $history  prior turns, oldest first
     * @param  array<string,mixed>                 $context  page context (url, title, product…)
     * @return array{ok:bool, reply:string, error?:string}
     */
    public function reply(string $message, array $history = [], array $context = []): array;
}
