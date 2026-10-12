<?php

declare(strict_types=1);

namespace OpenSimKit\Web;

use OpenSimKit\Ui\WebUi;

/**
 * The setup in a browser, running: the folder the two sides talk through, the web server (php -S) and the interface the setup
 * speaks to. Made by WizardLauncher; stop() ends it.
 */
final class WebSession
{
    private bool $stopped = false;

    /**
     * @param resource $process the web server
     */
    public function __construct(
        public readonly Mailbox $mailbox,
        public readonly WebUi $ui,
        public readonly string $url,
        public readonly int $port,
        private $process,
    ) {}

    /**
     * End the setup: the last screen says why, the server stops once the browser has shown it (or after a few seconds), and
     * the mailbox is removed.
     */
    public function stop(string $message = '', float $wait = 3.0): void
    {
        if ($this->stopped) {
            return;
        }
        $this->stopped = true;
        if ($message !== '') {
            $id = $this->mailbox->publish(['kind' => 'done', 'message' => $message]);
            $deadline = microtime(true) + $wait;
            while (!$this->mailbox->hasSeen($id) && microtime(true) < $deadline && $this->running()) {
                usleep(50_000);
            }
        }
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        $this->mailbox->remove();
    }

    /** Whether the web server is still there. */
    public function running(): bool
    {
        return is_resource($this->process) && (proc_get_status($this->process)['running'] ?? false);
    }
}
