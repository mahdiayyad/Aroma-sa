<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Models\Address;
use App\Services\LocationLookupService;
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
    private LocationLookupService $locationLookup;

    public function __construct(LocationLookupService $locationLookup)
    {
        $this->locationLookup = $locationLookup;
    }

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

        if ($data === null) {
            return back()->withErrors(['location_code' => __('location.errors.not_found')])->withInput();
        }

        $isStub = (bool) ($data['is_stub'] ?? false);
        unset($data['is_stub']); // no column on `addresses` — flash message is the only consumer

        if ($data['is_default'] || $user->addresses()->doesntExist()) {
            $this->clearDefault($user->id);
            $data['is_default'] = true;
        }

        $user->addresses()->create($data);

        return redirect()->route('account.addresses.index')
            ->with('status', __($isStub ? 'account.addresses.saved_stub' : 'account.addresses.saved'));
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

        if ($data === null) {
            return back()->withErrors(['location_code' => __('location.errors.not_found')])->withInput();
        }

        $isStub = (bool) ($data['is_stub'] ?? false);
        unset($data['is_stub']); // no column on `addresses` — flash message is the only consumer

        if ($data['is_default']) {
            $this->clearDefault($address->user_id);
        }

        $address->update($data);

        return redirect()->route('account.addresses.index')
            ->with('status', __($isStub ? 'account.addresses.saved_stub' : 'account.addresses.saved'));
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

    /**
     * Resolves one of the two location methods:
     * - location_code present: via LocationLookupService (city/region/
     *   district/formatted_address/coordinates). Returns null on lookup
     *   failure — store()/update() translate that into a field-specific
     *   validation error; there is no manual fallback.
     * - No code, only coordinates: the shopper pinned a spot on the map.
     *   Stored as coordinates ONLY — no lookup, no street/city/region/
     *   district/formatted_address text.
     *
     * Exactly one of these is guaranteed present by AddressRequest's
     * withValidator check before this is ever called.
     *
     * @return array<string,mixed>|null
     */
    private function payload(AddressRequest $request): ?array
    {
        $data = $request->validated();
        $data['type'] = 'shipping';

        if (filled($data['location_code'] ?? null)) {
            $result = $this->locationLookup->lookup($data['location_code']);

            if (! $result['success']) {
                return null;
            }

            $data['location_code'] = strtoupper(trim($data['location_code']));
            $data['street_address'] = null;
            $data['postal_code'] = null;

            // is_stub has no column on `addresses` — store()/update() strip
            // it before persistence. It rides along on this return value only
            // so those two callers can pick a stub-aware flash message.
            return array_merge($data, $result['data'], ['is_stub' => $result['is_stub'] ?? false]);
        }

        $data['location_code'] = null;
        $data['city'] = null;
        $data['region'] = null;
        $data['district'] = null;
        $data['country'] = null;
        $data['formatted_address'] = null;
        $data['street_address'] = null;
        $data['postal_code'] = null;
        // A pinned-on-map location never goes through lookup() — never stub data.
        $data['is_stub'] = false;

        return $data;
    }
}
