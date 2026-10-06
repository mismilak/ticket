@extends('layouts.admin')
@section('title', 'طراحی پلان '.$hall->name)
@push('head')
<style>
    .dz{display:grid;grid-template-columns:300px 1fr;gap:1rem;align-items:start}
    @media(max-width:1000px){.dz{grid-template-columns:1fr}}
    .dz-canvas{height:calc(100vh - 170px);min-height:480px;background:#0f1124;border-radius:12px;position:relative;touch-action:none;user-select:none;overflow:hidden}
    .dz-canvas svg{width:100%;height:100%;display:block}
    .dz-side .card{margin-bottom:.8rem}.dz-side .card-header{padding:.5rem .8rem;font-weight:700;font-size:.9rem}
    .dz-side .card-body{padding:.7rem .8rem}
    .tool-btn.active{background:var(--c-primary);color:#fff;border-color:var(--c-primary)}
    .dz-canvas .seat text{pointer-events:none}
    .cat-row{display:flex;gap:.4rem;align-items:center;margin-bottom:.4rem}
    .cat-row input[type=color]{width:34px;height:30px;padding:0;border:0;background:none}
    .hint{font-size:.78rem;color:#777}
</style>
@endpush
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <div class="d-flex align-items-center gap-2"><a href="{{ route('admin.halls.index', $hall->venue_id) }}" class="btn btn-sm btn-light"><i class="bi bi-arrow-right"></i></a>
        <h1 class="h5 mb-0">طراحی پلان: {{ $hall->venue->name }} — </h1><input id="hallName" class="form-control form-control-sm" style="width:200px" value="{{ $hall->name }}"></div>
    <div class="d-flex align-items-center gap-2"><span id="status" class="small text-muted"></span>
        <span class="small text-muted" id="count"></span>
        <button class="btn btn-outline-secondary btn-sm" id="undo" title="Ctrl+Z"><i class="bi bi-arrow-counterclockwise"></i></button>
        <button class="btn btn-primary" id="save"><i class="bi bi-save"></i> ذخیره پلان</button></div>
</div>

<div class="dz">
<div class="dz-side">
    <div class="card"><div class="card-header">طبقات (همکف، بالکن، ...)</div><div class="card-body">
        <div id="levelList" class="d-flex flex-wrap gap-1 mb-2"></div>
        <div class="d-flex gap-1 mb-2"><button class="btn btn-sm btn-outline-primary" id="addLevel">+ طبقه</button><button class="btn btn-sm btn-outline-secondary" id="renLevel">نام</button><button class="btn btn-sm btn-outline-danger" id="delLevel">حذف</button></div>
        <div class="row g-1"><div class="col"><label class="hint">عرض بوم</label><input type="number" id="lvW" class="form-control form-control-sm"></div><div class="col"><label class="hint">ارتفاع بوم</label><input type="number" id="lvH" class="form-control form-control-sm"></div></div>
    </div></div>

    <div class="card"><div class="card-header">دسته‌بندی صندلی‌ها (قیمت)</div><div class="card-body">
        <div id="catList"></div>
        <button class="btn btn-sm btn-outline-primary" id="addCat">+ دسته‌بندی</button>
        <div class="hint mt-1">قیمت هر دسته را هنگام ساخت رویداد تعیین می‌کنید.</div>
    </div></div>

    <div class="card"><div class="card-header">افزودن ردیف صندلی</div><div class="card-body">
        <div class="row g-1 mb-1">
            <div class="col-6"><label class="hint">تعداد ردیف</label><input type="number" id="gRows" class="form-control form-control-sm" value="5" min="1"></div>
            <div class="col-6"><label class="hint">صندلی در هر ردیف</label><input type="number" id="gCols" class="form-control form-control-sm" value="12" min="1"></div>
            <div class="col-6"><label class="hint">فاصله افقی</label><input type="number" id="gDx" class="form-control form-control-sm" value="30"></div>
            <div class="col-6"><label class="hint">فاصله ردیف‌ها</label><input type="number" id="gDy" class="form-control form-control-sm" value="32"></div>
            <div class="col-6"><label class="hint">انحنا (درجه، ۰=صاف)</label><input type="number" id="gArc" class="form-control form-control-sm" value="0"></div>
            <div class="col-6"><label class="hint">برچسب ردیف اول</label><input id="gRow" class="form-control form-control-sm" value="A" dir="ltr"></div>
            <div class="col-6"><label class="hint">شماره اولین صندلی</label><input type="number" id="gStart" class="form-control form-control-sm" value="1"></div>
            <div class="col-6"><label class="hint">شماره‌گذاری</label><select id="gDir" class="form-select form-select-sm"><option value="rtl">از راست</option><option value="ltr">از چپ</option></select></div>
        </div>
        <button class="btn btn-sm btn-primary w-100" id="genRows">افزودن به پلان</button>
        <div class="hint mt-1">ردیف‌ها وسط نمایش اضافه و انتخاب می‌شوند؛ می‌توانید بکشید و جابجا کنید.</div>
    </div></div>

    <div class="card"><div class="card-header">شکل‌ها</div><div class="card-body d-flex gap-1 flex-wrap">
        <button class="btn btn-sm btn-outline-secondary" data-shape="stage">+ صحنه</button>
        <button class="btn btn-sm btn-outline-secondary" data-shape="text">+ متن</button>
        <button class="btn btn-sm btn-outline-secondary" data-shape="rect">+ مستطیل (راهرو/بار)</button>
    </div></div>

    <div class="card" id="selPanel"><div class="card-header">انتخاب‌شده: <span id="selCount">۰</span></div><div class="card-body">
        <div id="seatTools">
            <div class="input-group input-group-sm mb-1"><select id="selCat" class="form-select"></select><button class="btn btn-outline-primary" id="applyCat">اعمال دسته</button></div>
            <div class="input-group input-group-sm mb-1"><input id="selRow" class="form-control" placeholder="برچسب ردیف" dir="ltr"><button class="btn btn-outline-primary" id="applyRow">اعمال</button></div>
            <div class="input-group input-group-sm mb-1"><input id="selStart" type="number" class="form-control" value="1"><select id="selDir" class="form-select"><option value="rtl">راست→چپ</option><option value="ltr">چپ→راست</option></select><button class="btn btn-outline-primary" id="renum">شماره‌گذاری</button></div>
            <div class="d-flex gap-1 flex-wrap"><button class="btn btn-sm btn-outline-secondary" id="alignY">هم‌تراز افقی</button><button class="btn btn-sm btn-outline-secondary" id="alignX">هم‌تراز عمودی</button><button class="btn btn-sm btn-outline-secondary" id="distX">فاصله مساوی</button><button class="btn btn-sm btn-outline-secondary" id="dup">کپی</button></div>
        </div>
        <div id="shapeTools" class="d-none">
            <input id="shText" class="form-control form-control-sm mb-1" placeholder="متن">
            <div class="row g-1"><div class="col"><input id="shW" type="number" class="form-control form-control-sm" placeholder="عرض"></div><div class="col"><input id="shH" type="number" class="form-control form-control-sm" placeholder="ارتفاع"></div></div>
        </div>
        <button class="btn btn-sm btn-danger w-100 mt-2" id="delSel"><i class="bi bi-trash"></i> حذف انتخاب‌شده (Delete)</button>
    </div></div>
</div>

<div>
    <div class="d-flex gap-2 mb-2 flex-wrap">
        <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary tool-btn active" data-tool="select"><i class="bi bi-cursor"></i> انتخاب</button><button class="btn btn-outline-secondary tool-btn" data-tool="pan"><i class="bi bi-hand-index"></i> جابجایی بوم</button></div>
        <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary" data-z="1.2"><i class="bi bi-zoom-in"></i></button><button class="btn btn-outline-secondary" data-z="0.83"><i class="bi bi-zoom-out"></i></button><button class="btn btn-outline-secondary" data-z="0"><i class="bi bi-arrows-fullscreen"></i></button></div>
        <span class="hint align-self-center">کلیک/کشیدن = انتخاب · Shift = افزودن به انتخاب · چرخ ماوس = زوم · کلیدهای جهت‌دار = جابجایی دقیق · دوبار کلیک روی متن = ویرایش</span>
    </div>
    <div class="dz-canvas" id="canvas"></div>
</div>
</div>
@endsection

@push('scripts')
<script>window.__HALL__ = {save: @json(route('admin.halls.save', $hall)), layout: @json($layout), csrf: @json(csrf_token())};</script>
<script src="{{ asset('js/seatmap.js') }}"></script>
<script src="{{ asset('js/designer.js') }}"></script>
@endpush
