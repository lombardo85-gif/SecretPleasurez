<?php
/**
 * Writes to stdout and to a dated run log, so a cron run leaves an audit trail.
 */

declare(strict_types=1);

final class ImportLogger
{
    /** @var resource|null */
    private $handle = null;

    private bool $quiet;
    private int $errors = 0;
    private int $warnings = 0;

    public function __construct(?string $logDir, bool $quiet = false)
    {
        $this->quiet = $quiet;

        if ($logDir === null) {
            return;
        }

        if (!is_dir($logDir) && !mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            fwrite(STDERR, sprintf("warning: cannot create log dir %s — logging to stdout only\n", $logDir));

            return;
        }

        $handle = fopen(rtrim($logDir, '/') . '/import-' . date('Ymd-His') . '.log', 'ab');
        if ($handle !== false) {
            $this->handle = $handle;
        }
    }

    public function info(string $message): void
    {
        $this->write('INFO ', $message, false);
    }

    /**
     * A run's final tally. Always printed, even under --quiet: a scheduled run
     * that reports nothing at all is indistinguishable from one that never ran.
     */
    public function summary(string $message): void
    {
        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), 'INFO ', $message);

        if ($this->handle !== null) {
            fwrite($this->handle, $line . PHP_EOL);
        }

        fwrite(STDOUT, $line . PHP_EOL);
    }

    public function warn(string $message): void
    {
        ++$this->warnings;
        $this->write('WARN ', $message, false);
    }

    public function error(string $message): void
    {
        ++$this->errors;
        $this->write('ERROR', $message, true);
    }

    public function errorCount(): int
    {
        return $this->errors;
    }

    public function warningCount(): int
    {
        return $this->warnings;
    }

    private function write(string $level, string $message, bool $toStderr): void
    {
        $line = sprintf('[%s] %s %s', date('Y-m-d H:i:s'), $level, $message);

        if ($this->handle !== null) {
            fwrite($this->handle, $line . PHP_EOL);
        }

        // Errors always surface, so cron mail is not silently empty on failure.
        if ($toStderr) {
            fwrite(STDERR, $line . PHP_EOL);
        } elseif (!$this->quiet) {
            fwrite(STDOUT, $line . PHP_EOL);
        }
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            fclose($this->handle);
        }
    }
}
