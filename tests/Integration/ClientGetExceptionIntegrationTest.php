<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client\Tests\Integration;

use GraphAware\Neo4j\Client\ClientBuilder;
use GraphAware\Neo4j\Client\Exception\Neo4jException;
use GraphAware\Neo4j\Client\Tests\Example\ExampleTestCase;

class ClientGetExceptionIntegrationTest extends ExampleTestCase
{
    public function testExceptionHandling()
    {
        $this->expectException(Neo4jException::class);
        $result = $this->client->run('CREATE (n:Cool');
    }
}
