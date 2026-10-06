@extends('layouts.app')
@section('title', $event->title)
@section('description', \Illuminate\Support\Str::limit(strip_tags($event->description), 160))
@section('content')
<section class="event-hero">
    <div class="container"><div class="row g-4 align-items-center">
        <div class="col-5 col-md-3"><img class="poster" src="{{ $event->posterUrl() }}" alt="{{ $event->title }}"></div>
        <div class="col-12 col-md-9">
            @if($event->category)<span class="badge bg-primary mb-2">{{ $event->category->name }}</span>@endif
            @if($event->status !== 'published')<span class="badge bg-warning text-dark mb-2">پیش‌نویس (فقط مدیر می‌بیند)</span>@endif
            <h1 class="h3 fw-bold">{{ $event->title }}</h1>
            @if($event->subtitle)<p class="opacity-75">{{ $event->subtitle }}</p>@endif
            <div class="info-row"><i class="bi bi-calendar-event"></i><div>
                @if($sessions->count() > 1)
                    <div class="small opacity-75 mb-1">سانس را انتخاب کنید:</div>
                    <div class="d-flex flex-wrap gap-2">
                    @foreach($sessions as $s)
                        <a href="{{ route('events.show', [$event, 'session' => $s->id]) }}" class="btn btn-sm {{ $s->id === $session->id ? 'btn-primary' : 'btn-outline-light' }} {{ $s->isOnSale() ? '' : 'opacity-50' }}">{{ jdate($s->starts_at) }}</a>
                    @endforeach
                    </div>
                @else
                    {{ jdate($session->starts_at) }}
                @endif
            </div></div>
            <div class="info-row"><i class="bi bi-geo-alt"></i><div>{{ $event->venueLabel() }}
                @if($event->hall?->venue?->city) ، {{ $event->hall->venue->city }}@endif
                @php $addr = $event->hall?->venue?->address ?: $event->venue_address; @endphp
                @if($addr)<div class="small opacity-75">{{ $addr }}</div>@endif
                @if($event->hall?->venue?->map_url)<a class="small text-warning" target="_blank" href="{{ $event->hall->venue->map_url }}">مشاهده روی نقشه</a>@endif
            </div></div>
        </div>
    </div></div>
</section>

<div class="container py-4">
<div class="row g-4">
    <div class="col-lg-8 order-2 order-lg-1">
        @if($seated && $onSale)
        <div class="seatmap-wrap" id="seatmap">
            <div class="seatmap-tools">
                <strong class="me-2"><i class="bi bi-grid-3x3-gap"></i> انتخاب صندلی</strong>
                <div class="btn-group btn-group-sm" id="levelTabs"></div>
                <div class="ms-auto btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" type="button" data-zoom="1.25"><i class="bi bi-zoom-in"></i></button>
                    <button class="btn btn-outline-secondary" type="button" data-zoom="0.8"><i class="bi bi-zoom-out"></i></button>
                    <button class="btn btn-outline-secondary" type="button" data-zoom="0"><i class="bi bi-arrows-fullscreen"></i></button>
                </div>
            </div>
            <div class="seatmap-stage" id="stage"></div>
            <div class="legend" id="legend"></div>
        </div>
        @elseif($seated)
            <div class="alert alert-warning">فروش بلیط این رویداد در حال حاضر بسته است.</div>
        @endif
        @if($event->description)
        <div class="card my-4"><div class="card-body"><h2 class="h5 mb-3">درباره این رویداد</h2><div class="page-body">{!! nl2br(e($event->description)) !!}</div></div></div>
        @endif
    </div>

    <div class="col-lg-4 order-1 order-lg-2">
    <div class="card ticket-box"><div class="card-body">
        <h2 class="h5 mb-3"><i class="bi bi-ticket-perforated text-primary"></i> خرید بلیط</h2>
        @if(! $onSale)
            <div class="alert alert-secondary mb-0">{{ $session->starts_at->isPast() ? 'این رویداد برگزار شده است.' : 'فروش بلیط فعال نیست.' }}</div>
        @elseif(! $seated && $general->isEmpty())
            <div class="alert alert-secondary mb-0">بلیطی برای فروش تعریف نشده است.</div>
        @else
        <form method="post" action="{{ route('events.reserve', $event) }}" id="buyForm">
            @csrf
            <input type="hidden" name="session" value="{{ $session->id }}">
            @foreach($general as $t)
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div><div class="fw-bold">{{ $t->name }}</div><div class="small text-muted">{{ $t->price ? price($t->price) : 'رایگان' }}
                        @if($t->remaining !== null)· {{ $t->remaining > 0 ? fa_digits($t->remaining).' عدد باقی‌مانده' : 'تمام شد' }}@endif</div></div>
                    @if($t->remaining === null || $t->remaining > 0)
                    <div class="qty" data-max="{{ $t->remaining === null ? $max : min($max, $t->remaining) }}">
                        <button type="button" data-d="-1">−</button><input readonly name="general[{{ $t->id }}]" value="0" data-price="{{ $t->price }}"><button type="button" data-d="1">+</button>
                    </div>@endif
                </div>
            @endforeach

            @if($seated)
                <div class="py-2"><div class="small text-muted mb-1">صندلی‌های انتخابی</div><div id="chosen" class="small">هنوز صندلی انتخاب نشده است.</div></div>
            @endif
            <div class="d-flex justify-content-between fw-bold py-3"><span>مبلغ کل</span><span id="total" class="text-primary">۰ {{ setting('currency_label') }}</span></div>
            <div id="seatInputs"></div>
            <button class="btn btn-primary w-100 py-2" id="buyBtn" disabled>ادامه خرید</button>
            <div class="small text-muted mt-2">حداکثر {{ fa_digits($max) }} بلیط در هر سفارش. صندلی‌ها {{ fa_digits(setting('hold_minutes')) }} دقیقه برای شما نگه داشته می‌شود.</div>
        </form>
        @endif
    </div></div>
    </div>
