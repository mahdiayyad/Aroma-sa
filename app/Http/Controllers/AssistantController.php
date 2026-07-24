<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ChatAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront AI concierge endpoints. Conversation history lives in the session
 * so it follows the shopper across pages without an account, and is capped to
 * keep prompts (and cost) bounded.
 */
class AssistantController extends Controller
{
    private const SESSION_KEY = 'assistant.history';
    private const MAX_TURNS = 12; // 6 exchanges

    public function chat(Request $request, ChatAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'message'         => ['required', 'string', 'max:1000'],
            'context'         => ['nullable', 'array'],
            'context.title'   => ['nullable', 'string', 'max:200'],
            'context.path'    => ['nullable', 'string', 'max:200'],
            'context.product' => ['nullable', 'string', 'max:200'],
        ]);

        $message = trim($data['message']);
        $history = $this->history();

        $result = $assistant->reply($message, $history, $data['context'] ?? []);

        // Only remember exchanges that actually succeeded, so a transient error
        // never poisons the thread.
        if ($result['ok']) {
            $history[] = ['role' => 'user', 'text' => $message];
            $history[] = ['role' => 'assistant', 'text' => $result['reply']];
            $this->remember($history);
        }

        return response()->json([
            'ok'    => $result['ok'],
            'reply' => $result['reply'],
        ], $result['ok'] ? 200 : 503);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['ok' => true]);
    }

    /** @return array<int,array{role:string,text:string}> */
    private function history(): array
    {
        $history = session(self::SESSION_KEY, []);

        return is_array($history) ? $history : [];
    }

    /** @param array<int,array{role:string,text:string}> $history */
    private function remember(array $history): void
    {
        session([self::SESSION_KEY => array_slice($history, -self::MAX_TURNS)]);
    }
}
