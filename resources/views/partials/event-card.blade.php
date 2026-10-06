<a href="{{ route('events.show', $event) }}" class="event-card position-relative">
    <div class="position-relative">
        <img class="poster" loading="lazy" src="{{ $event->posterUrl() }}" alt="{{ $event->title }}">
        @if($event->category)<span class="badge-cat">{{ $event->category->name }}</span>@endif
    </div>
    <div class="body">
        <h3>{{ $event->title }}</h3>
        <div class="meta"><i class="bi bi-calendar-event"></i> {{ jdate($event->starts_at, 'full') }}@if(($event->sessions_count ?? 0) > 1) <span class="badge bg-light text-dark border">{{ fa_digits($event->sessions_count) }} سانس</span>@endif</div>
        @if($event->venueLabel())<div class="meta"><i class="bi bi-geo-alt"></i> {{ $event->venueLabel() }}</div>@endif
        <div class="mt-2 d-flex justify-content-between align-items-center">
            @if($event->isOnSale() && $event->minPrice() !== null)
                <span class="small text-muted">از <b class="text-primary">{{ price($event->minPrice()) }}</b></span>
            @else
                <span class="badge bg-secondary">{{ $event->starts_at->isPast() ? 'برگزار شد' : 'فروش بسته است' }}</span>
            @endif
        </div>
    </div>
</a>
