<?php

namespace Tests\Unit\Providers;

use App\Application\OCR\Contracts\OcrProvider;
use App\Infrastructure\OCR\Providers\HttpOcrProvider;
use App\Infrastructure\OCR\Providers\TestOcrProvider;
use InvalidArgumentException;
use Tests\TestCase;

class AppServiceProviderOcrBindingTest extends TestCase
{
    public function test_test_provider_is_resolved_when_configured(): void
    {
        config([
            'ocr.provider' => 'test',
        ]);

        $provider = app(OcrProvider::class);

        $this->assertInstanceOf(
            TestOcrProvider::class,
            $provider
        );
    }

    public function test_http_provider_is_resolved_when_configured(): void
    {
        config([
            'ocr.provider' => 'http',
        ]);

        $provider = app(OcrProvider::class);

        $this->assertInstanceOf(
            HttpOcrProvider::class,
            $provider
        );
    }

    public function test_unsupported_provider_configuration_is_rejected(): void
    {
        config([
            'ocr.provider' => 'unsupported-provider',
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->expectExceptionMessage(
            'Unsupported OCR provider: unsupported-provider'
        );

        app(OcrProvider::class);
    }
}
