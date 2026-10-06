<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->longText('value')->nullable();
        });

        Schema::create('otp_codes', function (Blueprint $t) {
            $t->id();
            $t->string('mobile', 11)->index();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('icon')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('venues', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('city')->nullable();
            $t->string('address')->nullable();
            $t->string('map_url')->nullable();
            $t->timestamps();
        });

        Schema::create('halls', function (Blueprint $t) {
            $t->id();
            $t->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
        });

        // طبقات سالن (همکف، بالکن، ...)
        Schema::create('hall_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->unsignedInteger('sort')->default(0);
            $t->unsignedInteger('width')->default(1200);
            $t->unsignedInteger('height')->default(800);
            $t->json('shapes')->nullable(); // scene, texts, ...
            $t->timestamps();
        });

        // دسته‌بندی صندلی‌ها (VIP، طلایی، ...)
        Schema::create('seat_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('color', 9)->default('#6c5ce7');
            $t->timestamps();
        });

        Schema::create('seats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hall_level_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seat_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('row_label', 20)->nullable();
            $t->string('label', 30);
            $t->float('x');
            $t->float('y');
            $t->timestamps();
        });

        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('hall_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('subtitle')->nullable();
            $t->text('description')->nullable();
            $t->string('poster')->nullable();
            $t->string('venue_name')->nullable();   // برای رویداد بدون سالن تعریف‌شده
            $t->string('venue_address')->nullable();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->string('status', 20)->default('draft'); // draft|published
            $t->boolean('sales_open')->default(true);
            $t->boolean('is_featured')->default(false);
            $t->timestamps();
        });

        Schema::create('ticket_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seat_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->unsignedBigInteger('price')->default(0);
            $t->unsignedInteger('capacity')->nullable(); // فقط برای بلیط عمومی
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('code', 16)->unique();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('event_id')->constrained();
            $t->string('status', 20)->default('pending')->index(); // pending|paid|failed|expired|canceled
            $t->unsignedBigInteger('total')->default(0);
            $t->string('gateway', 30)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('event_id')->constrained();
            $t->foreignId('ticket_type_id')->constrained();
            $t->foreignId('seat_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('price');
            $t->string('ticket_code', 16)->nullable()->unique();
            $t->boolean('active')->default(true);
            $t->string('lock_key', 40)->nullable()->unique(); // event:seat — جلوگیری از فروش دوباره صندلی
            $t->timestamp('checked_in_at')->nullable();
            $t->timestamps();
            $t->index(['event_id', 'ticket_type_id', 'active']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('gateway', 30);
            $t->unsignedBigInteger('amount');
            $t->string('status', 20)->default('pending'); // pending|paid|failed
            $t->string('authority')->nullable();
            $t->string('ref_id')->nullable();
            $t->string('card_pan')->nullable();
            $t->text('message')->nullable();
            $t->json('raw')->nullable();
            $t->timestamps();
        });

        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->longText('body')->nullable();
            $t->boolean('show_in_footer')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('sliders', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->string('subtitle')->nullable();
            $t->string('image');
            $t->string('link')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // نمادها و مجوزهای فوتر (اینماد، ساماندهی، ...)
        Schema::create('licenses', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('image')->nullable();
            $t->string('link')->nullable();
            $t->text('embed_code')->nullable(); // کد HTML نماد (مثل اینماد)
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['licenses','sliders','pages','payments','order_items','orders','ticket_types','events','seats','seat_categories','hall_levels','halls','venues','categories','otp_codes','settings'] as $tb) {
            Schema::dropIfExists($tb);
        }
    }
};
