@php
    $tones = [
        \App\Models\ProductImportRun::STATUS_PENDING => 'info', \App\Models\ProductImportRun::STATUS_QUEUED => 'info', \App\Models\ProductImportRun::STATUS_VALIDATING => 'info', \App\Models\ProductImportRun::STATUS_APPLYING => 'info',
        \App\Models\ProductImportRun::STATUS_READY => 'warning', \App\Models\ProductImportRun::STATUS_COMPLETED => 'success',
        \App\Models\ProductImportRun::STATUS_INVALID => 'danger', \App\Models\ProductImportRun::STATUS_FAILED => 'danger', \App\Models\ProductImportRun::STATUS_CANCELLED => 'neutral',
    ];
    $summary = $run->summary ?? [];
    $shownErrors = array_slice((array) $run->errors, 0, $shown);
    $warnings = array_slice((array) $run->warnings, 0, $shown);
@endphp

@extends('admin.layouts.master')

@section('title', __('admin.products_io.run_title', ['id' => $run->id]).' — '.__('admin.app_name'))
@section('page_title', __('admin.products_io.title'))

@section('content')
    <x-admin.page-header :title="__('admin.products_io.run_title', ['id' => $run->id])" :subtitle="$run->original_name" :back="route('admin.products.index')">
        <x-slot name="actions">
            @can('products.import')
                <a href="{{ route('admin.products.import.create') }}" class="admin-btn admin-btn-outline"><i class="bi bi-cloud-arrow-up"></i>{{ __('admin.products_io.import_another') }}</a>
            @endcan
            <a href="{{ route('admin.products.import.history') }}" class="admin-btn admin-btn-outline"><i class="bi bi-clock-history"></i>{{ __('admin.products_io.history') }}</a>
        </x-slot>
    </x-admin.page-header>

    {{-- Status + live progress -------------------------------------------------- --}}
    <x-admin.card class="mb-3" id="importStatusCard"
        data-status-url="{{ route('admin.products.import.status', $run) }}"
        data-status="{{ $run->status }}"
        data-active="{{ $run->isActive() ? '1' : '0' }}"
        data-phase-validate="{{ __('admin.products_io.phase_validate') }}"
        data-phase-apply="{{ __('admin.products_io.phase_apply') }}"
        data-progress-text="{{ __('admin.products_io.progress', ['processed' => ':processed', 'total' => ':total']) }}">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
            <x-admin.badge :tone="$tones[$run->status] ?? 'neutral'" :label="__('admin.products_io.status.'.$run->status)" />
            <div class="admin-cell-sub">
                {{ __('admin.products_io.uploaded_by') }}: {{ optional($run->user)->name ?? '—' }} · {{ $run->created_at->format('Y-m-d H:i') }}
                @if ($run->finished_at && !empty($summary['seconds']))
                    · {{ __('admin.products_io.duration') }}: {{ __('admin.products_io.seconds', ['n' => $summary['seconds']]) }}
                @endif
            </div>
        </div>

        @if ($run->isActive())
            <div class="progress mb-2" style="height: 12px;" role="progressbar" aria-label="{{ __('admin.products_io.status.'.$run->status) }}"
                 aria-valuenow="{{ $progress['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" id="importProgressBar" style="width: {{ max($progress['percent'], 3) }}%; background: var(--admin-brand, #330101);"></div>
            </div>
            <div class="admin-cell-sub" id="importProgressText">
                @if ($run->status === \App\Models\ProductImportRun::STATUS_QUEUED)
                    {{ __('admin.products_io.status.queued') }}
                @else
                    {{ $progress['phase'] === \App\Models\ProductImportRun::PHASE_APPLY ? __('admin.products_io.phase_apply') : __('admin.products_io.phase_validate') }}
                    @if ($progress['total'] > 0) — {{ __('admin.products_io.progress', ['processed' => $progress['processed'], 'total' => $progress['total']]) }} @endif
                @endif
            </div>
            @if ($run->status === \App\Models\ProductImportRun::STATUS_QUEUED)
                <div class="admin-hint mt-2">
                    {{ __('admin.products_io.queued_hint') }}
                    <code dir="ltr">php artisan queue:work --queue={{ $queue }}</code>
                </div>
            @endif
        @endif

        @if ($run->status === \App\Models\ProductImportRun::STATUS_COMPLETED)
            <div class="admin-alert admin-alert-success mb-0 mt-2"><i class="bi bi-check-circle-fill"></i><div><strong>{{ __('admin.products_io.completed_title') }}</strong></div></div>
        @elseif ($run->status === \App\Models\ProductImportRun::STATUS_FAILED)
            <div class="admin-alert admin-alert-danger mb-0 mt-2"><i class="bi bi-exclamation-triangle-fill"></i>
                <div><strong>{{ __('admin.products_io.failed_title') }}</strong><div>{{ $run->message }}</div></div>
            </div>
        @elseif ($run->status === \App\Models\ProductImportRun::STATUS_CANCELLED)
            <div class="admin-hint mt-2">{{ __('admin.products_io.cancelled_title') }}</div>
        @elseif ($run->status === \App\Models\ProductImportRun::STATUS_INVALID)
            <div class="admin-alert admin-alert-danger mb-0 mt-2"><i class="bi bi-exclamation-triangle-fill"></i>
                <div><strong>{{ __('admin.products_io.invalid_title') }}</strong><div>{{ __('admin.products_io.invalid_hint') }}</div></div>
            </div>
        @endif
    </x-admin.card>

    {{-- Summary counters ------------------------------------------------------- --}}
    @if (! empty($summary))
        <div class="admin-stats-grid">
            <x-admin.stat :label="__('admin.products_io.rows')" :value="$summary['rows'] ?? 0" icon="bi-list-ol" />
            <x-admin.stat :label="__('admin.products_io.created')" :value="$summary['created'] ?? 0" icon="bi-plus-circle" tone="success" />
            <x-admin.stat :label="__('admin.products_io.updated')" :value="$summary['updated'] ?? 0" icon="bi-pencil-square" tone="info" />
            <x-admin.stat :label="__('admin.products_io.unchanged')" :value="$summary['unchanged'] ?? 0" icon="bi-dash-circle" />
            @if ($run->status === \App\Models\ProductImportRun::STATUS_COMPLETED)
                <x-admin.stat :label="__('admin.products_io.images_added')" :value="$summary['images_added'] ?? 0" icon="bi-images" tone="gold" />
                @if (! empty($summary['images_removed']))
                    <x-admin.stat :label="__('admin.products_io.images_removed')" :value="$summary['images_removed']" icon="bi-image" tone="warning" />
                @endif
            @elseif (in_array($run->status, [\App\Models\ProductImportRun::STATUS_READY, \App\Models\ProductImportRun::STATUS_INVALID], true))
                <x-admin.stat :label="__('admin.products_io.images_new')" :value="$summary['images_new'] ?? 0" icon="bi-images" tone="gold" />
            @endif
        </div>
    @endif

    {{-- Review & confirm ------------------------------------------------------- --}}
    @if ($run->canConfirm())
        <x-admin.card class="mb-3" :title="__('admin.products_io.preview_title')">
            <p class="mb-3">{{ __('admin.products_io.preview_text') }}</p>
            <div class="d-flex flex-wrap gap-2">
                @can('products.import')
                    <form method="post" action="{{ route('admin.products.import.confirm', $run) }}">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-check2-circle"></i>{{ __('admin.products_io.confirm') }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.products.import.cancel', $run) }}" data-confirm="{{ __('admin.products_io.cancel_text') }}">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn-danger"><i class="bi bi-x-circle"></i>{{ __('admin.products_io.cancel') }}</button>
                    </form>
                @endcan
            </div>
        </x-admin.card>
    @endif

    {{-- Errors ------------------------------------------------------------------- --}}
    @if ((int) $run->error_count > 0)
        <x-admin.card class="mb-3" :padding="false">
            <x-slot name="head">
                <h3 class="admin-card-title">{{ __('admin.products_io.errors_title', ['count' => $run->error_count]) }}</h3>
                <a href="{{ route('admin.products.import.errors', $run) }}" class="admin-btn admin-btn-outline btn-sm"><i class="bi bi-download"></i>{{ __('admin.products_io.download_errors') }}</a>
            </x-slot>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 70px">{{ __('admin.products_io.col_row') }}</th>
                            <th style="width: 200px">{{ __('admin.products_io.col_column') }}</th>
                            <th>{{ __('admin.products_io.col_message') }}</th>
                            <th style="width: 220px">{{ __('admin.products_io.col_value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shownErrors as $error)
                            <tr>
                                <td><strong>{{ $error['row'] }}</strong></td>
                                <td>{{ ! empty($error['column']) && isset(\App\Support\ProductSheet\Columns::all()[$error['column']]) ? \App\Support\ProductSheet\Columns::get($error['column'])->header : '—' }}</td>
                                <td>{{ $error['message'] }}</td>
                                <td class="admin-cell-sub" dir="auto">{{ $error['value'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ((int) $run->error_count > count($shownErrors))
                <div class="admin-hint p-3">{{ __('admin.products_io.errors_capped', ['shown' => count($shownErrors), 'count' => $run->error_count]) }}</div>
            @endif
        </x-admin.card>
    @endif

    {{-- Warnings ------------------------------------------------------------------ --}}
    @if (! empty($warnings))
        <x-admin.card class="mb-3" :padding="false">
            <x-slot name="head">
                <h3 class="admin-card-title">{{ __('admin.products_io.warnings_title', ['count' => count((array) $run->warnings)]) }}</h3>
                <span class="admin-cell-sub">{{ __('admin.products_io.warnings_hint') }}</span>
            </x-slot>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <tbody>
                        @foreach ($warnings as $warning)
                            <tr>
                                <td style="width: 70px"><strong>{{ $warning['row'] }}</strong></td>
                                <td style="width: 200px">{{ ! empty($warning['column']) && isset(\App\Support\ProductSheet\Columns::all()[$warning['column']]) ? \App\Support\ProductSheet\Columns::get($warning['column'])->header : '—' }}</td>
                                <td>{{ $warning['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    @endif

    @if ($run->isFinished())
        <div class="mt-3">
            <a href="{{ route('admin.products.index') }}" class="admin-btn admin-btn-primary"><i class="bi bi-box-seam"></i>{{ __('admin.products_io.back_to_products') }}</a>
        </div>
    @endif
@endsection

@push('scripts')
    @if ($run->isActive())
        <script src="{{ asset('js/admin-product-import.js') }}"></script>
    @endif
@endpush
