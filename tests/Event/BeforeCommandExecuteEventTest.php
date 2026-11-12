<?php

/**
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Console\Tests\Event;

use Joomla\Console\Application;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Console\Event\BeforeCommandExecuteEvent;
use Joomla\Console\Event\ConsoleEvent;
use Joomla\Console\Tests\Fixtures\Command\NamespacedCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Test class for \Joomla\Console\Event\BeforeCommandExecuteEvent
 */
#[CoversClass(BeforeCommandExecuteEvent::class)]
#[UsesClass(ConsoleEvent::class)]
#[UsesClass(Application::class)]
#[UsesClass(AbstractCommand::class)]
class BeforeCommandExecuteEventTest extends TestCase
{
    public function testTheCommandIsEnabledByDefault()
    {
        $event = new BeforeCommandExecuteEvent(new Application(), new NamespacedCommand());

        $this->assertTrue($event->isCommandEnabled());
    }

    public function testTheCommandCanBeDisabled()
    {
        $event = new BeforeCommandExecuteEvent(new Application(), new NamespacedCommand());
        $event->disableCommand();

        $this->assertFalse($event->isCommandEnabled());
    }

    public function testTheCommandCanBeEnabledAgain()
    {
        $event = new BeforeCommandExecuteEvent(new Application(), new NamespacedCommand());
        $event->disableCommand();
        $event->enableCommand();

        $this->assertTrue($event->isCommandEnabled());
    }
}
