<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Contracts\ChatAssistant;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini-backed shopping concierge.
 *
 * Uses the generateContent endpoint with a system instruction that carries the
 * Aroma brand voice (elegant, warm, never pushy), the store's facts, and strict
 * guardrails so it can't invent order details or prices.
 */
class GeminiAssistant implements ChatAssistant
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->apiKey      = (string) config('services.gemini.api_key', '');
        $this->model       = (string) config('services.gemini.model', 'gemini-2.0-flash');
        $this->baseUrl     = rtrim((string) config('services.gemini.base_url', ''), '/');
        $this->maxTokens   = (int) config('services.gemini.max_tokens', 500);
        $this->temperature = (float) config('services.gemini.temperature', 0.7);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function reply(string $message, array $history = [], array $context = []): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'reply' => __('assistant.offline'), 'error' => 'not_configured'];
        }

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->acceptJson()
                ->timeout(20)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent", [
                    'system_instruction' => ['parts' => [['text' => $this->systemPrompt($context)]]],
                    'contents'           => $this->buildContents($message, $history),
                    'generationConfig'   => [
                        'temperature'     => $this->temperature,
                        'maxOutputTokens' => $this->maxTokens,
                    ],
                    // Keep the concierge on-topic and safe for a retail audience.
                    'safetySettings' => [
                        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                    ],
                ]);

            // Quota/rate limit — a distinct, recoverable condition worth its own
            // message so shoppers are told to retry rather than to give up.
            if ($response->status() === 429) {
                Log::warning('Gemini quota exceeded', [
                    'retry_after' => data_get($response->json(), 'error.details.2.retryDelay'),
                    'message'     => data_get($response->json(), 'error.message'),
                ]);

                return ['ok' => false, 'reply' => __('assistant.busy'), 'error' => 'rate_limited'];
            }

            if (! $response->successful()) {
                Log::warning('Gemini request failed', ['status' => $response->status(), 'body' => $response->json()]);

                return ['ok' => false, 'reply' => __('assistant.error'), 'error' => 'http_'.$response->status()];
            }

            $data   = $response->json();
            $finish = (string) data_get($data, 'candidates.0.finishReason', '');
            $text   = trim((string) data_get($data, 'candidates.0.content.parts.0.text', ''));

            if ($text === '') {
                // MAX_TOKENS here means a reasoning model spent the whole budget
                // before emitting anything — a config problem, not a safety block.
                Log::warning('Gemini returned no text', [
                    'model'        => $this->model,
                    'finishReason' => $finish ?: 'unknown',
                    'maxTokens'    => $this->maxTokens,
                ]);

                return [
                    'ok'    => false,
                    'reply' => __('assistant.error'),
                    'error' => $finish === 'MAX_TOKENS' ? 'max_tokens' : 'empty_candidate',
                ];
            }

            return ['ok' => true, 'reply' => $text];
        } catch (Exception $e) {
            Log::error('Gemini assistant error', ['error' => $e->getMessage()]);

            return ['ok' => false, 'reply' => __('assistant.error'), 'error' => 'exception'];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function buildContents(string $message, array $history): array
    {
        $contents = [];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $text = trim((string) ($turn['text'] ?? ''));

            if ($text !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        return $contents;
    }

    /** @param array<string,mixed> $context */
    private function systemPrompt(array $context): string
    {
        $brand    = config('aroma.brand.name', 'Aroma');
        $tagline  = config('aroma.brand.tagline', 'Awaken your Senses');
        $currency = config('aroma.currency.code', 'SAR');
        $locale   = app()->getLocale() === 'ar' ? 'Arabic' : 'English';

        $page    = trim((string) ($context['title'] ?? ''));
        $path    = trim((string) ($context['path'] ?? ''));
        $product = trim((string) ($context['product'] ?? ''));

        $where = $page !== '' ? "The shopper is currently on: \"{$page}\" ({$path})." : '';
        if ($product !== '') {
            $where .= " They are viewing the product: \"{$product}\".";
        }

        return <<<PROMPT
You are the personal concierge for {$brand}, a premium Saudi boutique whose
tagline is "{$tagline}". {$brand} curates fine fragrances, floral arrangements,
abayas, beauty products and gifts.

VOICE
- Elegant, warm and understated — like a luxury boutique host, never a salesperson.
- Concise: 2–4 short sentences. No emoji. No exclamation marks. No hype.
- Reply in {$locale} unless the shopper writes in another language, then mirror theirs.

CONTEXT
{$where}

WHAT YOU CAN DO
- Help shoppers choose fragrances, gifts and products, and explain scent families.
- Explain shipping, packaging (every order includes a gift bag and thank-you card),
  returns, and the payment methods (Mada, Visa, Mastercard, Apple Pay). Prices are in {$currency}.
- Guide them through browsing, the cart and checkout.

STRICT RULES
- Never invent prices, stock levels, delivery dates, discount codes or order details.
  If you don't know, say so plainly and offer to connect them on WhatsApp.
- For anything about a specific existing order, refunds, or payment problems, do not
  speculate — invite them to continue on WhatsApp with a human colleague.
- Stay on topics related to {$brand}, its products and the shopping experience.
  Politely decline anything else in one sentence.
PROMPT;
    }
}
