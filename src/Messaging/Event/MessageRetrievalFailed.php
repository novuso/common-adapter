<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Messaging\Event;

use Novuso\Common\Domain\Messaging\Event\Event;

use function Novuso\Common\validate_array_keys;

/**
 * Class MessageRetrievalFailed
 */
final class MessageRetrievalFailed implements Event
{
    /**
     * Constructs MessageRetrievalFailed
     */
    public function __construct(
        protected string $message,
        protected mixed $code,
        protected string $file,
        protected int $line,
        protected array $trace,
        protected string $traceAsString
    ) {
    }

    /**
     * @inheritDoc
     */
    public static function fromArray(array $data): static
    {
        $keys = [
            'message',
            'code',
            'file',
            'line',
            'trace',
            'trace_as_string'
        ];

        validate_array_keys($keys, $data);

        return new static(
            $data['message'],
            $data['code'],
            $data['file'],
            $data['line'],
            $data['trace'],
            $data['trace_as_string']
        );
    }

    /**
     * Retrieves the message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Retrieves the code
     */
    public function getCode(): mixed
    {
        return $this->code;
    }

    /**
     * Retrieves the file
     */
    public function getFile(): string
    {
        return $this->file;
    }

    /**
     * Retrieves the line
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * Retrieves the stack trace
     */
    public function getTrace(): array
    {
        return $this->trace;
    }

    /**
     * Retrieves stack trace as a string
     */
    public function getTraceAsString(): string
    {
        return $this->traceAsString;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [
            'message'         => $this->message,
            'code'            => $this->code,
            'file'            => $this->file,
            'line'            => $this->line,
            'trace'           => $this->trace,
            'trace_as_string' => $this->traceAsString
        ];
    }
}
