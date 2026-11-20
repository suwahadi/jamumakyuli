<?php

namespace App\Providers;

use App\Models\User;
use App\Services\WishlistService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureDefaults();

        Blade::anonymousComponentNamespace('emails.components', 'email');

        View::composer('emails.*', function ($view): void {
            $view->with([
                ...config('mail.palette'),
                'tones' => config('mail.tones'),
                'siteName' => setting('site_name', config('services.brevo.sender_name', 'Jamu Mak Yuli')),
            ]);
        });

        Mail::extend('brevo', fn (array $config): TransportInterface => (new BrevoTransportFactory)->create(
            new Dsn('brevo+api', 'default', $config['key'] ?? config('services.brevo.key'))
        ));

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $broker = config('auth.defaults.passwords');

            return (new MailMessage)
                ->subject(__('Atur Ulang Kata Sandi').' | '.setting('site_name', config('app.name')))
                ->view('emails.auth.reset-password', [
                    'url' => rtrim((string) config('app.url'), '/').route('password.reset', [
                        'token' => $token,
                        'email' => $notifiable->getEmailForPasswordReset(),
                    ], false),
                    'email' => $notifiable->getEmailForPasswordReset(),
                    'nama' => $notifiable->name ?? null,
                    'expireMinutes' => (int) config('auth.passwords.'.$broker.'.expire'),
                ]);
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(WishlistService::class)->migrateGuestToUser($event->user);
            }
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
