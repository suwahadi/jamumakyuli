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

        View::composer('emails.*', function ($view): void {
            $view->with([
                'accent' => '#2F7D32',
                'accentDark' => '#1F5A23',
                'dark' => '#0F2A14',
                'ink' => '#0b0b0c',
                'muted' => '#6b7280',
                'line' => '#e5e7eb',
                'cream' => '#f9fafb',
                'creamDeep' => '#f5f5f5',

                'successBg' => '#ecfdf5',
                'successBorder' => '#a7f3d0',
                'success' => '#047857',
                'successInk' => '#065f46',
                'successInkSoft' => '#047857',

                'warningBg' => '#fffbeb',
                'warningBorder' => '#fde68a',
                'warning' => '#f59e0b',
                'warningInk' => '#92400e',

                'dangerBg' => '#fef2f2',
                'dangerBorder' => '#fecaca',
                'danger' => '#dc2626',
                'dangerInk' => '#991b1b',
                'dangerInkSoft' => '#b91c1c',

                'infoBg' => '#eff6ff',
                'infoBorder' => '#bfdbfe',
                'infoInk' => '#1e40af',
                'infoInkSoft' => '#1d4ed8',
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
