<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\ExpectedVersion;
use Prooph\EventStoreClient\RecordedEvent;
use Prooph\EventStoreClient\ResolvedEvent;
use Prooph\EventStoreClient\SliceReadStatus;
use Prooph\EventStoreClient\StreamEventsSlice;
use Prooph\EventStoreClient\StreamPosition;
use ProophTest\EventStoreClient\Helper\EventDataComparer;
use ProophTest\EventStoreClient\Helper\TestConnection;
use ProophTest\EventStoreClient\Helper\TestEvent;
use Throwable;
use function Amp\call;
use function Amp\Promise\wait;

class read_event_stream_forward_should extends TestCase
{
    /**
     * @test
     * @throws Throwable
     */
    public function throw_if_count_le_zero(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_throw_if_count_le_zero';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $this->expectException(InvalidArgumentException::class);

            $store->readStreamEventsForwardAsync(
                $stream,
                0,
                0,
                false
            );
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function throw_if_start_lt_zero(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_throw_if_start_lt_zero';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $this->expectException(InvalidArgumentException::class);

            $store->readStreamEventsForwardAsync(
                $stream,
                -1,
                1,
                false
            );
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function notify_using_status_code_if_stream_not_found(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_notify_using_status_code_if_stream_not_found';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $read = yield $store->readStreamEventsForwardAsync(
                $stream,
                StreamPosition::START,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamNotFound()->equals($read->status()));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function notify_using_status_code_if_stream_was_deleted(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_notify_using_status_code_if_stream_was_deleted';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();
            yield $store->deleteStreamAsync($stream, ExpectedVersion::EMPTY_STREAM, true);

            $read = yield $store->readStreamEventsForwardAsync(
                $stream,
                StreamPosition::START,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertTrue(SliceReadStatus::streamDeleted()->equals($read->status()));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_no_events_when_called_on_empty_stream(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_return_single_event_when_called_on_empty_stream';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $read = yield $store->readStreamEventsForwardAsync(
                $stream,
                StreamPosition::START,
                1,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertCount(0, $read->events());
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_empty_slice_when_called_on_non_existing_range(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_return_empty_slice_when_called_on_non_existing_range';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsForwardAsync(
                $stream,
                11,
                5,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertCount(0, $read->events());
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_partial_slice_if_no_enough_events_in_stream(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_return_partial_slice_if_no_enough_events_in_stream';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsForwardAsync(
                $stream,
                9,
                5,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $this->assertCount(1, $read->events());
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function throw_when_got_int_max_value_as_maxcount(): void
    {
        wait(call(function () {
            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $this->expectException(InvalidArgumentException::class);

            yield $store->readStreamEventsForwardAsync(
                'foo',
                StreamPosition::START,
                \PHP_INT_MAX,
                false
            );
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function return_events_in_same_order_as_written(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_return_events_in_same_order_as_written';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsForwardAsync(
                $stream,
                StreamPosition::START,
                10,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $events = \array_map(
                function (ResolvedEvent $e): RecordedEvent {
                    return $e->event();
                },
                $read->events()
            );

            $this->assertTrue(EventDataComparer::allEqual($testEvents, $events));
        }));
    }

    /**
     * @test
     * @throws Throwable
     */
    public function be_able_to_read_slice_from_arbitrary_position(): void
    {
        wait(call(function () {
            $stream = 'read_event_stream_forward_should_be_able_to_read_slice_from_arbitrary_position';

            $store = TestConnection::createAsync();
            yield $store->connectAsync();

            $testEvents = TestEvent::newAmount(10);
            yield $store->appendToStreamAsync(
                $stream,
                ExpectedVersion::EMPTY_STREAM,
                $testEvents
            );

            $read = yield $store->readStreamEventsForwardAsync(
                $stream,
                5,
                2,
                false
            );
            \assert($read instanceof StreamEventsSlice);

            $events = \array_map(
                function (ResolvedEvent $e): RecordedEvent {
                    return $e->event();
                },
                $read->events()
            );

            $this->assertTrue(EventDataComparer::allEqual(
                \array_slice($testEvents, 5, 2),
                $events
            ));
        }));
    }
}
