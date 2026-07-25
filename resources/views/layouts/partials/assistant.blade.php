{{-- AI concierge: floating chat widget + scroll-revealed WhatsApp button.
     Conversation history is server-side (session) so it follows the shopper
     across pages; it is re-rendered here on load. --}}
@php
    $waDigits = preg_replace('/\D+/', '', (string) config('aroma.contact.whatsapp'));
    $hasWhatsApp = strlen((string) $waDigits) >= 9;
    $waUrl = $hasWhatsApp
        ? 'https://wa.me/'.$waDigits.'?text='.rawurlencode(__('assistant.whatsapp_prefill'))
        : null;

    $assistantHistory = collect(session('assistant.history', []))
        ->filter(fn ($t) => is_array($t) && ! empty($t['text']))
        ->values();

    $assistantProduct = (isset($product) && $product instanceof \App\Models\Product) ? (string) $product->name : '';

    // Quick-action deep links. Customer-service / returns prefer WhatsApp, then
    // fall back to email, so the actions are always useful.
    $contactEmail = (string) config('aroma.contact.email');
    $shopUrl      = route('home', app()->getLocale());
    $serviceUrl   = $waUrl ?: ($contactEmail ? 'mailto:'.$contactEmail : null);
    $returnsUrl   = $hasWhatsApp
        ? 'https://wa.me/'.$waDigits.'?text='.rawurlencode(__('assistant.menu.returns_prefill'))
        : ($contactEmail ? 'mailto:'.$contactEmail.'?subject='.rawurlencode(__('assistant.menu.returns')) : null);
    $rtlChat = ($direction ?? 'ltr') === 'rtl';
@endphp

