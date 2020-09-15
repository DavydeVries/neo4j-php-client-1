<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Client\Tests\Example;

use GraphAware\Common\Result\Result;
use Laudis\Neo4j\Client\ClientBuilder;
use Laudis\Neo4j\Client\ClientInterface;
use PHPUnit\Framework\TestCase;

abstract class ExampleTestCase extends TestCase
{
    protected ClientInterface $client;

    protected string $neo4jVersion;

    public function setUp(): void
    {
        $this->client = $this->baseClientBuilder()->build();
    }

    public function emptyDB(): void
    {
        $this->client->run('MATCH (n) DETACH DELETE n');
    }

    protected function runQuery(string $query, array $params = []): Result
    {
        if ($this->isV4OrUP()) {
            // Change the parameter syntax to new version ({param} to $param)
            $query = preg_replace_callback('/{\w+}/', static function (array $match) {
                return '$'.mb_substr($match[0], 1, -1);
            }, $query);
        }

        return $this->client->run($query, $params);
    }

    protected function isV4OrUP(): bool
    {
        return ((int) explode('.', $this->neo4jVersion, 2)[0]) >= 4;
    }

    protected function createHttpUrl(): string
    {
        $boltUrl = 'http://localhost';
        if (isset($_ENV['NEO4J_USER'])) {
            $boltUrl = sprintf(
                'http://%s:%s@%s:%s',
                getenv('NEO4J_USER'),
                getenv('NEO4J_PASSWORD'),
                getenv('NEO4J_HOST'),
                getenv('NEO4J_PORT')
            );
        }

        return $boltUrl;
    }

    protected function createBoltUrl(): string
    {
        $boltUrl = 'bolt://localhost';
        if (isset($_ENV['NEO4J_USER'])) {
            $boltUrl = sprintf(
                'bolt://%s:%s@%s',
                getenv('NEO4J_USER'),
                getenv('NEO4J_PASSWORD'),
                getenv('NEO4J_HOST')
            );
        }

        return $boltUrl;
    }

    protected function baseClientBuilder(): ClientBuilder
    {
        $this->neo4jVersion = getenv('NEO4J_VERSION');

        if ($this->isV4OrUP()) {
            $boltUrl = $this->createHttpUrl();
        } else {
            $boltUrl = $this->createBoltUrl();
        }

        return ClientBuilder::create()->addConnection('default', $boltUrl);
    }
}
