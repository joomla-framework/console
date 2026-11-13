<?php

/**
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Console\Tests\Event;

use Joomla\Console\Application;
use Joomla\Console\Event\ApplicationErrorEvent;
use Joomla\Console\Event\CommandErrorEvent;
use Joomla\Console\Event\ConsoleEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Test class for the exit code handling of \Joomla\Console\Event\ApplicationErrorEvent and \Joomla\Console\Event\CommandErrorEvent
 */
#[CoversClass(ApplicationErrorEvent::class)]
#[CoversClass(CommandErrorEvent::class)]
#[UsesClass(ConsoleEvent::class)]
#[UsesClass(Application::class)]
class ApplicationErrorEventTest extends TestCase
{
    /**
     * @return  array
     */
    public static function dataEventClasses(): array
    {
        return [
            'ApplicationErrorEvent' => [ApplicationErrorEvent::class],
            'CommandErrorEvent'     => [CommandErrorEvent::class],
        ];
    }

    #[DataProvider('dataEventClasses')]
    public function testTheExitCodeDefaultsToTheErrorCode(string $class)
    {
        $event = new $class(new \RuntimeException('Testing', 42), new Application());

        $this->assertSame(42, $event->getExitCode());
    }

    #[DataProvider('dataEventClasses')]
    public function testTheExitCodeIsOneForAnErrorWithoutCode(string $class)
    {
        $event = new $class(new \RuntimeException('Testing'), new Application());

        $this->assertSame(1, $event->getExitCode());
    }

    #[DataProvider('dataEventClasses')]
    public function testTheExitCodeCanBeSet(string $class)
    {
        $event = new $class(new \RuntimeException('Testing', 42), new Application());
        $event->setExitCode(3);

        $this->assertSame(3, $event->getExitCode());
    }

    #[DataProvider('dataEventClasses')]
    public function testTheExitCodeCanBeSetToZero(string $class)
    {
        $event = new $class(new \RuntimeException('Testing', 42), new Application());
        $event->setExitCode(0);

        $this->assertSame(0, $event->getExitCode());
    }
}
