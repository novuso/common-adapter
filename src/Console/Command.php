<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Console;

use Novuso\System\Exception\DomainException;
use Novuso\System\Exception\RuntimeException;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Class Command
 */
abstract class Command extends BaseCommand
{
    protected InputInterface $input;
    protected SymfonyStyle $output;
    protected SymfonyStyle $stderr;
    protected string $name;
    protected string $description;

    private ?ProgressBar $progressBar = null;

    /**
     * Constructs Command
     *
     * @throws DomainException When command is missing name and/or description
     */
    public function __construct()
    {
        if (!is_string($this->name) && !is_string(static::$defaultName)) {
            $message = sprintf(
                'Console commands require protected name property to be a string; missing in %s',
                static::class
            );
            throw new DomainException($message);
        }

        if (!is_string($this->name) && is_string(static::$defaultName)) {
            $this->name = static::$defaultName;
        }

        if ($this->description === null) {
            $message = sprintf(
                'Console commands require protected description property to be a string; missing in %s',
                static::class
            );
            throw new DomainException($message);
        }

        // property is private in parent class
        $this->setDescription($this->description);

        parent::__construct($this->name);

        $this->specifyParameters();
    }

    /**
     * Runs the command
     *
     * @throws Throwable
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        $this->setFormatterStyles($output);
        $this->input = $input;
        $this->output = new SymfonyStyle($input, $output);

        if ($output instanceof ConsoleOutputInterface) {
            $this->stderr = new SymfonyStyle($input, $output->getErrorOutput());
        } else {
            $this->stderr = new SymfonyStyle($input, $output);
        }

        return (int) parent::run($input, $output);
    }

    /**
     * Checks if enabled
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Retrieves argument value
     */
    public function argument(?string $key = null): string|array|null
    {
        if ($key === null) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    /**
     * Retrieves option value
     */
    public function option(?string $key = null): string|array|null
    {
        if ($key === null) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    /**
     * Writes a line of text as success output
     */
    public function success(string $string, bool $stdout = false): void
    {
        $this->writeln(sprintf('<success>%s</success>', $string), $stdout);
    }

    /**
     * Writes a line of text as comment output
     */
    public function comment(string $string, bool $stdout = false): void
    {
        $this->writeln(sprintf('<comment>%s</comment>', $string), $stdout);
    }

    /**
     * Writes a line of text as question output
     */
    public function question(string $string, bool $stdout = false): void
    {
        $this->writeln(sprintf('<question>%s</question>', $string), $stdout);
    }

    /**
     * Writes a line of text as info output
     */
    public function info(string $string, bool $stdout = false): void
    {
        $this->writeln(sprintf('<info>%s</info>', $string), $stdout);
    }

    /**
     * Writes a line of text as warning output
     */
    public function warning(string $string, bool $stdout = false): void
    {
        $this->writeln(sprintf('<warning>%s</warning>', $string), $stdout);
    }

    /**
     * Writes a line of text as error output
     */
    public function error(string $string, bool $stdout = false): void
    {
        $this->writeln(sprintf('<error>%s</error>', $string), $stdout);
    }

    /**
     * Writes a line of text as a success block
     */
    public function successBlock(string $string, bool $stdout = false): void
    {
        $this->block($string, 'OK', 'fg=white;bg=green', ' ', true, $stdout);
    }

    /**
     * Writes a line of text as a success block
     */
    public function commentBlock(string $string, bool $stdout = false): void
    {
        $this->block($string, null, 'fg=green;bg=black', ' // ', true, $stdout);
    }

    /**
     * Writes a line of text as a success block
     */
    public function questionBlock(string $string, bool $stdout = false): void
    {
        $this->block($string, '???', 'fg=black;bg=cyan', ' ', true, $stdout);
    }

    /**
     * Writes a line of text as a success block
     */
    public function infoBlock(string $string, bool $stdout = false): void
    {
        $this->block($string, 'INFO', 'fg=white;bg=blue', ' ', true, $stdout);
    }

    /**
     * Writes a line of text as a success block
     */
    public function warningBlock(string $string, bool $stdout = false): void
    {
        $this->block($string, 'WARNING', 'fg=black;bg=yellow', ' ', true, $stdout);
    }

    /**
     * Writes a line of text as a success block
     */
    public function errorBlock(string $string, bool $stdout = false): void
    {
        $this->block($string, 'ERROR', 'fg=white;bg=red', ' ', true, $stdout);
    }

    /**
     * Convenience method for STDOUT output
     */
    public function stdout(string $string): void
    {
        $this->write($string, true);
    }

    /**
     * Convenience method for STDERR output
     */
    public function stderr(string $string): void
    {
        $this->write($string, false);
    }

    /**
     * Writes text to output
     */
    public function write(string $string, bool $stdout = true): void
    {
        if ($stdout) {
            $this->output->write($string);
        } else {
            $this->stderr->write($string);
        }
    }

    /**
     * Writes a line of text
     */
    public function writeln(string $string, bool $stdout = true): void
    {
        if ($stdout) {
            $this->output->writeln($string);
        } else {
            $this->stderr->writeln($string);
        }
    }

    /**
     * Formats a string as a block of text
     */
    public function block(
        string $string,
        string $type = null,
        string $style = null,
        string $prefix = ' ',
        bool $padding = false,
        bool $stdout = true
    ): void {
        if ($stdout) {
            $this->output->block($string, $type, $style, $prefix, $padding);
        } else {
            $this->stderr->block($string, $type, $style, $prefix, $padding);
        }
    }

    /**
     * Formats data in a table
     */
    public function table(
        array $headers,
        array $rows,
        bool $stdout = true
    ): void {
        if ($stdout) {
            $this->output->table($headers, $rows);
        } else {
            $this->stderr->table($headers, $rows);
        }
    }

    /**
     * Starts the progress bar output
     */
    public function progressStart(int $max = 0, bool $stdout = false): void
    {
        if ($stdout) {
            $this->progressBar = $this->output->createProgressBar($max);
        } else {
            $this->progressBar = $this->stderr->createProgressBar($max);
        }

        $this->progressBar->start();
    }

    /**
     * Advances the progress bar
     *
     * @throws Throwable
     */
    public function progressAdvance(int $step = 1): void
    {
        $this->getProgressBar()->advance($step);
    }

    /**
     * Finishes the progress bar output
     *
     * @throws Throwable
     */
    public function progressFinish(bool $stdout = false): void
    {
        $this->getProgressBar()->finish();

        if ($stdout) {
            $this->output->newLine(2);
        } else {
            $this->stderr->newLine(2);
        }

        $this->progressBar = null;
    }

    /**
     * Asks a question
     */
    public function ask(
        string $question,
        ?string $default = null,
        ?callable $validator = null,
        bool $stdout = true
    ): ?string {
        if ($stdout) {
            return $this->output->ask($question, $default, $validator);
        } else {
            return $this->stderr->ask($question, $default, $validator);
        }
    }

    /**
     * Asks a question with the input hidden
     */
    public function secret(
        string $question,
        ?callable $validator = null,
        bool $stdout = true
    ): ?string {
        if ($stdout) {
            return $this->output->askHidden($question, $validator);
        } else {
            return $this->stderr->askHidden($question, $validator);
        }
    }

    /**
     * Asks for confirmation
     */
    public function confirm(
        string $question,
        bool $default = true,
        bool $stdout = true
    ): bool {
        if ($stdout) {
            return (bool) $this->output->confirm($question, $default);
        } else {
            return (bool) $this->stderr->confirm($question, $default);
        }
    }

    /**
     * Asks a choice question
     */
    public function choice(
        string $question,
        array $choices,
        ?string $default = null,
        bool $stdout = true
    ): ?string {
        if ($stdout) {
            return $this->output->choice($question, $choices, $default);
        } else {
            return $this->stderr->choice($question, $choices, $default);
        }
    }

    /**
     * Call another console command
     *
     * @throws Throwable
     */
    public function call(string $name, array $arguments = []): int
    {
        $command = $this->getApplication()->find($name);
        $arguments['command'] = $name;

        return $command->run(new ArrayInput($arguments), $this->output);
    }

    /**
     * Call another console command silently
     *
     * @throws Throwable
     */
    public function callSilent(string $name, array $arguments = []): int
    {
        $command = $this->getApplication()->find($name);
        $arguments['command'] = $name;

        return $command->run(new ArrayInput($arguments), new NullOutput());
    }

    /**
     * Executes the command
     *
     * @throws Throwable
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        return $this->fire();
    }

    /**
     * Fires the command
     *
     * @throws Throwable
     */
    protected function fire(): int
    {
        return 0;
    }

    /**
     * Retrieves the command arguments
     */
    protected function getArguments(): array
    {
        return [];
    }

    /**
     * Retrieves the command options
     */
    protected function getOptions(): array
    {
        return [];
    }

    /**
     * Specifies the arguments and options
     */
    protected function specifyParameters(): void
    {
        foreach ($this->getArguments() as $argument) {
            call_user_func_array([$this, 'addArgument'], $argument);
        }
        foreach ($this->getOptions() as $option) {
            call_user_func_array([$this, 'addOption'], $option);
        }
    }

    /**
     * Sets default formatter styles
     */
    private function setFormatterStyles(OutputInterface $output): void
    {
        $formatter = $output->getFormatter();
        $formatter->setStyle('success', new OutputFormatterStyle('green'));
        $formatter->setStyle('comment', new OutputFormatterStyle('cyan'));
        $formatter->setStyle('question', new OutputFormatterStyle('black', 'cyan'));
        $formatter->setStyle('info', new OutputFormatterStyle('blue'));
        $formatter->setStyle('warning', new OutputFormatterStyle('yellow'));
        $formatter->setStyle('error', new OutputFormatterStyle('red'));
    }

    /**
     * Retrieves the progress bar
     *
     * @throws RuntimeException When the progress bar is not started
     */
    private function getProgressBar(): ProgressBar
    {
        if (!$this->progressBar) {
            throw new RuntimeException('The ProgressBar is not started');
        }

        return $this->progressBar;
    }
}
