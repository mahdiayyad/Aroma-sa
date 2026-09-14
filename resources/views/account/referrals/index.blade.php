@extends('layouts.app')

@section('title', __('referral.title').' — '.$brand['name'])
@section('robots', 'noindex, follow')

@section('content')
@php($locale = app()->getLocale())
@php($referralLink = route('register').'?ref='.$user->referral_code)
<div class="container my-4">
    <h1 class="aroma-section-title mb-1">{{ __('referral.title') }}</h1>
    <p class="text-aroma-muted mb-4">{{ __('referral.subtitle') }}</p>

    <div class="row g-4">
        <div class="col-lg-3">
            @include('account.partials.sidebar', ['active' => 'referrals'])
        </div>

        <div class="col-lg-9">
            {{-- Referral code --}}
            <div class="aroma-trust p-4 mb-4">
                <div class="text-aroma-muted small mb-1">{{ __('referral.your_code') }}</div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <span class="fs-3 fw-bold text-aroma-brown" dir="ltr" id="referralCode">{{ $user->referral_code }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-aroma-outline btn-sm" id="copyCodeBtn" data-copy="{{ $user->referral_code }}">
                        <i class="bi bi-clipboard me-1"></i>{{ __('referral.copy') }}
                    </button>
                    <button type="button" class="btn btn-aroma-outline btn-sm" id="copyLinkBtn" data-copy="{{ $referralLink }}">
                        <i class="bi bi-link-45deg me-1"></i>{{ __('referral.copy_link') }}
                    </button>
                    <button type="button" class="btn btn-aroma btn-sm" id="shareBtn" data-share-url="{{ $referralLink }}" data-share-text="{{ __('referral.how_it_works') }}">
                        <i class="bi bi-share me-1"></i>{{ __('referral.share') }}
                    </button>
                </div>
                <p class="text-aroma-muted small mt-3 mb-0">{{ __('referral.how_it_works') }}</p>
            </div>

            {{-- Stats --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="aroma-trust p-3 text-center h-100">
                        <div class="fs-4 fw-bold">{{ $stats['total'] }}</div>
                        <div class="text-aroma-muted small">{{ __('referral.stats.total_referrals') }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="aroma-trust p-3 text-center h-100">
                        <div class="fs-4 fw-bold">{{ $stats['successful'] }}</div>
                        <div class="text-aroma-muted small">{{ __('referral.stats.successful_referrals') }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="aroma-trust p-3 text-center h-100">
                        <div class="fs-4 fw-bold">{{ $stats['points_earned'] }}</div>
                        <div class="text-aroma-muted small">{{ __('referral.stats.points_earned') }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="aroma-trust p-3 text-center h-100">
                        <div class="fs-4 fw-bold text-aroma-brown">{{ $stats['balance'] }}</div>
                        <div class="text-aroma-muted small">{{ __('referral.stats.balance') }}</div>
                    </div>
                </div>
            </div>

            {{-- Referral history --}}
            <h2 class="h5 mb-3">{{ __('referral.referral_history.title') }}</h2>
            @if ($referrals->isEmpty())
                <div class="aroma-trust text-center py-5 mb-4">
                    <i class="bi bi-people fs-1 text-aroma-light-brown"></i>
                    <p class="text-aroma-muted small mt-3 mb-0">{{ __('referral.referral_history.empty') }}</p>
                </div>
            @else
                <div class="aroma-table-responsive mb-2">
                    <table class="aroma-table">
                        <thead>
                            <tr>
                                <th>{{ __('referral.referral_history.customer') }}</th>
                                <th>{{ __('referral.referral_history.status') }}</th>
                                <th>{{ __('referral.referral_history.date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($referrals as $referral)
                                <tr>
                                    <td>{{ optional($referral->referred)->name ?? '—' }}</td>
                                    <td><span class="aroma-badge-status aroma-badge-{{ $referral->status === 'rewarded' ? 'success' : ($referral->status === 'reversed' ? 'danger' : 'warning') }}">{{ __('referral.referral_history.status_'.$referral->status) }}</span></td>
                                    <td>{{ $referral->created_at->translatedFormat('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mb-4">{{ $referrals->links() }}</div>
            @endif

            {{-- Points history --}}
            <h2 class="h5 mb-3">{{ __('referral.points_history.title') }}</h2>
            @if ($pointTransactions->isEmpty())
                <div class="aroma-trust text-center py-5">
                    <i class="bi bi-gem fs-1 text-aroma-light-brown"></i>
                    <p class="text-aroma-muted small mt-3 mb-0">{{ __('referral.points_history.empty') }}</p>
                </div>
            @else
                <div class="aroma-table-responsive mb-2">
                    <table class="aroma-table">
                        <thead>
                            <tr>
                                <th>{{ __('referral.points_history.transaction') }}</th>
                                <th class="text-end">{{ __('referral.points_history.points') }}</th>
                                <th class="text-end">{{ __('referral.points_history.balance') }}</th>
                                <th>{{ __('referral.points_history.date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pointTransactions as $tx)
                                <tr>
                                    <td>{{ __('referral.types.'.$tx->type) }}</td>
                                    <td class="text-end {{ $tx->points >= 0 ? 'text-success' : 'text-danger' }}">{{ $tx->points >= 0 ? '+' : '' }}{{ $tx->points }}</td>
                                    <td class="text-end">{{ $tx->balance_after }}</td>
                                    <td>{{ $tx->created_at->translatedFormat('j M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div>{{ $pointTransactions->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        function flashCopied(btn, originalHtml) {
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{{ __('referral.copied') }}';
            setTimeout(function () { btn.innerHTML = originalHtml; }, 1800);
        }

        document.querySelectorAll('[data-copy]').forEach(function (btn) {
            var originalHtml = btn.innerHTML;
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () { flashCopied(btn, originalHtml); });
                }
            });
        });

        var shareBtn = document.getElementById('shareBtn');
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                var url = shareBtn.getAttribute('data-share-url');
                var text = shareBtn.getAttribute('data-share-text');
                if (navigator.share) {
                    navigator.share({ text: text, url: url }).catch(function () {});
                } else if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        flashCopied(shareBtn, shareBtn.innerHTML);
                    });
                }
            });
        }
    })();
</script>
@endpush
