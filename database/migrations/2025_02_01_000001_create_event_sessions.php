<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سانس‌های هر رویداد (هر سانس موجودی صندلی و ظرفیت جدا دارد)
        Schema::create('event_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->boolean('sales_open')->default(true);
            $t->timestamps();
        });
        Schema::table('orders', fn (Blueprint $t) => $t->foreignId('event_session_id')->nullable()->constrained('event_sessions'));
        Schema::table('order_items', fn (Blueprint $t) => $t->foreignId('event_session_id')->nullable()->constrained('event_sessions'));

        // انتقال رویدادهای موجود: یک سانس برای هر رویداد
        foreach (DB::table('events')->get() as $e) {
            $id = DB::table('event_sessions')->insertGetId([
                'event_id' => $e->id, 'starts_at' => $e->starts_at, 'ends_at' => $e->ends_at,
                'sales_open' => $e->sales_open, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('orders')->where('event_id', $e->id)->update(['event_session_id' => $id]);
            DB::table('order_items')->where('event_id', $e->id)->update(['event_session_id' => $id]);
        }
        // کلید قفل صندلی از «رویداد:صندلی» به «سانس:صندلی» تغییر می‌کند
        foreach (DB::table('order_items')->whereNotNull('lock_key')->get() as $i) {
            DB::table('order_items')->where('id', $i->id)->update(['lock_key' => $i->event_session_id.':'.$i->seat_id]);
        }
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('event_session_id'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('event_session_id'));
        Schema::dropIfExists('event_sessions');
    }
};
