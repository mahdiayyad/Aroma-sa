<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesAdminForms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GiftCardRequest;
use App\Models\GiftCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The single greeting-card catalogue used by both checkout's Gift Options
 * step and the cart gift studio (see ai-docs checkout refactor analysis).
 */
class GiftCardController extends Controller
{
    use HandlesAdminForms;

    public function index(): View
    {
        return view('admin.gift-cards.index', [
            'giftCards' => GiftCard::withCount('orders')->orderBy('sort_order')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.gift-cards.form', [
            'giftCard' => new GiftCard(['is_active' => true, 'sort_order' => 0]),
        ]);
    }

    public function store(GiftCardRequest $request): RedirectResponse
    {
        GiftCard::create($this->payload($request));

        return redirect()->route('admin.gift-cards.index')->with('status', __('admin.gift_cards.saved'));
    }

    public function edit(GiftCard $giftCard): View
    {
        return view('admin.gift-cards.form', ['giftCard' => $giftCard]);
    }

    public function update(GiftCardRequest $request, GiftCard $giftCard): RedirectResponse
    {
        $giftCard->update($this->payload($request, $giftCard));

        return redirect()->route('admin.gift-cards.index')->with('status', __('admin.gift_cards.saved'));
    }

    public function destroy(GiftCard $giftCard): RedirectResponse
    {
        // orders.greeting_card_id is nullOnDelete — deleting a design never
        // breaks a past order, it just loses the design reference.
        $giftCard->delete();

        return redirect()->route('admin.gift-cards.index')->with('status', __('admin.gift_cards.deleted'));
    }

    /** @return array<string,mixed> */
    private function payload(GiftCardRequest $request, ?GiftCard $giftCard = null): array
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug('gift_cards', $data['slug'] ?? null, $data['name']['en'], optional($giftCard)->id);

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeUpload($request->file('image'), 'gift-cards');
        } else {
            unset($data['image']);
        }

        return $data;
    }
}
