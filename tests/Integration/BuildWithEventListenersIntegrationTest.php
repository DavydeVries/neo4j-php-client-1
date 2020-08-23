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
use GraphAware\Neo4j\Client\Exception\Neo4jExceptionInterface;
use GraphAware\Neo4j\Client\Neo4jClientEvents;
use GraphAware\Neo4j\Client\Tests\Example\ExampleTestCase;

/**
 * Class BuildWithEventListenersIntegrationTest.
 *
 * @group listener
 */
class BuildWithEventListenersIntegrationTest extends ExampleTestCase
{

    public function testListenersAreRegistered()
    {
        $listener = new EventListener();
        $client = $this->baseClientBuilder()
            ->registerEventListener(Neo4jClientEvents::NEO4J_PRE_RUN, [$listener, 'onPreRun'])
            ->registerEventListener(Neo4jClientEvents::NEO4J_POST_RUN, [$listener, 'onPostRun'])
            ->registerEventListener(Neo4jClientEvents::NEO4J_ON_FAILURE, [$listener, 'onFailure'])
            ->build();

        $result = $client->run('MATCH (n) RETURN count(n)');
        $this->assertTrue($listener->hookedPreRun);
        $this->assertTrue($listener->hookedPostRun);
    }

    public function testFailureCanBeDisabled()
    {
        $listener = new EventListener();
        $client = $this->baseClientBuilder()
            ->registerEventListener(Neo4jClientEvents::NEO4J_ON_FAILURE, [$listener, 'onFailure'])
            ->build();

        $client->run('MATCH (n)');
        $this->assertInstanceOf(Neo4jExceptionInterface::class, $listener->e);
    }
}
