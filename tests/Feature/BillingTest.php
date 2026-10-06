<?php

use App\Jobs\SyncPendingPaymentsJob;
use App\Livewire\AdminPage;
use App\Livewire\BillingPage;
use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Services\Billing\BillingService;
use App\Services\Billing\WalletService;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config([
        'services.chip.key' => 'test-chip-key',
        'services.chip.brand_id' => 'brand-uuid',
        'billing.activation_fee_myr' => 23.90,
    ]);

    // A real RSA key pair so signatures are checked for real.
    $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    config(['services.chip.public_key' => openssl_pkey_get_details($this->key)['key']]);

    $this->workspace->forceFill(['plan' => 'pelanggan', 'activated_at' => null, 'balance_sen' => 0])->save();
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

function startCheckout(string $kind = 'activation', int $amount = 50): Payment
{
    Http::fake(['gate.chip-in.asia/api/v1/purchases/' => Http::response(['id' => 'pur_123', 'checkout_url' => 'https://gate.chip-in.asia/p/pur_123/', 'status' => 'created', 'is_test' => true], 201)]);

    $component = Livewire::test(BillingPage::class);
    $kind === 'activation' ? $component->call('activate') : $component->call('topup', $amount);
    $component->assertRedirect('https://gate.chip-in.asia/p/pur_123/');

    return Payment::latest('id')->firstOrFail();
}

function activatedWorkspace(): void
{
    test()->workspace->forceFill(['activated_at' => now()])->save();
    app(CurrentWorkspace::class)->set(test()->workspace->refresh());
}

it('creates a CHIP purchase for the RM23.90 activation fee, in sen, with callback', function () {
    $payment = startCheckout();

    expect($payment->amount_sen)->toBe(2390)
        ->and($payment->kind)->toBe('activation')
        ->and($payment->status)->toBe('created')
        ->and($payment->chip_purchase_id)->toBe('pur_123')
        ->and($payment->workspace_id)->toBe($this->workspace->id);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://gate.chip-in.asia/api/v1/purchases/'
        && $r->header('Authorization')[0] === 'Bearer test-chip-key'
        && $r['brand_id'] === 'brand-uuid'
        && $r['purchase']['products'][0]['price'] === 2390
        && $r['purchase']['currency'] === 'MYR'
        && $r['reference'] === $payment->reference()
        && $r['client']['email'] === $this->user->email
        && $r['success_callback'] === route('chip.callback'));
});

it('only allows top-ups after activation, and only the listed amounts', function () {
    // No fake yet: any CHIP call here would fail the test (stray request).
    Livewire::test(BillingPage::class)->call('topup', 50)->assertSee('Aktifkan akaun dahulu');
    expect(Payment::count())->toBe(0);

    activatedWorkspace();
    Livewire::test(BillingPage::class)->call('topup', 37);
    expect(Payment::count())->toBe(0);

    $payment = startCheckout('topup', 50);
    expect($payment->kind)->toBe('topup')->and($payment->amount_sen)->toBe(5000);
});

it('does not charge activation twice', function () {
    activatedWorkspace();

    Livewire::test(BillingPage::class)->call('activate')->assertSee('dah aktif');

    expect(Payment::count())->toBe(0);
});

it('activates the account after a verified callback confirmed with CHIP', function () {
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment))]);

    $body = json_encode(chipPurchase($payment));
    $this->call('POST', '/chip/callback', [], [], [], ['HTTP_X_SIGNATURE' => signed($body, $this->key), 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertOk();

    $payment->refresh();
    expect($payment->status)->toBe('paid')
        ->and($this->workspace->refresh()->activated_at)->not->toBeNull()
        ->and(app(WalletService::class)->inTrial($this->workspace))->toBeFalse()
        ->and($this->workspace->balance_sen)->toBe(0);
});

it('rejects a callback with a bad signature and changes nothing', function () {
    $payment = startCheckout();
    $body = json_encode(chipPurchase($payment));

    $this->call('POST', '/chip/callback', [], [], [], ['HTTP_X_SIGNATURE' => base64_encode('palsu'), 'CONTENT_TYPE' => 'application/json'], $body)
        ->assertStatus(400);

    expect($payment->refresh()->status)->toBe('created')
        ->and($this->workspace->refresh()->activated_at)->toBeNull();
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
        ->and($this->workspace->refresh()->activated_at)->toBeNull();
});

it('handles duplicate callbacks once', function () {
    $payment = startCheckout();
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment))]);

    $body = json_encode(chipPurchase($payment));
    foreach (range(1, 3) as $i) {
        $this->call('POST', '/chip/callback', [], [], [], ['HTTP_X_SIGNATURE' => signed($body, $this->key)], $body)->assertOk();
    }

    expect($this->workspace->refresh()->activated_at)->not->toBeNull()
        ->and(Payment::where('status', 'paid')->count())->toBe(1);
});

it('adds a paid top-up to the balance, once', function () {
    activatedWorkspace();
    app(WalletService::class)->credit($this->workspace, 400, 'admin');
    $payment = startCheckout('topup', 20);
    Http::fake(['gate.chip-in.asia/api/v1/purchases/pur_123/' => Http::response(chipPurchase($payment))]);

    app(BillingService::class)->sync($payment);
    app(BillingService::class)->sync($payment->refresh());

    expect($this->workspace->refresh()->balance_sen)->toBe(2400)
        ->and(WalletTransaction::where('reason', 'topup')->value('payment_id'))->toBe($payment->id);
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
    $theirs = app(CurrentWorkspace::class)->runAs($other, fn () => Payment::create(['kind' => 'topup', 'amount_sen' => 5000, 'chip_purchase_id' => 'pur_x']));
    Http::fake();

    $this->get(route('billing.return', $theirs))->assertNotFound();
    Http::assertNothingSent();
});

it('lets the admin give balance, record payments, extend trials and suspend', function () {
    actingAsAdmin();
    $customer = Workspace::factory()->create(['plan' => 'pelanggan']);

    Livewire::test(AdminPage::class)
        ->assertSee($customer->name)
        ->assertSee('Percubaan')
        ->call('manage', $customer->id)
        ->set('mode', 'activation')
        ->set('note', 'Bayar tunai')
        ->call('save', $customer->id)
        ->assertSee('akaun diaktifkan');

    Livewire::test(AdminPage::class)
        ->call('manage', $customer->id)
        ->set('mode', 'balance')
        ->set('amount', '5')
        ->call('save', $customer->id)
        ->assertSee('+RM5.00 baki');

    Livewire::test(AdminPage::class)
        ->call('manage', $customer->id)
        ->set('mode', 'topup')
        ->set('amount', '50')
        ->call('save', $customer->id);

    $customer->refresh();
    expect($customer->activated_at)->not->toBeNull()
        ->and($customer->balance_sen)->toBe(5500)
        ->and(Payment::withoutGlobalScope('workspace')->where('workspace_id', $customer->id)->pluck('kind')->sort()->values()->all())->toBe(['activation', 'topup']);

    $trialist = Workspace::factory()->create(['plan' => 'pelanggan', 'trial_ends_at' => now()->addDay()]);
    Livewire::test(AdminPage::class)->call('extendTrial', $trialist->id);
    expect($trialist->refresh()->trial_ends_at->isSameDay(now()->addDays(8)))->toBeTrue();

    Livewire::test(AdminPage::class)->call('toggleSuspend', $customer->id);
    expect($customer->refresh()->suspended_at)->not->toBeNull();
});

it('keeps the admin panel for the admin only', function () {
    $this->get('/admin')->assertForbidden();

    actingAsAdmin()->get('/admin')->assertOk()->assertSee('Admin');
});
