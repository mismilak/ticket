@foreach($fields as $name => $f)
    @php $type = $f['type'] ?? 'text'; $val = old($name, is_object($values) ? ($values->$name ?? null) : ($values[$name] ?? null)); @endphp
    <div class="mb-3">
        @if($type === 'checkbox')
            <div class="form-check form-switch"><input type="hidden" name="{{ $name }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $name }}" value="1" id="f_{{ $name }}" @checked($val ?? ($f['default'] ?? false))><label class="form-check-label" for="f_{{ $name }}">{{ $f['label'] }}</label></div>
        @else
            <label class="form-label" for="f_{{ $name }}">{{ $f['label'] }}</label>
            @if($type === 'textarea')
                <textarea class="form-control" id="f_{{ $name }}" name="{{ $name }}" rows="{{ $f['rows'] ?? 4 }}" dir="{{ $f['dir'] ?? 'rtl' }}">{{ $val }}</textarea>
            @elseif($type === 'image')
                @if($val)<div class="mb-2"><img src="{{ upload_url($val) }}" style="max-height:90px;border-radius:8px"> <label class="ms-2 small"><input type="checkbox" name="remove_{{ $name }}" value="1"> حذف تصویر</label></div>@endif
                <input type="file" class="form-control" name="{{ $name }}" accept="image/*">
            @elseif($type === 'color')
                <input type="color" class="form-control form-control-color" name="{{ $name }}" value="{{ $val ?: ($f['default'] ?? '#000000') }}">
            @elseif($type === 'password')
                <input type="password" class="form-control" name="{{ $name }}" dir="ltr" autocomplete="new-password" placeholder="{{ $val ? '•••••• (برای تغییر مقدار جدید وارد کنید)' : '' }}">
            @else
                <input type="{{ $type === 'number' ? 'number' : 'text' }}" class="form-control" id="f_{{ $name }}" name="{{ $name }}" value="{{ $val }}" dir="{{ $f['dir'] ?? 'rtl' }}">
            @endif
        @endif
    </div>
@endforeach
