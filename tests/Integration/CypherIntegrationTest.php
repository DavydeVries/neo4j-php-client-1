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

use GraphAware\Bolt\Result\Type\Node as BoltNode;
use GraphAware\Bolt\Result\Type\Relationship as BoltRelationship;
use GraphAware\Common\Type\Node;
use GraphAware\Common\Type\Path;
use Laudis\Neo4j\Client\Formatter\Type\Node as HttpNode;
use Laudis\Neo4j\Client\Formatter\Type\Relationship as HttpRelationship;

class CypherIntegrationTest extends IntegrationTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->emptyDb();
    }

    public function testNodeIsReturned()
    {
        $query = 'CREATE (n:Node) RETURN n';
        $record1 = $this->client->run($query, [], null, 'http')->firstRecord();
        $this->assertInstanceOf(Node::class, $record1->get('n'));
        $this->assertInstanceOf(HttpNode::class, $record1->get('n'));
        if (!$this->isV4OrUp()) {
            $record2 = $this->client->run($query, [], null, 'bolt')->firstRecord();
            $this->assertInstanceOf(Node::class, $record2->get('n'));
            $this->assertInstanceOf(BoltNode::class, $record2->get('n'));
        }
    }

    public function testRelationshipIsReturned()
    {
        $query = 'CREATE (a)-[r:RELATES]->(b) RETURN a, r, b';
        $record1 = $this->client->run($query, [], null, 'http')->firstRecord();
        $this->assertInstanceOf(HttpRelationship::class, $record1->get('r'));
        if (!$this->isV4OrUp()) {
            $record2 = $this->client->run($query, [], null, 'bolt')->firstRecord();
            $this->assertInstanceOf(BoltRelationship::class, $record2->get('r'));
        }
    }

    /**
     * @group path
     */
    public function testPathIsReturned()
    {
        $query = 'CREATE p=(a:Cool)-[:RELATES]->(b:NotSoCool) RETURN p';
        $record1 = $this->client->run($query, [], null, 'http')->firstRecord();
        $this->assertInstanceOf(Path::class, $record1->get('p'));

        if (!$this->isV4OrUp()) {
            $record2 = $this->client->run($query, [], null, 'bolt')->firstRecord();
            $this->assertInstanceOf(Path::class, $record2->get('p'));
        }
    }

    public function testExceptionIsThrownOnEmptyStatement(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $query = '';
        $this->client->run($query);
    }
}
