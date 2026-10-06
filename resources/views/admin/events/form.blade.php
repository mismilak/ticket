@extends('layouts.admin')
@section('content')
<h1 class="h4 mb-3">{{ $event->exists ? 'ویرایش رویداد' : 'رویداد جدید' }}</h1>
<form method="post" enctype="multipart/form-data" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}">
@csrf @if($event->exists) @method('PUT') @endif
<div class="row g-3">
<div class="col-lg-8">
    <div class="card mb-3"><div class="card-body">
        <div class="mb-3"><label class="form-label">عنوان</label><input name="title" class="form-control" value="{{ old('title', $event->title) }}" required></div>
        <div class="mb-3"><label class="form-label">زیرعنوان / هنرمند</label><input name="subtitle" class="form-control" value="{{ old('subtitle', $event->subtitle) }}"></div>
        <div class="mb-3"><label class="form-label">توضیحات</label><textarea name="description" class="form-control" rows="6">{{ old('description', $event->description) }}</textarea></div>
    </div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span class="fw-bold">سانس‌ها (زمان‌های برگزاری)</span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addSession">+ افزودن سانس</button></div>
        <div class="card-body" id="sessionRows"><div class="form-text mb-2">هر سانس موجودی صندلی و ظرفیت مستقل دارد. تاریخ شمسی: ۱۴۰۵/۰۷/۲۰ ۲۱:۳۰ — پایان اختیاری است.</div></div>
    </div></div>

    <div class="card mb-3"><div class="card-header fw-bold">مکان برگزاری و صندلی‌ها</div><div class="card-body">
        <div class="mb-3"><label class="form-label">سالن (پلان صندلی)</label>
            <select name="hall_id" id="hall" class="form-select"><option value="">— بدون پلان صندلی (بلیط عمومی) —</option>
                @foreach($halls as $h)<option value="{{ $h->id }}" @selected(old('hall_id', $event->hall_id) == $h->id)>{{ $h->venue->name }} — {{ $h->name }}</option>@endforeach</select>
            <div class="form-text">پلان‌ها را از «سالن‌ها و پلان صندلی» بسازید. اگر پلان انتخاب شود فروش بر اساس صندلی انجام می‌شود.</div></div>
        <div class="row g-3" id="venueManual">
            <div class="col-md-6"><label class="form-label">نام مکان (برای رویداد بدون پلان)</label><input name="venue_name" class="form-control" value="{{ old('venue_name', $event->venue_name) }}"></div>
            <div class="col-md-6"><label class="form-label">آدرس</label><input name="venue_address" class="form-control" value="{{ old('venue_address', $event->venue_address) }}"></div>
        </div>
        <div id="seatPrices" class="mt-3"></div>
    </div></div>

    <div class="card mb-3"><div class="card-header d-flex justify-content-between"><span class="fw-bold">بلیط عمومی (بدون انتخاب صندلی، فروش تعدادی)</span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addGeneral">+ افزودن نوع بلیط</button></div>
        <div class="card-body" id="generalRows"><div class="form-text mb-2">برای رویدادهای ایستاده یا ظرفیتی. ظرفیت خالی = نامحدود. قیمت ۰ = رایگان.</div></div></div>
</div>

<div class="col-lg-4">
    <div class="card mb-3"><div class="card-body">
        <div class="mb-3"><label class="form-label">وضعیت</label><select name="status" class="form-select"><option value="draft" @selected(old('status', $event->status) === 'draft')>پیش‌نویس</option><option value="published" @selected(old('status', $event->status) === 'published')>منتشر شده</option></select></div>
        <div class="mb-3"><label class="form-label">دسته‌بندی</label><select name="category_id" class="form-select"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $event->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        <div class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input" name="sales_open" value="1" id="so" @checked(old('sales_open', $event->sales_open ?? true))><label for="so" class="form-check-label">فروش باز است</label></div>
        <div class="form-check form-switch mb-3"><input type="checkbox" class="form-check-input" name="is_featured" value="1" id="ft" @checked(old('is_featured', $event->is_featured))><label for="ft" class="form-check-label">نمایش در بخش ویژه‌ها</label></div>
        <div class="mb-3"><label class="form-label">نشانی (slug، اختیاری)</label><input name="slug" class="form-control" dir="ltr" value="{{ old('slug', $event->slug) }}"></div>
        <label class="form-label">پوستر</label>
        @if($event->poster)<img src="{{ upload_url($event->poster) }}" class="d-block mb-2" style="max-height:140px;border-radius:8px">@endif
        <input type="file" name="poster" class="form-control" accept="image/*">
    </div></div>
    <button class="btn btn-primary w-100 mb-2">ذخیره</button>
    <a class="btn btn-light w-100" href="{{ route('admin.events.index') }}">بازگشت</a>
