<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Runs after the application boots and before RefreshDatabase can migrate.
     *
     * @return array<string, class-string>
     */
    protected function setUpTraits()
    {
        $this->abortIfLiveDatabase();

        return parent::setUpTraits();
    }

    private function abortIfLiveDatabase(): void
    {
        $name = (string) $this->app['db']->connection()->getDatabaseName();

        if (strcasecmp($name, 'ecw_hrmis') === 0) {
            $this->fail('Refusing to run tests against the live database ecw_hrmis.');
        }
    }
}