<div class="aroma-assistant" id="aromaAssistant"
     data-endpoint="{{ route('assistant.chat') }}"
     data-reset="{{ route('assistant.reset') }}"
     data-page-title="{{ trim($__env->yieldContent('title')) ?: config('aroma.brand.name') }}"
     data-page-path="{{ '/'.request()->path() }}"
     data-product="{{ $assistantProduct }}"
     data-error="{{ __('assistant.error') }}">

    {{-- Floating actions. WhatsApp sits above the chat launcher and only
         appears once the shopper has scrolled, so it never competes on load. --}}
    <div class="aroma-fabs">
        @if ($hasWhatsApp)
            <a class="aroma-fab aroma-fab-wa" id="aromaWhatsApp" href="{{ $waUrl }}"
               target="_blank" rel="noopener" aria-label="{{ __('assistant.whatsapp') }}">
                <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">
                    <path fill="currentColor" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.09c-.24.68-1.2 1.26-1.96 1.42-.52.11-1.2.2-3.5-.75-2.94-1.22-4.83-4.2-4.98-4.4-.14-.19-1.19-1.58-1.19-3.02s.75-2.14 1.02-2.43c.27-.29.58-.36.78-.36.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.82 2.02.89 2.17.07.14.12.31.02.5-.09.19-.14.31-.28.48-.14.17-.29.37-.42.5-.14.14-.28.29-.12.57.16.29.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.27.14.43.12.59-.07.16-.19.68-.79.86-1.07.18-.27.36-.22.6-.13.24.09 1.53.72 1.79.85.26.14.44.2.5.31.06.11.06.65-.18 1.33Z"/>
                </svg>
                <span class="aroma-fab-tip">{{ __('assistant.whatsapp') }}</span>
            </a>
        @endif

        <button type="button" class="aroma-fab aroma-fab-chat" id="aromaChatToggle"
                aria-expanded="false" aria-controls="aromaChatPanel" aria-label="{{ __('assistant.open') }}">
            <i class="bi bi-chat-dots aroma-fab-icon-open" aria-hidden="true"></i>
            <i class="bi bi-x-lg aroma-fab-icon-close" aria-hidden="true"></i>
            <span class="aroma-fab-tip">{{ __('assistant.open') }}</span>
        </button>
    </div>

    {{-- Conversation panel --}}
    <section class="aroma-chat" id="aromaChatPanel" role="dialog"
             aria-labelledby="aromaChatTitle" aria-describedby="aromaChatIntro" hidden>
        <header class="aroma-chat-head">
            <span class="aroma-chat-avatar" aria-hidden="true">A</span>
            <span class="aroma-chat-id">
                <span class="aroma-chat-name" id="aromaChatTitle">{{ __('assistant.name') }}</span>
                <span class="aroma-chat-status"><i class="aroma-dot" aria-hidden="true"></i>{{ __('assistant.status') }}</span>
            </span>
            <button type="button" class="aroma-chat-icon" id="aromaChatReset" title="{{ __('assistant.reset') }}" aria-label="{{ __('assistant.reset') }}">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            </button>
            <button type="button" class="aroma-chat-icon" id="aromaChatClose" title="{{ __('assistant.close') }}" aria-label="{{ __('assistant.close') }}">
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </button>
        </header>

        <div class="aroma-chat-log" id="aromaChatLog" role="log" aria-live="polite" aria-relevant="additions">
            <div class="aroma-msg aroma-msg-bot aroma-chat-greeting">
                <p class="aroma-chat-greet-title">{{ __('assistant.title') }}</p>
                <p id="aromaChatIntro" class="mb-0">{{ __('assistant.intro') }}</p>
            </div>

            {{-- Quick actions: deep links (not AI turns). Preserved on reset. --}}
            <div class="aroma-quick-actions" role="group" aria-label="{{ __('assistant.menu.title') }}">
                @php($chev = 'bi-chevron-'.($rtlChat ? 'left' : 'right'))

                <a class="aroma-quick-action" href="{{ $shopUrl }}">
                    <span class="aroma-qa-icon"><i class="bi bi-bag" aria-hidden="true"></i></span>
                    <span class="aroma-qa-text"><strong>{{ __('assistant.menu.products') }}</strong><span>{{ __('assistant.menu.products_desc') }}</span></span>
                    <i class="bi {{ $chev }} aroma-qa-chev" aria-hidden="true"></i>
                </a>

                @if ($serviceUrl)
                    <a class="aroma-quick-action" href="{{ $serviceUrl }}" @if($hasWhatsApp) target="_blank" rel="noopener" @endif>
                        <span class="aroma-qa-icon aroma-qa-icon-wa"><i class="bi bi-whatsapp" aria-hidden="true"></i></span>
                        <span class="aroma-qa-text"><strong>{{ __('assistant.menu.service') }}</strong><span>{{ __('assistant.menu.service_desc') }}</span></span>
                        <i class="bi {{ $chev }} aroma-qa-chev" aria-hidden="true"></i>
                    </a>
                @endif

                @if ($returnsUrl)
                    <a class="aroma-quick-action" href="{{ $returnsUrl }}" @if($hasWhatsApp) target="_blank" rel="noopener" @endif>
                        <span class="aroma-qa-icon"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></span>
                        <span class="aroma-qa-text"><strong>{{ __('assistant.menu.returns') }}</strong><span>{{ __('assistant.menu.returns_desc') }}</span></span>
                        <i class="bi {{ $chev }} aroma-qa-chev" aria-hidden="true"></i>
                    </a>
                @endif
            </div>

            @foreach ($assistantHistory as $turn)
                <div class="aroma-msg aroma-msg-{{ ($turn['role'] ?? 'user') === 'assistant' ? 'bot' : 'me' }}">{{ $turn['text'] }}</div>
            @endforeach
        </div>

        <div class="aroma-chat-suggestions" id="aromaSuggestions" @if ($assistantHistory->isNotEmpty()) hidden @endif>
            @foreach ((array) __('assistant.suggestions') as $suggestion)
                <button type="button" class="aroma-chip" data-suggestion>{{ $suggestion }}</button>
            @endforeach
        </div>

        <form class="aroma-chat-form" id="aromaChatForm" autocomplete="off">
            <label class="visually-hidden" for="aromaChatInput">{{ __('assistant.placeholder') }}</label>
            <textarea id="aromaChatInput" rows="1" maxlength="1000"
                      placeholder="{{ __('assistant.placeholder') }}" required></textarea>
            <button type="submit" class="aroma-chat-send" aria-label="{{ __('assistant.send') }}">
                <i class="bi bi-send-fill" aria-hidden="true"></i>
            </button>
        </form>

        <p class="aroma-chat-disclaimer">
            {{ __('assistant.disclaimer') }}
            @if ($hasWhatsApp)
                <a href="{{ $waUrl }}" target="_blank" rel="noopener">{{ __('assistant.whatsapp') }}</a>
            @endif
        </p>
    </section>
</div>
