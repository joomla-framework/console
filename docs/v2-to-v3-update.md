# Updating from v2 to v3

Release 3.0.0 raises the PHP requirement and moves to Symfony Console 6. **No public or protected
method signature changed** in this package, but the Symfony jump brings its own requirements.

## At a glance

| | v2 (2.0.x) | v3 (3.0.0) |
|---|---|---|
| PHP | `^7.2.5` | `^8.1.0` |
| `symfony/console` | `^3.4 \| ^4.4 \| ^5.0` | `^6.2` |
| `symfony/error-handler` | — | `^6` (new) |
| This package's API | — | unchanged |

## Minimum supported PHP version raised

All Framework packages now require **PHP 8.1** or newer.

## Symfony Console 6

The jump from 5.x to 6.x is the substantive part of this upgrade. Symfony 6 added return types
throughout, which affects your command classes:

```php
// Symfony 5 style
protected function configure()
protected function execute(InputInterface $input, OutputInterface $output)

// Symfony 6
protected function configure(): void
protected function execute(InputInterface $input, OutputInterface $output): int
```

In this package commands extend `Joomla\Console\Command\AbstractCommand` and implement
`doExecute(InputInterface $input, OutputInterface $output): int`, which already carried the return
type — so commands written against `AbstractCommand` need no change. Commands extending Symfony's
own `Command` directly do.

`symfony/error-handler` became a direct dependency; it renders uncaught throwables.

## No API changes in this package

`Application`, `AbstractCommand`, the loader, the descriptors, the helper and the event classes have
the same signatures in 3.0.0 as in 2.0.0.

## Dependency changes

| Package | v2 (2.0.x) | v3 (3.0.0) |
|---|---|---|
| `php` | `^7.2.5` | `^8.1.0` |
| `symfony/console` | `^3.4 \| ^4.4 \| ^5.0` | `^6.2` |
| `symfony/error-handler` | — | `^6` |
| `joomla/application` | `^2.0` | `^3.0` |
| `joomla/event` | `^2.0` | `^3.0` |
| `joomla/string` | `^2.0` | `^3.0` |
