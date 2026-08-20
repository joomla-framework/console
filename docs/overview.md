## Overview

The Console package provides the infrastructure to build and run command line applications within the Joomla! Framework.

### Methodology

The Console package is built very closely modeling the [Symfony Console component](https://symfony.com/components/Console). Symfony provides an
excellent infrastructure however its two main pieces, the base Application class and the base Command class, are largely incompatible with
the Joomla code structure and methodology. Therefore, this package provides a replacement for these core classes and aims to be able
to use the Symfony component API as practical.

### Console Application

Unlike the Application's `AbstractCliApplication` class, this package provides a fully functional and somewhat opinionated `Application` class
which can be used to run `AbstractCommand` implementations. In fact, your application can be bootstrapped with only a few lines of code.

```php
<?php
use Joomla\Console\Application;

$application = new Application;

// Register commands via $application->addCommand();

$application->execute();
```

### Console Commands

The `AbstractCommand` defines the base API for console command classes, largely based on `Symfony\Component\Console\Command\Command`.

When using the `AbstractCommand`, generally two methods are required in your classes to have a functional command:

- `configure` is a hook for configuring the command class, conceptually this is similar to `Symfony\Component\Console\Command\Command::configure()`
- `doExecute` is the method which runs the command's logic, conceptually this is similar to `Symfony\Component\Console\Command\Command::execute()`

The package comes with two commands commonly used in applications:

- `help` to display help information about a command
- `list` to display a list of the available commands

### Lazy Loading Commands

As of Symfony 3.4, Symfony supports lazy loading command classes through a command loader. Our Console package provides a similar feature that can
be registered to the application. Note that this is functionally equivalent to the Symfony component, however since its interface requires returning
`Symfony\Component\Console\Command\Command` objects we have elected to create our own implementation.


```php
<?php
use Joomla\Console\Application;
use Joomla\Console\Loader\ContainerLoader;
use Joomla\DI\Container;

// Note that this can be any PSR-11 container that is able to handle callables
$container = new Container;
$container->set('foo.command', function () {
	return new FooCommand;
});

// The mapping array is an associative array where the keys are the command names and the values are the container service IDs
$mapping = ['foo' => 'foo.command'];

$loader = new ContainerLoader($container, $mapping);

$application = new Application;
$application->setCommandLoader($loader);

// Register non-lazy commands via $application->addCommand();

$application->execute();
```

### Application Events

Similar to Symfony's Console component, the application supports dispatching events at key spots in the process.
A `Joomla\Event\DispatcherInterface` must be injected into the application for events to be dispatched.

```php
<?php
use Joomla\Console\Application;
use Joomla\Console\ConsoleEvents;
use Joomla\Console\Event\ApplicationErrorEvent;
use Joomla\Event\Dispatcher;

$dispatcher = new Dispatcher;

// Sample listener for the ConsoleEvents::ERROR event
$dispatcher->addListener(
	ConsoleEvents::APPLICATION_ERROR,
	function (ApplicationErrorEvent $event)
	{
		$event->getApplication()->getConsoleOutput()->writeln('<comment>Error event triggered.</comment>');
	}
);

$application = new Application;
$application->setDispatcher($dispatcher);

// Register commands via $application->addCommand();

$application->execute();
```

The Application has inbuilt support for four events. In addition to the Application package's `BEFORE_EXECUTE` and `AFTER_EXECUTE` events,
there are several console specific events:

- `ConsoleEvents::BEFORE_COMMAND_EXECUTE` is triggered immediately before executing a command, developers may listen for this event to optionally disable a command at runtime
- `ConsoleEvents::APPLICATION_ERROR` is triggered when the application catches any `Throwable` object that is not caught elsewhere in the application, this can be used to integrate extra error handling/reporting tools
- `ConsoleEvents::COMMAND_ERROR` is triggered when the application catches any `Throwable` object directly from a command, this can be used to integrate extra error handling/reporting tools
- `ConsoleEvents::TERMINATE` is triggered immediately before the process is completed, developers may listen for this event to perform any actions required at the end of the process

## Things to know before you build on this

**Use `$defaultName`, not `#[AsCommand]`.** Symfony removed the static property in 7.0, but
`AbstractCommand` reads it through its own reflection and does not read the attribute. A command
annotated with `#[AsCommand]` therefore has no name here.

`getDefaultName()` also checks that the property is declared on the class being asked, so a
subclass does not inherit the name of its parent — give every concrete command its own.

**There is no signal handling.** A `SIGTERM` during a long-running command — a deployment, a
supervisor restart — kills the process wherever it happens to be. Add it yourself for anything that
runs longer than a moment:

```php
protected function doExecute(InputInterface $input, OutputInterface $output): int
{
    $running = true;

    if (\function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static function () use (&$running) { $running = false; });
        pcntl_signal(SIGINT, static function () use (&$running) { $running = false; });
    }

    while ($running) {
        // one unit of work, then re-check
    }

    return 0;
}
```

**There is no execution lock.** Nothing prevents two instances of the same command running at once,
which matters for cron-driven commands that take longer than their interval:

```php
$lock = fopen(sys_get_temp_dir() . '/my-command.lock', 'c');

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    $output->writeln('Another instance is running.');

    return 0;
}
```

**`ContainerLoader` cannot distinguish "unknown command" from "unregistered service".** Its
`has()` requires both a map entry and a container entry, so a command listed in the map whose
service was never registered reports "Command … does not exist." Check the container registration
when a command you added does not appear.

**`Application` is a reimplementation, not a subclass.** It reproduces Symfony's `Application`
rather than extending it, so fixes Symfony makes to command resolution, error rendering or the
handler juggling do not arrive automatically.
