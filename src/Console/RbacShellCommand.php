<?php

namespace Dsewth\SimpleHRBAC\Console;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Interactive shell to browse and manage the RBAC role tree.
 */
class RbacShellCommand extends Command
{
    protected $signature = 'rbac:shell';

    protected $description = 'Interactively browse and manage the RBAC role tree';

    public function handle(): int
    {
        try {
            return $this->runShell();
        } catch (QueryException $exception) {
            $this->error('Database error: '.$exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            // Never dump a stack trace inside the interactive shell.
            $this->error('Unexpected error: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function runShell(): int
    {
        $session = new ShellSession($this->output);

        $this->newLine();
        $this->info('simpleHRBAC interactive shell');
        $this->line('Press <comment>TAB</comment> to complete, type <comment>help</comment> for the command list and <comment>exit</comment> (or Ctrl+D) to quit.');
        $this->newLine();

        $this->safely(function () use ($session): void {
            if ($session->current() === null) {
                $this->warn('No roles yet — use "add role" to create the root role.');
            } else {
                $session->execute('ls');
            }
        });

        $this->registerCompletion($session);

        while (true) {
            $line = $this->readLine($session);

            if ($line === null) {
                // End of input (Ctrl+D on an empty line).
                break;
            }

            $continue = $this->safely(fn (): bool => $session->execute($line));

            if ($continue === false) {
                // exit / quit.
                break;
            }
        }

        $this->newLine();
        $this->line('Goodbye!');

        return self::SUCCESS;
    }

    /**
     * Run a shell step, turning unexpected failures into a friendly
     * message instead of a stack trace — and keeping the REPL alive.
     */
    protected function safely(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            $this->error('Database error: '.$exception->getMessage());

            return true;
        } catch (\Throwable $exception) {
            $this->error('Unexpected error: '.$exception->getMessage());

            return true;
        }
    }

    /**
     * Read one shell line. Returns null when the input ends.
     */
    protected function readLine(ShellSession $session): ?string
    {
        if (function_exists('readline')) {
            $line = readline($this->prompt($session, true));

            return $line === false ? null : $line;
        }

        // Fallback (e.g. Windows without ext-readline): plain line reading.
        $this->output->write($this->prompt($session, false));
        $line = fgets(STDIN);

        return $line === false ? null : rtrim($line, "\r\n");
    }

    /**
     * Build the prompt showing the current path.
     */
    protected function prompt(ShellSession $session, bool $ansi): string
    {
        if ($ansi) {
            // Raw escape sequences because readline() writes the prompt itself.
            return sprintf("\033[1;36msimpleHRBAC\033[0m \033[1;33m[%s]\033[0m > ", $session->path());
        }

        return sprintf(
            '<fg=cyan>simpleHRBAC</> <fg=yellow>[%s]</> > ',
            OutputFormatter::escape($session->path())
        );
    }

    /**
     * Register context aware tab completion for the shell line.
     */
    protected function registerCompletion(ShellSession $session): void
    {
        if (! function_exists('readline_completion_function')) {
            return;
        }

        $completer = $session->completer();

        readline_completion_function(function (string $text, int $start, int $end) use ($completer): array {
            try {
                $buffer = $text;
                $info = function_exists('readline_info') ? readline_info() : null;

                if (is_array($info) && isset($info['line_buffer'])) {
                    // line_buffer may contain trailing data, cut it at the cursor.
                    $point = (int) ($info['point'] ?? $info['end'] ?? strlen($info['line_buffer']));
                    $buffer = substr((string) $info['line_buffer'], 0, $point);
                }

                return $completer->complete($buffer);
            } catch (\Throwable) {
                // Never break the line editor because of a failed lookup.
                return [];
            }
        });
    }
}
