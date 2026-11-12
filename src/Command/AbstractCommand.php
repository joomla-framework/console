<?php

/**
 * Part of the Joomla Framework Console Package
 *
 * @copyright  Copyright (C) 2005 - 2021 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Console\Command;

use Joomla\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command class for a Joomla! command line application.
 *
 * @since  2.0.0
 */
abstract class AbstractCommand
{
    /**
     * The default command name
     *
     * @var    string|null
     * @since  2.0.0
     *
     * @deprecated  5.0 Use the #[AsCommand] attribute instead
     */
    protected static $defaultName;

    /**
     * The command's aliases.
     *
     * @var    string[]
     * @since  2.0.0
     */
    private array $aliases = [];

    /**
     * The application running this command.
     *
     * @var    Application|null
     * @since  2.0.0
     */
    private ?Application $application = null;

    /**
     * Flag tracking whether the application definition has been merged to this command.
     *
     * @var    boolean
     * @since  2.0.0
     */
    private bool $applicationDefinitionMerged = false;

    /**
     * Flag tracking whether the application definition with arguments has been merged to this command.
     *
     * @var    boolean
     * @since  2.0.0
     */
    private bool $applicationDefinitionMergedWithArgs = false;

    /**
     * Per-class cache of whether the class declares `$defaultName` and of its `#[AsCommand]` attribute.
     *
     * @var    array<string, array{declaresDefaultName: bool, attribute: AsCommand|null}>
     * @since  __DEPLOY_VERSION__
     */
    private static $classMetadata = [];

    /**
     * The command's input definition.
     *
     * @var    InputDefinition
     * @since  2.0.0
     */
    private ?InputDefinition $definition = null;

    /**
     * The command's description.
     *
     * @var    string
     * @since  2.0.0
     */
    private string $description = '';

    /**
     * The command's help.
     *
     * @var    string
     * @since  2.0.0
     */
    private string $help = '';

    /**
     * The command's input helper set.
     *
     * @var    HelperSet|null
     * @since  2.0.0
     */
    private ?HelperSet $helperSet = null;

    /**
     * Flag tracking whether the command is hidden from the command listing.
     *
     * @var    boolean
     * @since  2.0.0
     */
    private bool $hidden = false;

    /**
     * The command's name.
     *
     * @var    string
     * @since  2.0.0
     */
    private ?string $name = null;

    /**
     * The command's synopses.
     *
     * @var    string[]
     * @since  2.0.0
     */
    private array $synopsis = [];

    /**
     * Command constructor.
     *
     * @param   string|null  $name  The name of the command; if the name is empty and no default is set, a name must be set in the configure() method
     *
     * @since   2.0.0
     */
    public function __construct(?string $name = null)
    {
        $this->definition = new InputDefinition();

        $metadata = self::getClassMetadata(static::class);

        if ($name === null && null !== $name = static::getDefaultName()) {
            // A name read from the #[AsCommand] attribute may carry aliases and the hidden flag: "|name|alias"
            if ($metadata['attribute'] !== null && !$metadata['declaresDefaultName']) {
                $aliases = explode('|', $metadata['attribute']->name);

                if ($aliases[0] === '') {
                    $this->setHidden(true);
                    array_shift($aliases);
                }

                array_shift($aliases);
                $this->setAliases($aliases);
            }
        }

        if ($name !== null) {
            $this->setName($name);
        }

        if ($metadata['attribute'] !== null) {
            if ($metadata['attribute']->description !== null) {
                $this->setDescription($metadata['attribute']->description);
            }

            // The help property was added in Symfony 7.4
            if (isset($metadata['attribute']->help)) {
                $this->setHelp($metadata['attribute']->help);
            }
        }

        $this->configure();
    }

    /**
     * Adds an argument to the input definition.
     *
     * @param   string    $name         The argument name
     * @param   ?integer  $mode         The argument mode: InputArgument::REQUIRED or InputArgument::OPTIONAL
     * @param   string    $description  A description text
     * @param   mixed     $default      The default value (for InputArgument::OPTIONAL mode only)
     *
     * @return  $this
     *
     * @since   2.0.0
     */
    public function addArgument(string $name, ?int $mode = null, string $description = '', mixed $default = null): self
    {
        $this->definition->addArgument(new InputArgument($name, $mode, $description, $default));

        return $this;
    }

