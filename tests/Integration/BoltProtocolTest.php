<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Client\Tests\Integration;

use GraphAware\Bolt\Exception\HandshakeException;
use Laudis\Neo4j\Client\ClientBuilder;
use Laudis\Neo4j\Client\ClientInterface;
use Laudis\Neo4j\Client\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;

final class BoltProtocolTest extends TestCase
{
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $boltUrl = sprintf(
            'bolt://%s:%s@%s',
            getenv('NEO4J_USER'),
            getenv('NEO4J_PASSWORD'),
            getenv('NEO4J_HOST')
        );
        $this->client = ClientBuilder::create()->addConnection('bolt', $boltUrl)->build();
    }

    public function testExceptionThrownWhenNeeded(): void
    {
        if ($this->isV4OrUp()) {
            $this->expectException(HandshakeException::class);
        }

        $this->client->run('MATCH (x) RETURN x LIMIT 1');
    }

    private function isV4OrUp(): bool
    {
        return ((int) explode('.', getenv('NEO4J_VERSION'), 2)[0]) >= 4;
    }
}
