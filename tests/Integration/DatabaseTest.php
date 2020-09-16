<?php


namespace Laudis\Neo4j\Client\Tests\Integration;


use Laudis\Neo4j\Client\ClientBuilder;
use Laudis\Neo4j\Client\Exception\Neo4jException;
use Laudis\Neo4j\Client\HttpDriver\Configuration;

final class DatabaseTest extends IntegrationTestCase
{

    public function testCustomDatabase(): void
    {
        $client = ClientBuilder::create()->addConnection(
            'test',
            $this->getConnections()['http'],
            Configuration::create()->setValue('database', 'abc')
        )->build();

        if ($this->isV4OrUp()) {
            $this->expectException(Neo4jException::class);
            $this->expectErrorMessage("The database requested does not exists. Requested database name: 'abc'.");
        }
        $client->run('MATCH (x) RETURN x');
    }
}
