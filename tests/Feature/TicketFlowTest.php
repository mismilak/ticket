<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Seat;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DemoSeeder::class);
        Setting::put('fake_enabled', '1');
    }

    public function test_seat_cannot_be_sold_twice_and_is_released_on_expiry(): void
    {
        $event = Event::where('slug', 'demo-concert')->first();
        $seat = Seat::first();
        $a = User::factory()->create(['mobile' => '09111111111']);
        $b = User::factory()->create(['mobile' => '09122222222']);

        $order = OrderService::create($a, $event, ['seats' => [$seat->id]]);
        $this->expectExceptionMessage('رزرو');
        try {
            OrderService::create($b, $event, ['seats' => [$seat->id]]);
        } finally {
            OrderService::release($order, 'expired');
            $this->assertNotNull(OrderService::create($b, $event, ['seats' => [$seat->id]]));
        }
    }

    public function test_general_ticket_capacity_is_enforced(): void
    {
        $event = Event::where('slug', 'demo-conference')->first();
        $type = $event->ticketTypes()->where('name', 'دانشجویی')->first();
        $type->update(['capacity' => 2]);
        $u = User::factory()->create(['mobile' => '09111111111']);

        OrderService::create($u, $event, ['general' => [$type->id => 2]]);
        $this->expectExceptionMessage('ظرفیت');
        OrderService::create($u, $event, ['general' => [$type->id => 1]]);
    }

    public function test_full_purchase_with_otp_and_test_gateway(): void
    {
        $event = Event::where('slug', 'demo-concert')->first();
        $seat = Seat::first();

        $this->post('/events/demo-concert/reserve', ['seats' => [$seat->id]])->assertRedirect('/checkout');
        $this->get('/checkout')->assertRedirect('/login');

        $this->post('/login', ['mobile' => '09123456789']);
        $code = session('otp_dev');
        $this->assertNotNull($code, 'OTP dev code should be exposed when SMS is not configured');
        $this->post('/login/verify', ['code' => $code])->assertRedirect('/profile');
        $this->post('/profile', ['name' => 'تست'])->assertRedirect();

        $res = $this->post('/checkout', ['gateway' => 'fake']);
        $res->assertRedirect();
        $payment = \App\Models\Payment::first();
        $this->get("/payment/callback/{$payment->id}?result=ok")->assertRedirect();

        $order = $payment->order->fresh();
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->items->first()->ticket_code);
    }

    public function test_admin_area_requires_admin(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['mobile' => '09111111111']))->get('/admin')->assertForbidden();
        $this->actingAs(User::where('role', 'admin')->first())->get('/admin')->assertOk();
    }
}
