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

use Amp\Delayed;
use Generator;
use PHPUnit\Framework\TestCase;
use Prooph\EventStore\Util\Guid;
use Throwable;

class when_resetting_projections extends TestCase
{
    use ProjectionSpecification;

    /** @var string */
    private $projectionName;
    /** @var string */
    private $streamName;
    /** @var string */
    private $query;

    public function given(): Generator
    {
        $id = Guid::generateAsHex();
        $this->projectionName = 'when_resetting_projections-' . $id;
        $this->streamName = 'test-stream-' . $id;

        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 1}');
        yield $this->postEvent($this->streamName, 'testEvent', '{"A": 2}');

        $this->query = $this->createStandardQuery($this->streamName);

        yield $this->projectionsManager->createContinuousAsync(
            $this->projectionName,
            $this->query,
            false,
            'JS',
            $this->credentials
        );
    }

    protected function when(): Generator
    {
        yield $this->projectionsManager->resetAsync(
            $this->projectionName,
            $this->credentials
        );
    }

    /**
     * @test
     * @throws Throwable
     */
    public function should_reset_the_projection(): void
    {
        $this->execute(function () {
            yield new Delayed(500);

            $projectionStatus = \json_decode(
                yield $this->projectionsManager->getStatusAsync(
                    $this->projectionName,
                    $this->credentials
                ),
                true
            );
            $status = $projectionStatus['status'];

            $this->assertTrue(\in_array($status, ['Starting/Initial', 'Running'], true));
        });
    }
}
