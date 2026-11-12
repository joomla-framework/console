<?php

/**
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Console\Tests\Command;

use Joomla\Console\Application;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Console\Tests\Fixtures\Command\AttributeCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test class for \Joomla\Console\Command\AbstractCommand
 */
#[CoversClass(AbstractCommand::class)]
#[UsesClass(Application::class)]
class AbstractCommandTest extends TestCase
{
    public function testTheCommandIsExecutedWithoutAnApplication()
    {
        $command = new class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $input  = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $this->assertSame(0, $command->execute($input, $output));
    }

    public function testTheCommandIsExecutedWithAnApplication()
    {
        $command = new class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $input  = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $application = new Application($input, $output);

        $command->setApplication($application);

        $this->assertSame(0, $command->execute($input, $output));
    }

    public function testArgumentsAreAddedToTheDefinition()
    {
        $command = new class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame($command, $command->addArgument('test'), 'addArgument has a fluent interface');
        $this->assertTrue($command->getDefinition()->hasArgument('test'));
    }

    public function testOptionsAreAddedToTheDefinition()
    {
        $command = new class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame($command, $command->addOption('test'), 'addOption has a fluent interface');
        $this->assertTrue($command->getDefinition()->hasOption('test'));
    }

    public function testTheDefaultCommandNameIsRetrieved()
    {
        $command = new class () extends AbstractCommand {
            protected static $defaultName = 'test:command';

            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame('test:command', $command::getDefaultName());
    }

    public function testTheDefaultCommandNameIsReadFromTheAsCommandAttribute()
    {
        $command = new #[AsCommand(name: 'test:attribute', description: 'Attribute description', aliases: ['test:attr'])]
        class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame('test:attribute', $command::getDefaultName());
        $this->assertSame('test:attribute', $command->getName());
        $this->assertSame(['test:attr'], $command->getAliases());
        $this->assertSame('Attribute description', $command->getDescription());
        $this->assertFalse($command->isHidden());
    }

    public function testTheHelpIsReadFromTheAsCommandAttribute()
    {
        if (!property_exists(AsCommand::class, 'help')) {
            $this->markTestSkipped('The help argument of #[AsCommand] requires Symfony 7.4 or later.');
        }

        $command = new #[AsCommand(name: 'test:attribute', help: 'Attribute help')]
        class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame('Attribute help', $command->getHelp());
    }

    public function testTheAsCommandAttributeCanHideTheCommand()
    {
        $command = new #[AsCommand(name: 'test:hidden', aliases: ['test:secret'], hidden: true)]
        class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame('test:hidden', $command::getDefaultName());
        $this->assertSame('test:hidden', $command->getName());
        $this->assertSame(['test:secret'], $command->getAliases());
        $this->assertTrue($command->isHidden());
    }

    public function testTheDefaultNamePropertyTakesPrecedenceOverTheAsCommandAttribute()
    {
        $command = new #[AsCommand(name: 'test:attribute|test:attr', description: 'Attribute description')]
        class () extends AbstractCommand {
            protected static $defaultName = 'test:property';

            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame('test:property', $command::getDefaultName());
        $this->assertSame('test:property', $command->getName());
        $this->assertSame([], $command->getAliases());
        $this->assertSame('Attribute description', $command->getDescription());
    }

    public function testTheConfigureMethodOverridesTheAsCommandAttribute()
    {
        $command = new #[AsCommand(name: 'test:attribute', description: 'Attribute description')]
        class () extends AbstractCommand {
            protected function configure(): void
            {
                $this->setDescription('Configured description');
            }

            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $this->assertSame('Configured description', $command->getDescription());
    }

    public function testAnExplicitNameTakesPrecedenceOverTheAsCommandAttribute()
    {
        $class = new #[AsCommand(name: 'test:attribute', aliases: ['test:attr'])]
        class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $command = new $class('test:explicit');

        $this->assertSame('test:explicit', $command->getName());
        $this->assertSame([], $command->getAliases());
    }

    public function testTheAsCommandAttributeIsNotInherited()
    {
        $child = new class () extends AttributeCommand {
        };

        $this->assertSame('test:attribute', AttributeCommand::getDefaultName());
        $this->assertNull($child::getDefaultName());
        $this->assertNull($child->getName());
    }

    public function testTheCommandHelpIsProcessed()
    {
        $command = new class () extends AbstractCommand {
            protected static $defaultName = 'test:command';

            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }

            public function getHelp(): string
            {
                return 'The %command.name% command is used... Example: php %command.full_name%';
            }
        };

        $this->assertStringContainsString('The test:command command is used...', $command->getProcessedHelp(), 'getProcessedHelp() replaces %command.name%');
        $this->assertStringNotContainsString('%command.full_name%', $command->getProcessedHelp(), 'getProcessedHelp() replaces %command.full_name%');
    }

    public function testTheCommandSynopsisIsProcessed()
    {
        $command = new class () extends AbstractCommand {
            protected static $defaultName = 'test:command';

            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $command->addOption('foo');
        $command->addArgument('bar');

        $this->assertSame('test:command [--foo] [--] [<bar>]', $command->getSynopsis());
    }

    public function testTheApplicationInputDefinitionIsMergedWithTheCommand()
    {
        $command = new class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $input  = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $application = new Application($input, $output);

        $application->getDefinition()->addArguments([new InputArgument('foo')]);
        $application->getDefinition()->addOptions([new InputOption('bar')]);

        $command->setApplication($application);
        $command->setDefinition($definition = new InputDefinition([new InputArgument('bar'), new InputOption('foo')]));

        $command->mergeApplicationDefinition();

        $this->assertTrue($command->getDefinition()->hasArgument('foo'));
        $this->assertTrue($command->getDefinition()->hasArgument('bar'));
        $this->assertTrue($command->getDefinition()->hasOption('foo'));
        $this->assertTrue($command->getDefinition()->hasOption('bar'));

        $command->mergeApplicationDefinition();

        $this->assertSame(
            3,
            $command->getDefinition()->getArgumentCount(),
            'mergeApplicationDefinition() does not try to merge the application arguments and options multiple times'
        );
    }

    public function testTheArgumentsOfTheApplicationInputDefinitionAreNotMergedWithTheCommandUntilInstructed()
    {
        $command = new class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $input  = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $application = new Application($input, $output);

        $application->getDefinition()->addArguments([new InputArgument('foo')]);
        $application->getDefinition()->addOptions([new InputOption('bar')]);

        $command->setApplication($application);
        $command->setDefinition($definition = new InputDefinition([new InputArgument('bar'), new InputOption('foo')]));

        $command->mergeApplicationDefinition(false);

        $this->assertFalse($command->getDefinition()->hasArgument('foo'));
        $this->assertTrue($command->getDefinition()->hasArgument('bar'));
        $this->assertTrue($command->getDefinition()->hasOption('foo'));
        $this->assertTrue($command->getDefinition()->hasOption('bar'));

        $command->mergeApplicationDefinition(true);

        $this->assertTrue($command->getDefinition()->hasArgument('foo'));
    }

    public function testTheApplicationHelperSetIsMergedToTheCommand()
    {
        $command = new class () extends AbstractCommand {
            protected function doExecute(InputInterface $input, OutputInterface $output): int
            {
                return 0;
            }
        };

        $input  = $this->createStub(InputInterface::class);
        $output = $this->createStub(OutputInterface::class);

        $application = new Application($input, $output);

        $command->setApplication($application);

        $this->assertSame($command->getHelperSet(), $application->getHelperSet());
    }
}
