<?php

/**
 * This file is part of `prooph/event-store-client`.
 * (c) 2018-2022 Alexander Miertsch <kontakt@codeliner.ws>
 * (c) 2018-2022 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\ClientOperations\ClientOperation;

/** @internal */
class StartOperationMessage implements Message
{
    private ClientOperation $operation;
    private int $maxRetries;
    private int $timeout;

    public function __construct(ClientOperation $operation, int $maxRetries, int $timeout)
    {
        $this->operation = $operation;
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
    }

    /** @psalm-pure */
    public function operation(): ClientOperation
    {
        return $this->operation;
    }

    /** @psalm-pure */
    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    /** @psalm-pure */
    public function timeout(): int
    {
        return $this->timeout;
    }

    /** @psalm-pure */
    public function __toString(): string
    {
        return 'StartOperationMessage';
    }
}
