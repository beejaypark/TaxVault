<?php

namespace App\Providers;

use App\Application\OCR\Contracts\OcrProvider;
use App\Infrastructure\OCR\Providers\HttpOcrProvider;
use App\Infrastructure\OCR\Providers\TestOcrProvider;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            OcrProvider::class,
            function () {
                return match (config('ocr.provider', 'test')) {
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
        //
    }
}