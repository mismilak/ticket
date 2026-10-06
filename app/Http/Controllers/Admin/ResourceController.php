<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * کنترلر CRUD عمومی برای جدول‌های ساده (دسته‌بندی، اسلایدر، صفحات، مجوزها، سالن‌ها).
 * فیلدها در متد fields() تعریف می‌شوند و فرم/لیست خودکار ساخته می‌شود.
 */
abstract class ResourceController extends Controller
{
    protected string $model;
    protected string $route;      // admin.categories
    protected string $title;      // دسته‌بندی‌ها
    protected string $singular;   // دسته‌بندی
    protected string $orderBy = 'id';

    abstract protected function fields(): array;     // ['name' => ['label'=>, 'type'=>, 'rules'=>]]
    abstract protected function columns(): array;    // ['name' => 'عنوان']

    protected function query()
    {
        return ($this->model)::query()->orderBy($this->orderBy);
    }

    public function index()
    {
        return view('admin.resource.index', [
            'items' => $this->query()->paginate(30),
            'columns' => $this->columns(), 'route' => $this->route, 'title' => $this->title, 'singular' => $this->singular,
            'extraActions' => $this->extraActions(),
        ]);
    }

    protected function extraActions(): array { return []; }

    public function create()
    {
        return $this->form(new ($this->model)());
    }

    public function edit($id)
    {
        return $this->form(($this->model)::findOrFail($id));
    }

    protected function form(Model $item)
    {
        return view('admin.resource.form', [
            'item' => $item, 'fields' => $this->fields(), 'route' => $this->route,
            'title' => ($item->exists ? 'ویرایش ' : 'افزودن ').$this->singular,
        ]);
    }

    public function store(Request $request)
    {
        $item = new ($this->model)();
        $this->save($request, $item);
        return redirect()->route($this->route.'.index')->with('status', 'ذخیره شد.');
    }

    public function update(Request $request, $id)
    {
        $this->save($request, ($this->model)::findOrFail($id));
        return redirect()->route($this->route.'.index')->with('status', 'ذخیره شد.');
    }

    public function destroy($id)
    {
        $item = ($this->model)::findOrFail($id);
        try {
            $item->delete();
        } catch (\Throwable $e) {
            return back()->with('error', 'این مورد در جای دیگری استفاده شده و قابل حذف نیست.');
        }
        return back()->with('status', 'حذف شد.');
    }

    protected function save(Request $request, Model $item): void
    {
        $rules = [];
        foreach ($this->fields() as $name => $f) {
            $rules[$name] = $f['rules'] ?? 'nullable';
        }
        $request->validate($rules);

        $data = [];
        foreach ($this->fields() as $name => $f) {
            $type = $f['type'] ?? 'text';
            if ($type === 'checkbox') {
                $data[$name] = $request->boolean($name);
            } elseif ($type === 'image') {
                if ($request->hasFile($name)) {
                    $data[$name] = self::storeImage($request->file($name));
                } elseif ($request->boolean('remove_'.$name)) {
                    $data[$name] = null;
                }
            } else {
                $data[$name] = $request->input($name);
            }
        }
        $this->beforeSave($data, $item);
        $item->fill($data)->save();
    }

    protected function beforeSave(array &$data, Model $item): void {}

    public static function storeImage($file): string
    {
        $dir = public_path('uploads');
        File::ensureDirectoryExists($dir);
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        abort_unless(in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico']), 422, 'فرمت تصویر مجاز نیست.');
        $name = Str::random(20).'.'.$ext;
        $file->move($dir, $name);
        return $name;
    }
}
