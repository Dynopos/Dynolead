<?php

use App\Jobs\SyncPendingPaymentsJob;
use App\Livewire\AdminPage;
use App\Livewire\BillingPage;
use App\Models\CreditTransaction;
use App\Models\Payment;
use App\Models\Workspace;
use App\Services\Billing\BillingService;
use App\Services\Billing\CreditService;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.chip.key' => 'test-chip-key',
        'services.chip.brand_id' => 'brand-uuid',
        'credits.packs.pek30.price_myr' => 99,
    ]);

    // A real RSA key pair so signatures are checked for real.
    $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    config(['services.chip.public_key' => openssl_pkey_get_details($this->key)['key']]);

    $this->workspace->update(['plan' => 'kredit']);
    app(CurrentWorkspace::class)->set($this->workspace->refresh());
    actingAsOwner();
});

function chipPurchase(Payment $payment, string $status = 'paid', array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $payment->chip_purchase_id,
        'status' => $status,
        'reference' => $payment->reference(),
        'is_test' => true,
        'purchase' => ['currency' => 'MYR', 'total' => $payment->amount_sen],
    ], $overrides);
}

function signed(string $body, $key): string
{
    openssl_sign($body, $signature, $key, OPENSSL_ALGO_SHA256);

    return base64_encode($signature);
}

function startCheckout(): Payment
{
    Http::fake(['gate.chip-in.asia/api/v1/purchases/' => Http::response(['id' => 'pur_123', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_123/', 'status' => 'created', 'is_test' => true], 201)]);

    Livewire::test(BillingPage::class)->call('buy', 'pek30')->assertRedirect('https://gate.chip-in.asia/p/pur_123/');

    return Payment::firstOrFail();
}

it('creates a CHIP purchase in sen with callback and redirects', function () {
    $payment = startCheckout();

    expect($payment->amount_sen)->toBe(9900)
        ->and($payment->credits)->toBe(30)
        ->and($payment->status)->toBe('created')
        ->and($payment->chip_purchase_id)->toBe('pur_123')
        ->and($payment->workspace_id)->toBe($this->workspace->id);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://gate.chip-in.asia/api/v1/purchases/'
        && $r->header('Authorization')[0] === 'Bearer test-chip-key'
        && $r['brand_id'] === 'brand-uuid'
        && $r['purchase']['products'][0]['price'] === 9900
        && $r['purchase']['currency'] === 'MYR'
        && $r['reference'] === $payment->reference()
        && $r['client']['email'] === $this->user->email
        && $r['success_callback'] === route('chip.callback'));
});

it('refuses packs without a price', function () {
    Http::fake();

    Livewire::test(BillingPage::class)->call('buy', 'pek100');

    expect(Payment::count())->toBe(0);
    Http::assertNothingSent();
});

it('adds the credits after a verified callback confirmed with CHIP', function () {
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment))]);

    $body = json_encode(chipPurchase($payment));
    $this->call('POST', '/chip/callback', [], [], [], ['HTTP_X_SIGNATURE' => signed($body, $this->key), 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('paid')
        ->and($this->workspace->refresh()->credits)->toBe(30)
        ->and(CreditTransaction::where('reason', 'purchase')->value('payment_id'))->toBe($payment->id);
});

it('rejects a callback with a bad signature and changes nothing', function () {
    $payment = startCheckout();
    $body = json_encode(chipPurchase($payment));

    $this->call('POST', '/chip/callback', [], [], [], ['HTTP_X_SIGNATURE' => base64_encode('palsu'), 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertStatus(400);

    expect($payment->refresh()->status)->toBe('created')
        ->and($this->workspace->refresh()->credits)->toBe(0);
});

it('does not trust a signed payload that CHIP itself says is unpaid', function () {
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment, 'created'))]);

    $body = json_encode(chipPurchase($payment, 'paid'));
    $this->call('POST', '/chip/callback', [], [], [], ['HTTP_X_SIGNATURE' => signed($body, $this->key)], $body)->assertOk();

    expect($payment->refresh()->status)->toBe('created');
});

it('refuses a paid purchase whose amount does not match', function () {
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment, 'paid', ['purchase' => ['total' => 100]]))]);

    app(BillingService::class)->sync($payment);

    expect($payment->refresh()->status)->toBe('failed')
        ->and($this->workspace->refresh()->credits)->toBe(0);
});

it('handles duplicate callbacks once', function () {
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment))]);

    $body = json_encode(chipPurchase($payment));
    foreach (range(1, 3) as $i) {
        $this->call('POST', '/chip/callback', [], [], [], ['HTTP_X_SIGNATURE' => signed($body, $this->key)], $body)->assertOk();
    }

    expect($this->workspace->refresh()->credits)->toBe(30)
        ->and(Payment::where('status', 'paid')->count())->toBe(1);
});

it('adds a second pack on top of the current balance', function () {
    app(CreditService::class)->grant($this->workspace, 4, 'admin');
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment))]);

    app(BillingService::class)->sync($payment);

    expect($this->workspace->refresh()->credits)->toBe(34);
});

it('confirms payment when the customer returns, and the hourly job catches missed callbacks', function () {
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::sequence()
        ->push(chipPurchase($payment, 'created'))
        ->push(chipPurchase($payment, 'paid'))]);

    $this->get(route('billing.return', $payment))->assertRedirect(route('billing'))->assertSessionHas('warning');
    expect($payment->refresh()->status)->toBe('created');

    app(CurrentWorkspace::class)->clear();
    dispatch_sync(new SyncPendingPaymentsJob);

    expect($payment->refresh()->status)->toBe('paid');
});

it('never shows or syncs another customer’s payment', function () {
    [$other] = otherWorkspace();
    $theirs = app(CurrentWorkspace::class)->runAs($other, fn () => Payment::create(['pack' => 'pek30', 'credits' => 30, 'amount_sen' => 9900, 'chip_purchase_id' => 'pur_x']));
    Http::fake();

    $this->get(route('billing.return', $theirs))->assertNotFound();
    Http::assertNothingSent();
});

it('lets the admin grant credits, record a manual payment and suspend', function () {
    actingAsAdmin();
    $customer = Workspace::factory()->create(['plan' => 'kredit']);

    Livewire::test(AdminPage::class)
        ->assertSee($customer->name)
        ->call('manage', $customer->id)
        ->set('amount', '5')
        ->set('note', 'Pampasan')
        ->call('grant', $customer->id)
        ->assertSee('+5 kredit');

    Livewire::test(AdminPage::class)
        ->call('manage', $customer->id)
        ->set('mode', 'payment')
        ->set('pack', 'pek30')
        ->set('note', 'Pindahan bank')
        ->call('recordPayment', $customer->id)
        ->assertSee('bayaran Pek 30 direkod');

    expect($customer->refresh()->credits)->toBe(35)
        ->and(Payment::withoutGlobalScope('workspace')->where('workspace_id', $customer->id)->value('status'))->toBe('manual');

    Livewire::test(AdminPage::class)->call('toggleSuspend', $customer->id);
    expect($customer->refresh()->suspended_at)->not->toBeNull();
});

it('keeps the admin panel for the admin only', function () {
    $this->get('/admin')->assertForbidden();

    actingAsAdmin()->get('/admin')->assertOk()->assertSee('Admin');
});
