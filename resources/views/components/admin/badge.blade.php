@props(['status' => null, 'tone' => 'neutral', 'label' => null])

@php
    // Map order/payment statuses to a tone; fall back to the provided tone.
    $map = [
        'paid' => 'success', 'delivered' => 'success', 'captured' => 'success', 'authorized' => 'success',
        'pending' => 'warning',
        'processing' => 'info', 'shipped' => 'info',
        'cancelled' => 'danger', 'failed' => 'danger',
        'refunded' => 'neutral',
    ];
    $resolved = $status ? ($map[$status] ?? 'neutral') : $tone;
    $text = $label ?? ($status ? __('orders.status.'.$status) : '');
@endphp

<span class="admin-badge admin-badge-{{ $resolved }}">{{ $text }}</span>
