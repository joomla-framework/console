<?php

/**
 * @copyright  Copyright (C) 2005 - 2026 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Console\Tests\Descriptor;

use Joomla\Console\Application;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Console\Command\HelpCommand;
use Joomla\Console\Command\ListCommand;
use Joomla\Console\Descriptor\ApplicationDescription;
use Joomla\Console\Tests\Fixtures\Command\AliasedCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Test class for \Joomla\Console\Descriptor\ApplicationDescription
 */
#[CoversClass(ApplicationDescription::class)]
#[UsesClass(Application::class)]
#[UsesClass(AbstractCommand::class)]
#[UsesClass(HelpCommand::class)]
#[UsesClass(ListCommand::class)]
class ApplicationDescriptionTest extends TestCase
{
    public function testGetCommandWithoutPriorInspection()
    {
        $description = new ApplicationDescription(new Application());

        $this->assertInstanceOf(ListCommand::class, $description->getCommand('list'));
    }

    public function testGetCommandByAliasWithoutPriorInspection()
    {
        $application = new Application();
        $application->addCommand(new AliasedCommand());

        $description = new ApplicationDescription($application);

        $this->assertInstanceOf(AliasedCommand::class, $description->getCommand('test:alias'));
    }

    public function testGetCommandForUnknownCommand()
    {
        $this->expectException(CommandNotFoundException::class);
        $this->expectExceptionMessage('Command foo does not exist.');

        (new ApplicationDescription(new Application()))->getCommand('foo');
    }

    public function testGetCommandForApplicationWithoutAliases()
    {
        $description = new ApplicationDescription(new Application());
        $description->getCommands();

        $this->assertInstanceOf(HelpCommand::class, $description->getCommand('help'));
    }
}
