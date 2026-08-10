<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Console\Tests\Command;

use Joomla\Console\Application;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Console\Command\HelpCommand;
use Joomla\Console\Command\ListCommand;
use Joomla\Console\Descriptor\TextDescriptor;
use Joomla\Console\Helper\DescriptorHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test class for \Joomla\Console\Command\HelpCommand
 */
#[CoversClass(HelpCommand::class)]
#[UsesClass(Application::class)]
#[UsesClass(AbstractCommand::class)]
#[UsesClass(ListCommand::class)]
#[UsesClass(TextDescriptor::class)]
#[UsesClass(DescriptorHelper::class)]
class HelpCommandTest extends TestCase
{
    public function testTheCommandIsExecutedWithACommandName()
    {
        $input  = new ArrayInput(
            [
                'command'      => 'help',
                'command_name' => 'list',
            ]
        );
        $output = new BufferedOutput();

        $application = new Application($input, $output);

        $command = new HelpCommand();
        $command->setApplication($application);

        $this->assertSame(0, $command->execute($input, $output));

        $screenOutput = $output->fetch();
        $this->assertStringContainsString('list [<namespace>]', $screenOutput);
    }

    public function testTheCommandIsExecutedWithACommandClass()
    {
        $input  = new ArrayInput(
            [
                'command' => 'help',
            ]
        );
        $output = new BufferedOutput();

        $application = new Application($input, $output);

        $command = new HelpCommand();
        $command->setApplication($application);
        $command->setCommand(new ListCommand());

        $this->assertSame(0, $command->execute($input, $output));

        $screenOutput = $output->fetch();
        $this->assertStringContainsString('list [<namespace>]', $screenOutput);
    }
}
