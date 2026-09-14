{{-- Write-a-review form — exactly two fields: rating + comment. --}}
@auth
    @if ($userReview)
        <p class="mb-0 text-aroma-muted">
            <i class="bi bi-check-circle text-success me-1"></i>{{ __('reviews.form.already_reviewed') }}
        </p>
    @else
        <button type="button" class="btn btn-aroma-outline" data-bs-toggle="collapse" data-bs-target="#reviewFormPanel">
            <i class="bi bi-pencil-square me-1"></i>{{ __('reviews.write_review') }}
        </button>

        <div class="collapse mt-4 {{ $errors->has('review') || $errors->has('rating') || $errors->has('body') ? 'show' : '' }}" id="reviewFormPanel">
            <form method="post" action="{{ route('reviews.store', $product) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label d-block">{{ __('reviews.form.rating') }} <span class="aroma-required">*</span></label>
                    <x-star-rating-input />
                    @error('rating')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>

                <div class="mb-2">
                    <label class="form-label" for="reviewBody">{{ __('reviews.form.body') }} <span class="aroma-required">*</span></label>
                    <textarea name="body" id="reviewBody" rows="4" maxlength="1000"
                              class="form-control @error('body') is-invalid @enderror js-review-body"
                              placeholder="{{ __('reviews.form.body_placeholder') }}" required>{{ old('body') }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="text-aroma-muted small text-end mb-3 js-review-char-count" data-max="1000"></div>

                @error('review')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                <button type="submit" class="btn btn-aroma">{{ __('reviews.form.submit') }}</button>
            </form>
        </div>
    @endif
@else
    <a href="{{ route('login') }}" class="btn btn-aroma-outline">
        <i class="bi bi-pencil-square me-1"></i>{{ __('reviews.form.sign_in_prompt') }}
    </a>
@endauth
