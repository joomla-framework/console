# Updating from v3 to v4

Release 4.0.0 raises the PHP requirement and moves to Symfony Console 7. This package's own API is
unchanged.

## At a glance

| | v3 (3.0.2) | v4 (4.0.0) |
|---|---|---|
| PHP | `^8.1.0` | `^8.3.0` |
| `symfony/console` | `^6.2` | `^7.0` |
| `symfony/error-handler` | `^6` | `^7` |
| `AbstractCommand::getName()` | untyped | `: string` |

## Minimum supported PHP version raised

All Framework packages now require **PHP 8.3** or newer.

## Symfony Console 7

Symfony 7 removed a number of things deprecated during 6.x. The one that matters here:

**`Command::$defaultName` was removed from Symfony**, in favour of the `#[AsCommand]` attribute.
This package does **not** follow that change — `Joomla\Console\Command\AbstractCommand` reads
`$defaultName` itself, through its own reflection, so it keeps working:

```php
final class MyCommand extends AbstractCommand
{
    protected static $defaultName = 'app:do-something';   // still correct here
}
```

The consequence is the reverse of what Symfony's own documentation suggests: **`#[AsCommand]` is
not read by this package**, so a command annotated that way ends up with no name. Use
`$defaultName` for commands extending `AbstractCommand`.

Note that `AbstractCommand::getDefaultName()` compares the declaring class with the reflected one,
so a subclass does **not** inherit its parent's `$defaultName`. Each concrete command needs its own.

## One signature tightened

`git diff 3.0.2 HEAD -- src/` touches a single line: `AbstractCommand::getName()` gained a `: string`
return type.

```php
// v3
public function getName()

// v4
public function getName(): string
```

Callers are unaffected. A command overriding `getName()` must add the return type, otherwise PHP
refuses to load the class.

`Application`, `Loader\ContainerLoader`, the descriptors and the event classes are unchanged.

## Dependency changes

| Package | v3 (3.0.2) | v4 (4.0.0) |
|---|---|---|
| `php` | `^8.1.0` | `^8.3.0` |
| `symfony/console` | `^6.2` | `^7.0` |
| `symfony/error-handler` | `^6` | `^7` |
| `joomla/application` | `^3.0` | `^4.0` |
| `joomla/event` | `^3.0` | `^4.0` |
| `joomla/string` | `^3.0` | `^4.0` |
