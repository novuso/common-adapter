<?php declare(strict_types=1);

namespace Novuso\Common\Adapter\EventStore\InMemory;

use ArrayIterator;
use Novuso\Common\Application\EventStore\EventStoreInterface;
use Novuso\Common\Application\EventStore\Exception\ConcurrencyException;
use Novuso\Common\Application\EventStore\Exception\StreamNotFoundException;
use Novuso\Common\Domain\Identity\IdentifierInterface;
use Novuso\Common\Domain\Model\EventRecord;
use Novuso\System\Type\Type;
use Novuso\System\Utility\Validate;

/**
 * InMemoryEventStore is an in-memory implementation of an event store
 *
 * @copyright Copyright (c) 2017, Novuso. <http://novuso.com>
 * @license   http://opensource.org/licenses/MIT The MIT License
 * @author    John Nickell <email@johnnickell.com>
 */
class InMemoryEventStore implements EventStoreInterface
{
    /**
     * Stream data
     *
     * @var array
     */
    protected $streamData = [];

    /**
     * {@inheritdoc}
     */
    public function append(EventRecord $eventRecord): void
    {
        $idString = $eventRecord->aggregateId()->toString();
        $typeString = $eventRecord->aggregateType()->toString();

        if (!isset($this->streamData[$typeString])) {
            $this->streamData[$typeString] = [];
        }

        if (!isset($this->streamData[$typeString][$idString])) {
            $this->streamData[$typeString][$idString] = new InMemoryStreamData();
        }

        /** @var InMemoryStreamData $streamData */
        $streamData = $this->streamData[$typeString][$idString];

        $version = $eventRecord->sequenceNumber();
        if ($version === 0) {
            $expected = null;
        } else {
            $expected = $version - 1;
        }

        if ($streamData->getVersion() !== $expected) {
            $found = $streamData->getVersion();
            $message = sprintf(
                'Expected v%s; found v%s in stream [%s]{%s}',
                $expected,
                $found,
                $typeString,
                $idString
            );
            throw new ConcurrencyException($message);
        }

        $streamData->addEvent($eventRecord);
        $streamData->setVersion($version);
    }

    /**
     * {@inheritdoc}
     */
    public function appendStream($eventStream): void
    {
        assert(Validate::isTraversable($eventStream), 'Event stream is not traversable');

        foreach ($eventStream as $eventRecord) {
            $this->append($eventRecord);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function readStream(Type $type, IdentifierInterface $id, ?int $first = null, ?int $last = null)
    {
        $idString = $id->toString();
        $typeString = $type->toString();

        if (!$this->hasStream($type, $id)) {
            $message = sprintf('Stream not found for [%s]{%s}', $typeString, $idString);
            throw new StreamNotFoundException($message);
        }

        /** @var InMemoryStreamData $streamData */
        $streamData = $this->streamData[$typeString][$idString];

        $count = count($streamData);
        $first = $this->normalizeFirst($first);
        $last = $this->normalizeLast($last, $count);

        return new ArrayIterator(array_values(array_filter(
            $streamData->getEvents(),
            function (EventRecord $event) use ($first, $last) {
                $sequence = $event->sequenceNumber();

                if ($sequence >= $first && $sequence <= $last) {
                    return true;
                }

                return false;
            }
        )));
    }

    /**
     * {@inheritdoc}
     */
    public function hasStream(Type $type, IdentifierInterface $id): bool
    {
        $idString = $id->toString();
        $typeString = $type->toString();

        if (!isset($this->streamData[$typeString])) {
            return false;
        }

        if (!isset($this->streamData[$typeString][$idString])) {
            return false;
        }

        return true;
    }

    /**
     * Retrieves the normalized first version
     *
     * @param int|null $first The first version or null for beginning
     *
     * @return int
     */
    protected function normalizeFirst(?int $first): int
    {
        if ($first === null) {
            return 0;
        }

        return $first;
    }

    /**
     * Retrieves the normalized last version
     *
     * @param int|null $last  The last version or null for remaining
     * @param int      $count The total event count
     *
     * @return int
     */
    protected function normalizeLast(?int $last, int $count): int
    {
        if ($last === null) {
            return $count - 1;
        }

        return $last;
    }
}
