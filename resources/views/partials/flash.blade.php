{{-- Success flashes render as a toast (see the data-flash-success body
     attribute + aroma-ui.js) instead of a persistent banner here. --}}

@if (session('error'))
    <div class="aroma-alert aroma-alert-danger aroma-animate-in alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <div class="flex-grow-1">{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('warning'))
    <div class="aroma-alert aroma-alert-warning aroma-animate-in alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle"></i>
        <div class="flex-grow-1">{{ session('warning') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="aroma-alert aroma-alert-danger aroma-animate-in" role="alert">
        <i class="bi bi-exclamation-triangle"></i>
        <ul class="mb-0 ps-3 flex-grow-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
