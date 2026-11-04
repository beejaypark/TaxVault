<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Container environment variables override phpunit.xml, and RefreshDatabase
    // wipes the whole schema, so refuse to run against a non-test database.
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $database = (string) $app['config']->get(
            'database.connections.'.$app['config']->get('database.default').'.database'
        );

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against database "%s". Run: docker compose exec -e DB_HOST=postgres -e DB_PORT=5432 -e DB_DATABASE=taxvault_test backend php artisan test',
                $database,
            ));
        }

        return $app;
    }
}
