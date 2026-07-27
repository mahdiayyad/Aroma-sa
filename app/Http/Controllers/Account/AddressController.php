<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Customer address book. Feeds the "saved addresses" picker in checkout
 * (CheckoutController::showAddressForm already reads $user->addresses()) —
 * before this controller existed, nothing ever wrote to that table.
 *
 * The billing/shipping `type` column is an implementation detail, not shown to
 * the shopper: every address is written as `shipping` since checkout doesn't
 * discriminate by type when listing, and a single "your addresses" list is the
 * simpler, Floward-style mental model.
 */
class AddressController extends Controller
{
    public function index(): View
    {
        return view('account.addresses.index', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('account.addresses.form', ['address' => new Address()]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->payload($request);

        if ($data['is_default'] || $user->addresses()->doesntExist()) {
            $this->clearDefault($user->id);
            $data['is_default'] = true;
        }

        $user->addresses()->create($data);

        return redirect()->route('account.addresses.index')->with('status', __('account.addresses.saved'));
    }

    public function edit(Address $address): View
    {
        $this->authorizeOwner($address);

        return view('account.addresses.form', ['address' => $address]);
    }

    public function update(AddressRequest $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($address);

        $data = $this->payload($request);

        if ($data['is_default']) {
            $this->clearDefault($address->user_id);
        }

        $address->update($data);

        return redirect()->route('account.addresses.index')->with('status', __('account.addresses.saved'));
    }

    public function destroy(Address $address): RedirectResponse
    {
        $this->authorizeOwner($address);
        $address->delete();

        return redirect()->route('account.addresses.index')->with('status', __('account.addresses.deleted'));
    }

    public function setDefault(Address $address): RedirectResponse
    {
        $this->authorizeOwner($address);

        $this->clearDefault($address->user_id);
        $address->update(['is_default' => true]);

        return redirect()->route('account.addresses.index')->with('status', __('account.addresses.default_updated'));
    }

    private function authorizeOwner(Address $address): void
    {
        abort_unless($address->user_id === auth()->id(), 403);
    }

    private function clearDefault(int $userId): void
    {
        Address::where('user_id', $userId)->where('is_default', true)->update(['is_default' => false]);
    }

    /** @return array<string,mixed> */
    private function payload(AddressRequest $request): array
    {
        $data = $request->validated();
        $data['type'] = 'shipping';

        return $data;
    }
}
