{{-- Add-to-cart confirmation modal. Rendered once per page and populated by
     aroma-ui.js from the JSON returned by POST /cart, so any add-to-cart form
     on the site opens it. --}}
<div class="aroma-cart-modal" id="aromaCartModal" role="dialog" aria-modal="true"
     aria-labelledby="aromaCartModalTitle" hidden
     data-cart-url="{{ route('cart.index') }}"
     data-update-url="{{ url('cart') }}"
     data-add-url="{{ route('cart.store') }}"
     data-i18n-add="{{ __('cart.modal.add') }}"
     data-i18n-options="{{ __('cart.modal.options') }}"
     data-i18n-remove="{{ __('cart.modal.remove') }}"
     data-i18n-qty="{{ __('cart.qty') }}">
    <div class="aroma-cart-modal-backdrop" data-close></div>

    <div class="aroma-cart-modal-panel" role="document">
        <header class="aroma-cart-modal-head">
            <h2 class="aroma-cart-modal-title" id="aromaCartModalTitle">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>{{ __('cart.modal.added') }}
            </h2>
            <button type="button" class="aroma-cart-modal-close" data-close aria-label="{{ __('cart.modal.close') }}">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <div class="aroma-cart-modal-body">
            {{-- The item that was just added --}}
            <div class="aroma-added-item" id="aromaAddedItem">
                <img src="" alt="" id="aromaAddedImg">
                <div class="aroma-added-meta">
                    <p class="aroma-added-name" id="aromaAddedName"></p>
                    <p class="aroma-added-variant" id="aromaAddedVariant" hidden></p>
                    <p class="aroma-added-qty" id="aromaAddedQty"></p>
                </div>
                <span class="aroma-added-price" id="aromaAddedTotal"></span>
            </div>

            {{-- Running cart total --}}
            <div class="aroma-cart-modal-total">
                <span>{{ __('cart.modal.total') }}</span>
                <strong id="aromaCartTotal"></strong>
            </div>

            {{-- اجعل هديتك مثالية --}}
            <section class="aroma-gift-suggest" id="aromaGiftSuggest" hidden>
                <h3 class="aroma-gift-suggest-title">{{ __('cart.modal.perfect') }}</h3>
                <p class="aroma-gift-suggest-hint">{{ __('cart.modal.perfect_hint') }}</p>
                <div class="aroma-suggest-strip" id="aromaSuggestStrip"></div>
            </section>
        </div>

        <footer class="aroma-cart-modal-foot">
            <button type="button" class="btn btn-aroma-outline" data-close>{{ __('cart.modal.continue') }}</button>
            <a href="{{ route('cart.index') }}" class="btn btn-aroma">
                <i class="bi bi-bag me-1" aria-hidden="true"></i>{{ __('cart.modal.show_cart') }}
            </a>
        </footer>
    </div>
</div>