</div>
</div>
</form>
@endsection
@push('scripts')
<script>
const hallCats = @json($hallCats);
const prices = @json($seatPrices);
const general = @json($generalData);
const sessionRows = @json($sessionRows);
const sBox = document.getElementById('sessionRows'); let si = 0;
function addSession(t = {}) {
    const i = si++;
    sBox.insertAdjacentHTML('beforeend', `<div class="row g-2 mb-2 align-items-center">
      <input type="hidden" name="sessions[${i}][id]" value="${t.id ?? ''}">
      <div class="col-md-4"><input class="form-control" dir="ltr" name="sessions[${i}][starts_at]" placeholder="شروع: 1405/07/20 21:00" value="${t.starts_at ?? ''}"></div>
      <div class="col-md-4"><input class="form-control" dir="ltr" name="sessions[${i}][ends_at]" placeholder="پایان (اختیاری)" value="${t.ends_at ?? ''}"></div>
      <div class="col-md-3"><div class="form-check form-switch"><input type="checkbox" class="form-check-input" name="sessions[${i}][sales_open]" value="1" ${t.sales_open ?? true ? 'checked' : ''}><label class="form-check-label">فروش باز</label></div></div>
      <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.row').remove()">×</button></div></div>`);
}
document.getElementById('addSession').onclick = () => addSession();
(sessionRows.length ? sessionRows : [{}]).forEach(addSession);
const hall = document.getElementById('hall'), box = document.getElementById('seatPrices'), rows = document.getElementById('generalRows');
function renderSeats() {
    const cats = hallCats[hall.value] || [];
    document.getElementById('venueManual').style.display = hall.value ? 'none' : '';
    box.innerHTML = cats.length ? '<div class="fw-bold mb-2">قیمت هر دسته‌بندی صندلی (خالی = غیرقابل فروش)</div>' + cats.map(c =>
        `<div class="input-group mb-2"><span class="input-group-text" style="min-width:150px"><i style="display:inline-block;width:14px;height:14px;border-radius:4px;background:${c.color};margin-left:6px"></i>${c.name}</span>
         <input class="form-control" dir="ltr" inputmode="numeric" name="seat_price[${c.id}]" value="${prices[c.id] ?? ''}" placeholder="قیمت (تومان)"></div>`).join('') : '';
}
let gi = 0;
function addGeneral(t = {}) {
    const i = gi++;
    rows.insertAdjacentHTML('beforeend', `<div class="row g-2 mb-2 align-items-center">
      <input type="hidden" name="general[${i}][id]" value="${t.id ?? ''}">
      <div class="col-md-5"><input class="form-control" name="general[${i}][name]" placeholder="نام (مثلاً بلیط عادی)" value="${t.name ?? ''}"></div>
      <div class="col-md-3"><input class="form-control" dir="ltr" name="general[${i}][price]" placeholder="قیمت" value="${t.price ?? ''}"></div>
      <div class="col-md-3"><input class="form-control" dir="ltr" name="general[${i}][capacity]" placeholder="ظرفیت" value="${t.capacity ?? ''}"></div>
      <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.row').remove()">×</button></div></div>`);
}
hall.onchange = renderSeats; renderSeats();
document.getElementById('addGeneral').onclick = () => addGeneral();
general.forEach(addGeneral);
</script>
@endpush
