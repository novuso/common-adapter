<?php

declare(strict_types=1);

namespace Novuso\Common\Adapter\Console\Subscriber;

use Novuso\System\Utility\ClassName;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class SymfonyExceptionLogger
 */
final class SymfonyExceptionLogger implements EventSubscriberInterface
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [ConsoleEvents::ERROR => 'onConsoleError'];
    }

    /**
     * Logs console exception
     */
    public function onConsoleError(ConsoleErrorEvent $event): void
    {
        $exception = $event->getError();
        $exitCode = $event->getExitCode();

        $message = sprintf(
            '%s: "%s" at %s line %s',
            ClassName::short($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        $this->logger->error($message, [
            'exit_code' => $exitCode,
            'exception' => $exception
        ]);
    }
}
