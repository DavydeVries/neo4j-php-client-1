<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Client\HttpDriver;

use function explode;
use GraphAware\Common\Connection\BaseConfiguration;
use GraphAware\Common\Driver\ConfigInterface;
use GraphAware\Common\Driver\DriverInterface;
use GraphAware\Common\Driver\SessionInterface;
use Http\Adapter\Guzzle6\Client;
use Http\Client\Exception;
use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use JsonException;
use Laudis\Neo4j\Client\Formatter\ResponseFormatter;
use RuntimeException;
use function str_replace;

class Driver implements DriverInterface
{
    const DEFAULT_HTTP_PORT = 7474;

    /**
     * @var string
     */
    protected $uri;

    /**
     * @var Configuration
     */
    protected $config;

    private ?string $decidedVersion = null;
    private ?string $transaction = null;

    /**
     * @param string            $uri
     * @param BaseConfiguration $config
     */
    public function __construct($uri, ConfigInterface $config = null)
    {
        if (null !== $config && !$config instanceof BaseConfiguration) {
            throw new RuntimeException(sprintf('Second argument to "%s" must be null or "%s"', __CLASS__, BaseConfiguration::class));
        }

        $this->uri = $uri;
        $this->config = null !== $config ? $config : Configuration::create();
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function session(): SessionInterface
    {
        $client = $this->getHttpClient();
        /** @var RequestFactory $factory */
        $factory = $this->config->getValue('request_factory');

        if (null === $this->decidedVersion) {
            $version = $this->discovery($client, $factory);
            $this->decidedVersion = $version['neo4j_version'] ?? '3.5';
            $defaultDb = getenv('NEO4J_DATABASE');
            if (false === $defaultDb) {
                $defaultDb = 'neo4j';
            }
            $this->transaction = str_replace('{databaseName}', $this->config->getValue('database', $defaultDb), $version['transaction'] ?? '');
        }

        if ($this->isV4OrUp($this->decidedVersion)) {
            $userPasswordCombo = str_replace('http://', '', explode('@', $this->uri, 2)[0]);

            return new SessionApi4(
                new ResponseFormatter(),
                $this->config->getValue('request_factory'),
                $client,
                $this->transaction,
                'Basic '.base64_encode($userPasswordCombo)
            );
        }

        return new Session($this->uri, $client, $this->config);
    }

    /**
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * @return HttpClient
     */
    private function getHttpClient()
    {
        $options = [];
        if ($this->config->hasValue('timeout')) {
            $options['timeout'] = $this->config->getValue('timeout');
        }

        if ($this->config->hasValue('curl_interface')) {
            $options['curl'][10062] = $this->config->getValue('curl_interface');
        }

        if (empty($options)) {
            return $this->config->getValue('http_client');
        }

        // This is to keep BC. Will be removed in 5.0

        $options['curl'][74] = true;
        $options['curl'][75] = true;

        return Client::createWithConfig($options);
    }

    /**
     * @throws Exception|JsonException
     */
    private function discovery(HttpClient $client, RequestFactory $factory): array
    {
        $response = $client->sendRequest($factory->createRequest('GET', $this->uri));

        return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function isV4OrUp(string $version): bool
    {
        return (int) (explode('.', $version, 2)[0]) >= 4;
    }
}
