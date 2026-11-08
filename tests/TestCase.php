<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A trilha de auditoria dos testes não vai para storage/logs. Quem testa a auditoria troca o canal.
        config(['logging.channels.auditoria' => ['driver' => 'monolog', 'handler' => NullHandler::class]]);
    }
}
