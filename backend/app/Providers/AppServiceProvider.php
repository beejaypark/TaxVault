<?php

namespace App\Providers;

use App\Application\Audit\AuditLogger;
use App\Infrastructure\Storage\EncryptingAdapter;
use App\Infrastructure\Storage\FileCipher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use App\Application\OCR\Contracts\OcrProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Infrastructure\OCR\Providers\HttpOcrProvider;
use App\Infrastructure\OCR\Providers\LocalOcrProvider;
use App\Infrastructure\OCR\Providers\TestOcrProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Support\ViewerContext::class);
        $this->app->bind(
            OcrProvider::class,
            function () {
                return match (config('ocr.provider', 'local')) {
                    'local' => app(LocalOcrProvider::class),
                    'test' => app(TestOcrProvider::class),
                    'http' => app(HttpOcrProvider::class),
                    default => throw new InvalidArgumentException(
                        sprintf(
                            'Unsupported OCR provider: %s',
                            config('ocr.provider'),
                        ),
                    ),
                };
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('encrypted-local', function ($app, array $config) {
            $adapter = new EncryptingAdapter(
                new LocalFilesystemAdapter($config['root']),
                FileCipher::fromConfig(),
            );

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });

        Event::listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event): void {
            $event->extendSocialite('keycloak', KeycloakProvider::class);
        });

        Event::listen(Login::class, function (Login $event): void {
            app(AuditLogger::class)->record($event->user, 'auth.login', 'user', (string) $event->user->getAuthIdentifier());
        });
        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user !== null) {
                app(AuditLogger::class)->record($event->user, 'auth.logout', 'user', (string) $event->user->getAuthIdentifier());
            }
        });
    }
}