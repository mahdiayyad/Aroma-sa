@props(['rating' => 0])

@php($rating = (float) $rating)
<span class="aroma-stars" dir="ltr" aria-hidden="true">
    @for ($i = 1; $i <= 5; $i++)
        @if ($rating >= $i)
            <i class="bi bi-star-fill"></i>
        @elseif ($rating >= $i - 0.5)
            <i class="bi bi-star-half"></i>
        @else
            <i class="bi bi-star"></i>
        @endif
    @endfor
</span>
