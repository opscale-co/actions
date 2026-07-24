<?php

declare(strict_types=1);

namespace Opscale\Actions\Results;

/**
 * Envelope for the two documented outcomes of an Action's process:
 *
 * - success: `handle()` finished and returned its output payload.
 * - fail (soft): `handle()` (or the `canRun()` gate) surfaced a controlled
 *   error — the caller gets a message, no exception is raised, no system
 *   event is dispatched.
 *
 * Hard fail (uncaught Throwable) is NOT modeled here — the pipeline lets it
 * bubble so each adapter can translate it to its native error channel.
 */
final class Result
{
    private function __construct(
        private readonly bool $success,
        private readonly ?string $message,
        private readonly array $data,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data = []): self
    {
        return new self(true, null, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fail(string $message, array $data = []): self
    {
        return new self(false, $message, $data);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFail(): bool
    {
        return ! $this->success;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }
}