</div>
</div>
@endsection

@push('scripts')
@if($onSale)
<script src="{{ asset('js/seatmap.js') }}"></script>
<script>
(function () {
    const cur = @json(setting('currency_label'));
    const max = {{ $max }};
    const fa = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
    const money = n => fa(Number(n).toLocaleString('en-US')) + ' ' + cur;
    const form = document.getElementById('buyForm'); if (!form) return;
    let picked = [];            // [{id,label,row,price,level}]
    const general = () => [...form.querySelectorAll('.qty input')];

    function refresh() {
        let total = 0, count = picked.length;
        picked.forEach(p => total += p.price);
        general().forEach(i => { total += i.value * i.dataset.price; count += +i.value; });
        document.getElementById('total').textContent = money(total);
        document.getElementById('buyBtn').disabled = count < 1;
        const box = document.getElementById('seatInputs'); box.innerHTML = '';
        picked.forEach(p => box.insertAdjacentHTML('beforeend', '<input type="hidden" name="seats[]" value="' + p.id + '">'));
        const ch = document.getElementById('chosen');
        if (ch) ch.innerHTML = picked.length ? picked.map(p => '<span class="badge bg-light text-dark border me-1 mb-1">' + (p.row ? 'ردیف ' + p.row + ' ' : '') + 'صندلی ' + p.label + ' · ' + p.level + '</span>').join('') : 'هنوز صندلی انتخاب نشده است.';
        return count;
    }
    form.querySelectorAll('.qty').forEach(q => q.addEventListener('click', e => {
        const b = e.target.closest('button'); if (!b) return;
        const i = q.querySelector('input');
        const others = picked.length + general().filter(x => x !== i).reduce((s, x) => s + +x.value, 0);
        const v = Math.max(0, Math.min(+q.dataset.max, others + (+i.value) + (+b.dataset.d) > max ? +i.value : (+i.value) + (+b.dataset.d)));
        i.value = v; refresh();
    }));

    @if($seated)
    const map = new SeatMap({
        stage: document.getElementById('stage'), tabs: document.getElementById('levelTabs'), legend: document.getElementById('legend'),
        url: @json(route('events.seatmap', [$event, 'session' => $session->id])), max, currency: cur,
        canPick: () => picked.length + general().reduce((s, x) => s + +x.value, 0) < max,
        onChange: list => { picked = list; refresh(); },
        onLimit: () => alert('حداکثر ' + fa(max) + ' بلیط در هر سفارش مجاز است.'),
    });
    document.querySelectorAll('[data-zoom]').forEach(b => b.onclick = () => map.zoom(+b.dataset.zoom));
    @endif
    refresh();
})();
</script>
@endif
@endpush
