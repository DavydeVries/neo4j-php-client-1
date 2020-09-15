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

use GraphAware\Common\Result\Result;
use Laudis\Neo4j\Client\ClientBuilder;
use PHPUnit\Framework\TestCase;

class IntegrationTestCase extends TestCase
{
    /**
     * @var \Laudis\Neo4j\Client\Client
     */
    protected $client;

    public function setUp(): void
    {
        $connections = array_merge($this->getConnections(), $this->getAdditionalConnections());

        $builder = ClientBuilder::create()->addConnection('http', $connections['http']);
        if (!$this->isV4OrUp()) {
            $builder->addConnection('bolt', $connections['bolt']);
        }

        $this->client = $builder->build();
    }

    protected function getConnections()
    {
        $httpUri = 'http://localhost:7474';
        if (isset($_ENV['NEO4J_USER'])) {
            $httpUri = sprintf(
                '%s://%s:%s@%s:%s',
                getenv('NEO4J_SCHEMA'),
                getenv('NEO4J_USER'),
                getenv('NEO4J_PASSWORD'),
                getenv('NEO4J_HOST'),
                getenv('NEO4J_PORT')
            );
        }

        $boltUrl = 'bolt://localhost';
        if (isset($_ENV['NEO4J_USER'])) {
            $boltUrl = sprintf(
                'bolt://%s:%s@%s',
                getenv('NEO4J_USER'),
                getenv('NEO4J_PASSWORD'),
                getenv('NEO4J_HOST')
            );
        }

        return [
            'http' => $httpUri,
            'bolt' => $boltUrl
        ];
    }

    protected function runQuery(string $query, array $params = [], $tag = null, $connectionAlias = null): Result
    {
        $query = $this->modernizeQueryIfNeeded($query);

        return $this->client->run($query, $params, $tag, $connectionAlias);
    }

    protected function isV4OrUp(): bool
    {
        return ((int) explode('.', getenv('NEO4J_VERSION'), 2)[0]) >= 4;
    }

    protected function getAdditionalConnections()
    {
        return [];
    }

    /**
     * Empties the graph database.
     *
     * @void
     */
    public function emptyDb()
    {
        $this->client->run('MATCH (n) OPTIONAL MATCH (n)-[r]-() DELETE r,n', null, null);
    }

    /**
     * @param string $query
     * @return string|string[]|null
     */
    protected function modernizeQueryIfNeeded(string $query)
    {
        if ($this->isV4OrUP()) {
            // Change the parameter syntax to new version ({param} to $param)
            $query = preg_replace_callback('/{\w+}/', static function (array $match) {
                return '$' . mb_substr($match[0], 1, -1);
            }, $query);
        }
        return $query;
    }
}