    /**
     * Adds an option to the input definition.
     *
     * @param   string             $name         The option name
     * @param   string|array|null  $shortcut     The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param   ?integer           $mode         The option mode: One of the VALUE_* constants
     * @param   string             $description  A description text
     * @param   mixed              $default      The default value (must be null for InputOption::VALUE_NONE)
     *
     * @return  $this
     *
     * @since   2.0.0
     */
    public function addOption(string $name, string|array|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null): self
    {
        $this->definition->addOption(new InputOption($name, $shortcut, $mode, $description, $default));

        return $this;
    }

    /**
     * Configure the command.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    protected function configure(): void
    {
    }

    /**
     * Internal function to execute the command.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  integer  The command exit code
     *
     * @since   2.0.0
     */
    abstract protected function doExecute(InputInterface $input, OutputInterface $output): int;

    /**
     * Executes the command.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  integer  The command exit code
     *
     * @since   2.0.0
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // Force the creation of the synopsis before the merge with the app definition
        $this->getSynopsis(true);
        $this->getSynopsis(false);

        // Add the application arguments and options
        $this->mergeApplicationDefinition();

        // Bind the input against the command specific arguments/options
        $input->bind($this->getDefinition());

        $this->initialise($input, $output);

        // Ensure that the command has a `command` argument so it does not fail validation
        if ($input->hasArgument('command') && $input->getArgument('command') === null) {
            $input->setArgument('command', $this->getName());
        }

        $input->validate();

        return $this->doExecute($input, $output);
    }

    /**
     * Get the command's aliases.
     *
     * @return  string[]
     *
     * @since   2.0.0
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }

    /**
     * Get the application object.
     *
     * @return  Application  The application object.
     *
     * @since   2.0.0
     * @throws  \UnexpectedValueException if the application has not been set.
     */
    public function getApplication(): Application
    {
        if ($this->application) {
            return $this->application;
        }

        throw new \UnexpectedValueException('Application not set in ' . \get_class($this));
    }

    /**
     * Get the default command name for this class.
     *
     * This allows a command name to defined and referenced without instantiating the full command class.
     *
     * The name is read from the static `$defaultName` property if the class declares it, otherwise from the class' `#[AsCommand]`
     * attribute. Neither is inherited from a parent class.
     *
     * @return  string|null
     *
     * @since  2.0.0
     */
    public static function getDefaultName(): ?string
    {
        $metadata = self::getClassMetadata(static::class);

        if ($metadata['declaresDefaultName']) {
            // @phpstan-ignore staticProperty.deprecated
            $defaultName = static::$defaultName;

            if ($defaultName !== null) {
                trigger_deprecation(
                    'joomla/console',
                    '__DEPLOY_VERSION__',
                    'Naming a command with the static $defaultName property is deprecated and will not be supported in 5.0, add the %s attribute to %s instead.',
                    AsCommand::class,
                    static::class
                );
            }

            return $defaultName;
        }

        if ($metadata['attribute'] === null) {
            return null;
        }

        // Strip the aliases and the hidden flag from the attribute's name
        return explode('|', ltrim($metadata['attribute']->name, '|'))[0];
    }

    /**
     * Gets the InputDefinition attached to this command.
     *
     * @return  InputDefinition
     *
     * @since   2.0.0
     */
    public function getDefinition(): InputDefinition
    {
        return $this->definition;
    }

    /**
     * Get the command's description.
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the command's help.
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * Get the command's input helper set.
     *
     * @return  HelperSet|null
     *
     * @since   2.0.0
     */
    public function getHelperSet(): ?HelperSet
    {
        return $this->helperSet;
    }

    /**
     * Get the command's name.
     *
     * @return  string|null
     *
     * @since   2.0.0
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Returns the processed help for the command.
     *
     * This method is used to replace placeholders in commands with the real values.
     * By default, this supports `%command.name%` and `%command.full_name`.
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getProcessedHelp(): string
    {
        $name = $this->getName();

        $placeholders = [
            '%command.name%',
            '%command.full_name%',
        ];

        $replacements = [
            $name,
            $_SERVER['PHP_SELF'] . ' ' . $name,
        ];

        return str_replace($placeholders, $replacements, $this->getHelp() ?: $this->getDescription());
    }

    /**
     * Get the command's synopsis.
     *
     * @param   boolean  $short  Flag indicating whether the short or long version of the synopsis should be returned
     *
     * @return  string
     *
     * @since   2.0.0
     */
    public function getSynopsis(bool $short = false): string
    {
        $key = $short ? 'short' : 'long';

        if (!isset($this->synopsis[$key])) {
            $this->synopsis[$key] = trim(sprintf('%s %s', $this->getName(), $this->getDefinition()->getSynopsis($short)));
        }

        return $this->synopsis[$key];
    }

