<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ChatAssistant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * End-to-end check of the AI concierge, with a plain-English diagnosis when the
 * provider rejects the call.
 *
 *   php artisan aroma:test-ai
 *   php artisan aroma:test-ai "Which perfume suits a gift?"
 */
class TestAiCommand extends Command
{
    // NB: no default in the signature — a comma there breaks the parser.
    protected $signature = 'aroma:test-ai
                            {message? : Message to send to the concierge}
                            {--models : List the models this key can actually use}';

    protected $description = 'Send one message to the AI concierge and report exactly what happened';

    public function handle(ChatAssistant $assistant): int
    {
        $key = (string) config('services.gemini.api_key');

        $this->line('Model   : '.config('services.gemini.model'));
        // Keys are never judged by their prefix — AI Studio issues both legacy
        // "AIza…" keys and newer "AQ…" auth keys, and both are valid. The only
        // meaningful test is a real API call, which is what this command does.
        $this->line('Key     : '.($key === '' ? 'NOT SET' : 'set ('.strlen($key).' chars)'));

        if (! $assistant->isConfigured()) {
            $this->error('GEMINI_API_KEY is not set. Add it to .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        if ($this->option('models')) {
            return $this->listModels($key);
        }

        $message = (string) ($this->argument('message') ?: 'Hello, what do you sell?');

        $this->line('Sending : '.$message);
        $this->newLine();

        $result = $assistant->reply($message);

        if ($result['ok']) {
            $this->info('✓ Working. Reply:');
            $this->line($result['reply']);

            return self::SUCCESS;
        }

        $this->error('✗ Failed ('.($result['error'] ?? 'unknown').')');
        $this->newLine();
        $this->line($this->diagnose((string) ($result['error'] ?? '')));

        return self::FAILURE;
    }

    /** Ask Google which models this key may call — the definitive auth check. */
    private function listModels(string $key): int
    {
        $base = rtrim((string) config('services.gemini.base_url'), '/');

        $response = Http::withHeaders(['x-goog-api-key' => $key])
            ->acceptJson()->timeout(30)->get($base.'/models');

        if (! $response->successful()) {
            $this->error('Could not list models (HTTP '.$response->status().').');
            $this->line((string) data_get($response->json(), 'error.message', ''));

            return self::FAILURE;
        }

        $this->info('✓ Key authenticated. Models supporting generateContent:');

        foreach ((array) data_get($response->json(), 'models', []) as $model) {
            if (in_array('generateContent', (array) data_get($model, 'supportedGenerationMethods', []), true)) {
                $this->line('  '.str_replace('models/', '', (string) data_get($model, 'name')));
            }
        }

        $this->newLine();
        $this->line('Set the one you want with GEMINI_MODEL in .env, then: php artisan config:clear');

        return self::SUCCESS;
    }

    private function diagnose(string $code): string
    {
        switch (true) {
            case $code === 'rate_limited':
                return <<<TXT
Google returned 429 (quota exhausted) — note this means your key AUTHENTICATED
fine; the project simply has no quota for this particular model.

  1. See what you can actually call:  php artisan aroma:test-ai --models
  2. Point GEMINI_MODEL at one of those (the "…-latest" aliases track whatever
     your project has quota for), then: php artisan config:clear
  3. If everything is exhausted, enable billing on the Google Cloud project.
TXT;
            case $code === 'max_tokens':
                return "The model spent its whole budget reasoning before writing a reply.\n"
                    ."Either raise GEMINI_MAX_TOKENS (try 1200) or use a lite model,\n"
                    .'e.g. GEMINI_MODEL=gemini-flash-lite-latest';
            case $code === 'http_403':
                return "403 — the key was rejected, or the Generative Language API isn't enabled\n"
                    .'for its project. Enable it in Google Cloud, or issue a new key in AI Studio.';
            case $code === 'http_404':
                return "404 — that model name isn't available to this key.\n"
                    .'Run: php artisan aroma:test-ai --models  to see valid names.';
            case $code === 'http_400':
                return '400 — malformed request or an unusable key. Re-check GEMINI_API_KEY.';
            case $code === 'empty_candidate':
                return 'The model returned nothing — usually a safety filter. Try a different message.';
            default:
                return 'Check storage/logs/laravel.log for the full response from Google.';
        }
    }
}
