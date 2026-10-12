<?php

declare(strict_types=1);

namespace OpenSimKit\Web;

/**
 * What the setup and the page of the browser say to each other: a folder only its user can enter, with the screen being
 * asked (screen.json), the answer given to it (answer.json), what was said meanwhile (log.jsonl) and who is allowed in
 * (token, session). The setup writes the screens and reads the answers, the web server does the opposite; nothing is
 * kept once the setup ends.
 */
final class Mailbox
{
    public function __construct(public readonly string $dir) {}

    /** A new folder, in the temporary one, for this user only. */
    public static function create(): self
    {
        $dir = rtrim(sys_get_temp_dir(), '/') . '/opensim-wizard-' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0o700)) {
            throw new \RuntimeException("cannot make the folder $dir");
        }

        return new self($dir);
    }

    /**
     * Show a screen: it gets the next number, and the answer to the one before is forgotten.
     *
     * @param array<string,mixed> $screen
     * @return int its number
     */
    public function publish(array $screen): int
    {
        $id = ($this->screen()['id'] ?? 0) + 1;
        $this->clearAnswer();
        $this->progress('');
        $this->write('screen.json', ['id' => $id] + $screen);

        return $id;
    }

    /** @return ?array<string,mixed> the screen being asked, null before the first one */
    public function screen(): ?array
    {
        return $this->read('screen.json');
    }

    /** The number of the last screen published, 0 before the first. */
    public function current(): int
    {
        return (int) ($this->screen()['id'] ?? 0);
    }

    /** @param array<string,mixed> $answer */
    public function answer(int $id, array $answer): void
    {
        $this->write('answer.json', ['id' => $id] + $answer);
    }

    /** @return ?array<string,mixed> what was answered to this screen, null while nothing was */
    public function answered(int $id): ?array
    {
        $answer = $this->read('answer.json');

        return $answer !== null && ($answer['id'] ?? 0) === $id ? $answer : null;
    }

    /** What was answered is not kept, it may hold a password. */
    public function clearAnswer(): void
    {
        is_file("{$this->dir}/answer.json") && unlink("{$this->dir}/answer.json");
    }

    /** Something said to the person: it shows above the next screen. */
    public function say(string $level, string $message): void
    {
        $line = json_encode(['after' => $this->current(), 'level' => $level, 'message' => $message], JSON_THROW_ON_ERROR);
        file_put_contents("{$this->dir}/log.jsonl", $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /** What a program is saying right now, on a line it keeps rewriting (a download): it replaces the one before. */
    public function progress(string $line): void
    {
        $file = "{$this->dir}/progress.txt";
        if ($line === '') {
            is_file($file) && unlink($file);

            return;
        }
        $tmp = "$file." . bin2hex(random_bytes(3));
        file_put_contents($tmp, $line);
        rename($tmp, $file);
    }

    /** The line the programs are rewriting, empty when there is none. */
    public function progressLine(): string
    {
        $file = "{$this->dir}/progress.txt";

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    /**
     * @return list<array{after:int,level:string,message:string}>
     */
    public function log(): array
    {
        $file = "{$this->dir}/log.jsonl";
        $lines = is_file($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        return array_values(array_filter(array_map(static fn(string $l): mixed => json_decode($l, true), $lines ?: [])));
    }

    /** The address can be used once: it is kept until a browser comes with it. */
    public function allow(string $token): void
    {
        $this->write('token.json', ['token' => $token]);
    }

    /**
     * Let in the browser that comes with the address: it gets the secret it will show from now on, the address is of no use anymore.
     *
     * @return ?string the secret, null when the token is not (or no more) the right one
     */
    public function admit(string $token): ?string
    {
        $expected = $this->read('token.json')['token'] ?? null;
        if ($expected === null || !hash_equals($expected, $token)) {
            return null;
        }
        unlink("{$this->dir}/token.json");
        $secret = bin2hex(random_bytes(16));
        $this->write('session.json', ['secret' => $secret]);

        return $secret;
    }

    /** Whether this is the secret of the browser let in. */
    public function admitted(string $secret): bool
    {
        $expected = $this->read('session.json')['secret'] ?? null;

        return $expected !== null && $secret !== '' && hash_equals($expected, $secret);
    }

    /** The browser showed the last screen (the one that ends the setup): the server can stop. */
    public function seen(int $id): void
    {
        $this->write('seen.json', ['id' => $id]);
    }

    public function hasSeen(int $id): bool
    {
        return ($this->read('seen.json')['id'] ?? 0) >= $id;
    }

    /** Remove the folder and what it holds. */
    public function remove(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        is_dir($this->dir) && rmdir($this->dir);
    }

    /** @param array<string,mixed> $data */
    private function write(string $name, array $data): void
    {
        // Written aside then renamed, so the other side never reads half a file
        $tmp = "{$this->dir}/.$name." . bin2hex(random_bytes(3));
        file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        rename($tmp, "{$this->dir}/$name");
    }

    /** @return ?array<string,mixed> */
    private function read(string $name): ?array
    {
        $file = "{$this->dir}/$name";
        $text = is_file($file) ? file_get_contents($file) : false;
        $data = $text === false ? null : json_decode($text, true);

        return is_array($data) ? $data : null;
    }
}