    /**
     * Internal hook to initialise the command after the input has been bound and before the input is validated.
     *
     * @param   InputInterface   $input   The input to inject into the command.
     * @param   OutputInterface  $output  The output to inject into the command.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    protected function initialise(InputInterface $input, OutputInterface $output): void
    {
    }

    /**
     * Check if the command is enabled in this environment.
     *
     * @return  boolean
     *
     * @since   2.0.0
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Check if the command is hidden from the command listing.
     *
     * @return  boolean
     *
     * @since   2.0.0
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Merges the application definition with the command definition.
     *
     * @param   boolean  $mergeArgs  Flag indicating whether the application's definition arguments should be merged
     *
     * @return  void
     *
     * @since   2.0.0
     * @internal  This method should not be relied on as part of the public API
     */
    final public function mergeApplicationDefinition(bool $mergeArgs = true): void
    {
        if (!$this->application || ($this->applicationDefinitionMerged && ($this->applicationDefinitionMergedWithArgs || !$mergeArgs))) {
            return;
        }

        $this->getDefinition()->addOptions($this->getApplication()->getDefinition()->getOptions());

        $this->applicationDefinitionMerged = true;

        if ($mergeArgs) {
            $currentArguments = $this->getDefinition()->getArguments();
            $this->getDefinition()->setArguments($this->getApplication()->getDefinition()->getArguments());
            $this->getDefinition()->addArguments($currentArguments);

            $this->applicationDefinitionMergedWithArgs = true;
        }
    }

    /**
     * Set the command's aliases.
     *
     * @param   string[]  $aliases  The command aliases
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setAliases(array $aliases): void
    {
        $this->aliases = $aliases;
    }

    /**
     * Set the command's application.
     *
     * @param   ?Application  $application  The command's application
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setApplication(?Application $application = null): void
    {
        $this->application = $application;

        if ($application) {
            $this->setHelperSet($application->getHelperSet());
        } else {
            $this->helperSet = null;
        }
    }

    /**
     * Sets the input definition for the command.
     *
     * @param   array|InputDefinition  $definition  Either an InputDefinition object or an array of objects to write to the definition.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setDefinition(array|InputDefinition $definition): void
    {
        if ($definition instanceof InputDefinition) {
            $this->definition = $definition;
        } else {
            $this->definition->setDefinition($definition);
        }

        $this->applicationDefinitionMerged = false;
    }

    /**
     * Sets the description for the command.
     *
     * @param   string  $description  The description for the command
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * Sets the help for the command.
     *
     * @param   string  $help  The help for the command
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setHelp(string $help): void
    {
        $this->help = $help;
    }

    /**
     * Set the command's input helper set.
     *
     * @param   HelperSet  $helperSet  The helper set.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setHelperSet(HelperSet $helperSet): void
    {
        $this->helperSet = $helperSet;
    }

    /**
     * Set whether this command is hidden from the command listing.
     *
     * @param   boolean  $hidden  Flag if this command is hidden.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }

    /**
     * Set the command's name.
     *
     * @param   string  $name  The command name
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the cached reflection data of a command class.
     *
     * @param   string  $class  The command class
     *
     * @return  array{declaresDefaultName: bool, attribute: AsCommand|null}
     *
     * @since   __DEPLOY_VERSION__
     */
    private static function getClassMetadata(string $class): array
    {
        if (!isset(self::$classMetadata[$class])) {
            $attributes = (new \ReflectionClass($class))->getAttributes(AsCommand::class);

            self::$classMetadata[$class] = [
                'declaresDefaultName' => (new \ReflectionProperty($class, 'defaultName'))->class === $class,
                'attribute'           => $attributes ? $attributes[0]->newInstance() : null,
            ];
        }

        return self::$classMetadata[$class];
    }
}
