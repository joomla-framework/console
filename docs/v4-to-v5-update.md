# Updating from v4 to v5

Release 5.0.0 removes what 4.x deprecated and tightens signatures. This page lists the changes that can
break code written for 4.x.

## Native types

Parameters and return values that were only typed in doc blocks now have native types. Callers passing
the documented types are unaffected; classes that implement or override these methods must use the
same signatures, otherwise PHP refuses to load them.

| Method | v4 | v5 |
|---|---|---|
| `Application::execute()` | `execute()` | `execute(): void` |
| `Command\AbstractCommand::addArgument()` | `$default = null` | `mixed $default = null` |
| `Command\AbstractCommand::addOption()` | `$shortcut = null, …, $description = '', $default = null` | `string\|array\|null $shortcut = null, …, string $description = '', mixed $default = null` |
| `Command\AbstractCommand::setDefinition()` | `setDefinition($definition)` | `setDefinition(array\|InputDefinition $definition)` |
| `Helper\DescriptorHelper::describe()` | `describe(OutputInterface $output, $object, array $options = [])` | `describe(OutputInterface $output, object $object, array $options = []): void` |
| `Loader\LoaderInterface::has()` | `has($name): bool` | `has(string $name): bool` |
| `Loader\ContainerLoader::has()` | `has($name): bool` | `has(string $name): bool` |

An application class overriding `execute()` needs the `: void` return type, and a custom command loader
needs the `string` type on `has()`.

All private properties are typed as well. `AbstractCommand::$defaultName` is left untyped, since
commands redeclare it.
