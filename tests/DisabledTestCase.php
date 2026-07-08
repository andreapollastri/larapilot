<?php

declare(strict_types=1);

namespace Larapilot\Tests;

abstract class DisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('larapilot.enabled', false);
    }
}
