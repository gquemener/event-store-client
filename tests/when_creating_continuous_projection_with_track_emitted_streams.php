<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2019 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2019 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\EventReadResult;
use Prooph\EventStore\Projections\ProjectionDetails;
use Prooph\EventStore\Util\Guid;
use Throwable;

class when_creating_continuous_projection_with_track_emitted_streams extends TestCase
{
    use ProjectionSpecification;

    /** @var string */
    private $projectionName;
    /** @var string */
    private $streamName;
    /** @var string */
    private $emittedStreamName;
    /** @var string */
    private $query;
    /** @var string */
    private $projectionId;

    public function given(): Generator
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_creating_transient_projection-' . $id;
        $this->streamName = 'test-stream-' . $id;
        $this->emittedStreamName = 'emittedStream-' . $id;

        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $this->query = $this->createStandardQuery($this->streamName);
    }

    protected function when(): Generator
    {
        yield $this->projectionsManager->createContinuousAsync(
            $this->projectionName,
            $this->query,
            true,
            'JS',
            $this->credentials
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_create_projection(): string
    {
        $this->execute(function () {
            /** @var ProjectionDetails[] $allProjections */
            $allProjections = yield $this->projectionsManager->listContinuousAsync($this->credentials);

            $found = false;

            foreach ($allProjections as $projection) {
                if ($projection->effectiveName() === $this->projectionName) {
                    $this->projectionId = $projection->name();
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found);
            $this->assertNotNull($this->projectionId);
        });

        return $this->projectionId;
    }

    /**
     * @test
     * @throws Throwable
     * @depends should_create_projection
     */
    public function should_enable_track_emitted_streams(string $projectionId): void
    {
        $this->execute(function () use ($projectionId) {
            $event = yield $this->connection->readEventAsync(
                '$projections-' . $projectionId,
                0,
                true,
                $this->credentials
            );
            \assert($event instanceof EventReadResult);
            $data = $event->event()->event()->data();
            $eventData = \json_decode($data, true);
            $this->assertTrue((bool) $eventData['trackEmittedStreams']);
        });
    }
}
