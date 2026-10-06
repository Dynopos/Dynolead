<?php

use App\Livewire\AccountPage;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Tenancy\CurrentWorkspace;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

beforeEach(fn () => app(CurrentWorkspace::class)->clear());

it('registers a new business with its own workspace on the free trial', function () {
    Livewire::test(Register::class)
        ->set('name', 'Ali')
        ->set('business', 'Ali Digital')
        ->set('email', 'Ali@Contoh.my')
        ->set('password', 'rahsia123')
        ->set('agree', true)
        ->call('register')
        ->assertHasNoErrors();

    $user = User::where('email', 'ali@contoh.my')->firstOrFail();
    expect($user->workspace->name)->toBe('Ali Digital')
        ->and($user->workspace->sender_name)->toBe('Ali')
        ->and($user->workspace->plan)->toBe('pelanggan')
        ->and($user->workspace->trial_ends_at->isSameDay(now()->addDays(14)))->toBeTrue()
        ->and($user->workspace->activated_at)->toBeNull()
        ->and($user->workspace->balance_sen)->toBe(0)
        ->and($user->isAdmin())->toBeFalse()
        ->and(Hash::check('rahsia123', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('requires terms, a unique e-mail and an 8 character password', function () {
    Livewire::test(Register::class)
        ->set('name', 'Ali')->set('business', 'Ali Digital')
        ->set('email', $this->user->email)
        ->set('password', 'pendek')
        ->call('register')
        ->assertHasErrors(['email' => 'unique', 'password', 'agree' => 'accepted']);

    expect(Workspace::count())->toBe(1);
});

it('logs in with e-mail and password and rate limits failures', function () {
    $this->user->update(['password' => 'betul12345']);

    Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'salah')->call('login')->assertHasErrors('email');
    $this->assertGuest();

    Livewire::test(Login::class)->set('email', strtoupper($this->user->email))->set('password', 'betul12345')->call('login')->assertRedirect(route('leads'));
    $this->assertAuthenticatedAs($this->user);
});

it('locks the login after 5 failed attempts', function () {
    foreach (range(1, 5) as $i) {
        Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'salah')->call('login');
    }

    Livewire::test(Login::class)->set('email', $this->user->email)->set('password', 'password')->call('login')
        ->assertHasErrors('email')->assertSee('Terlalu banyak cubaan');
});

it('sends a reset link and resets the password', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)->set('email', $this->user->email)->call('send')->assertSee('pautan tukar kata laluan');
    Notification::assertSentTo($this->user, ResetPasswordNotification::class);

    $token = Password::createToken($this->user);
    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('email', $this->user->email)
        ->set('password', 'baru12345')
        ->call('resetPassword')
        ->assertRedirect(route('login'));

    expect(Hash::check('baru12345', $this->user->refresh()->password))->toBeTrue();
});

it('gives the same answer for unknown e-mails', function () {
    Notification::fake();

    Livewire::test(ForgotPassword::class)->set('email', 'tiada@contoh.my')->call('send')->assertSee('Kalau e-mel ni berdaftar');
    Notification::assertNothingSent();
});

it('updates business details and password on the account page', function () {
    $this->user->update(['password' => 'lama12345']);
    app(CurrentWorkspace::class)->set($this->workspace);
    $this->actingAs($this->user);

    Livewire::test(AccountPage::class)
        ->set('business', 'Nama Baru Sdn Bhd')
        ->set('sender_name', 'Abu')
        ->call('save')
        ->assertHasNoErrors()
        ->set('current_password', 'salah')
        ->set('new_password', 'baru12345')
        ->call('changePassword')
        ->assertHasErrors('current_password')
        ->set('current_password', 'lama12345')
        ->call('changePassword')
        ->assertHasNoErrors();

    expect($this->workspace->refresh()->name)->toBe('Nama Baru Sdn Bhd')
        ->and($this->workspace->sender_name)->toBe('Abu')
        ->and(Hash::check('baru12345', $this->user->refresh()->password))->toBeTrue();
});

it('keeps guests out of the app and shows the business name in the header', function () {
    $this->get('/lead')->assertRedirect(route('login'));
    $this->get('/kos')->assertRedirect(route('login'));

    $this->actingAs($this->user)->get('/lead')->assertOk()->assertSee($this->workspace->name);
});

it('shows terms and privacy pages to everyone', function () {
    $this->get('/terma')->assertOk()->assertSee('Terma Perkhidmatan');
    $this->get('/privasi')->assertOk()->assertSee('PDPA');
});

it('creates the platform admin with the DynoPOS demo products', function () {
    $this->artisan('dynoleads:admin', ['email' => 'bob@dynopos.my', '--demo-products' => true])
        ->expectsQuestion('Kata laluan (min 8 aksara)', 'rahsiabob1')
        ->assertSuccessful();

    $bob = User::where('email', 'bob@dynopos.my')->firstOrFail();
    expect($bob->isAdmin())->toBeTrue()
        ->and($bob->workspace->plan)->toBe('dalaman')
        ->and(Product::withoutGlobalScope('workspace')->where('workspace_id', $bob->workspace_id)->pluck('slug')->sort()->values()->all())
        ->toBe(['dynopos', 'murahwebsite']);
});

it('gives the admin the workspace that holds migrated Fasa 0 data', function () {
    $legacy = Workspace::factory()->create(['name' => 'DynoPOS Technologies', 'plan' => 'dalaman']);

    $this->artisan('dynoleads:admin', ['email' => 'bob@dynopos.my'])
        ->expectsQuestion('Kata laluan (min 8 aksara)', 'rahsiabob1')
        ->assertSuccessful();

    expect(User::where('email', 'bob@dynopos.my')->value('workspace_id'))->toBe($legacy->id);
});

it('promotes a user who signed up first, without asking for a password', function () {
    Livewire::test(Register::class)
        ->set('name', 'Bob')->set('business', 'DynoPOS Technologies')->set('email', 'bob@dynopos.my')
        ->set('password', 'rahsiabob1')->set('agree', true)
        ->call('register');

    $this->artisan('dynoleads:admin', ['email' => 'bob@dynopos.my', '--demo-products' => true])
        ->assertSuccessful();

    $bob = User::where('email', 'bob@dynopos.my')->firstOrFail();
    expect($bob->isAdmin())->toBeTrue()
        ->and($bob->workspace->plan)->toBe('dalaman')
        ->and(Product::withoutGlobalScope('workspace')->where('workspace_id', $bob->workspace_id)->count())->toBe(2);
});

it('sends new sign-ups to onboarding', function () {
    Livewire::test(Register::class)
        ->set('name', 'Siti')->set('business', 'Siti Web')->set('email', 'siti@contoh.my')
        ->set('password', 'rahsia123')->set('agree', true)
        ->call('register')
        ->assertRedirect(route('onboarding'));
});
