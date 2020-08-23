<?php

/*
 * This file is part of the GraphAware Neo4j Client package.
 *
 * (c) GraphAware Limited <http://graphaware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GraphAware\Neo4j\Client\HttpDriver;

use GraphAware\Common\Driver\PipelineInterface;
use GraphAware\Common\Driver\SessionInterface;
use GraphAware\Common\Result\ResultCollection;
use GraphAware\Neo4j\Client\Exception\Neo4jException;
use GraphAware\Neo4j\Client\Formatter\ResponseFormatter;
use Http\Client\Exception;
use Http\Client\Exception\HttpException;
use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use stdClass;

class SessionApi4 implements SessionInterface
{
    private ResponseFormatter $responseFormatter;
    private RequestFactory $requestFactory;
    private string $transactionEndpoint;
    private HttpClient $httpClient;
    public ?Transaction $transaction = null;
    private string $authorizationHeader;

    public function __construct(ResponseFormatter $responseFormatter, RequestFactory $requestFactory, HttpClient $httpClient, string $transactionEndpoint, string $authorizationHeader)
    {
        $this->responseFormatter = $responseFormatter;
        $this->requestFactory = $requestFactory;
        $this->httpClient = $httpClient;
        $this->transactionEndpoint = $transactionEndpoint;
        $this->authorizationHeader = $authorizationHeader;
    }

    public function run($statement, array $parameters = [], $tag = null)
    {
        $pipeline = $this->createPipeline($statement, $parameters, $tag);
        $response = $pipeline->run();

        return $response->results()[0];
    }

    public function close(): void
    {
    }

    public function transaction(): Transaction
    {
        if ($this->transaction instanceof Transaction) {
            throw new RuntimeException('A transaction is already bound to this session');
        }

        return new Transaction($this);
    }

    public function createPipeline($query = null, array $parameters = [], $tag = null)
    {
        $pipeline = new Pipeline($this);

        if (null !== $query) {
            $pipeline->push($query, $parameters, $tag);
        }

        return $pipeline;
    }

    /**
     * @throws JsonException
     * @throws Neo4jException
     * @throws Exception
     */
    public function flush(PipelineInterface $pipeline): \GraphAware\Neo4j\Client\Result\ResultCollection
    {
        if (!$pipeline instanceof Pipeline) {
            throw new RuntimeException('Pipeline must be an instance of: '.Pipeline::class);
        }
        $request = $this->prepareRequest($pipeline);
        $data = $this->handleRequest($request);

        return $this->responseFormatter->format($data, $pipeline->statements());
    }

    /**
     * @throws JsonException
     */
    private function prepareRequest(Pipeline $pipeline): RequestInterface
    {
        $statements = $this->formatStatements($pipeline->statements());

        $body = json_encode(compact('statements'), JSON_THROW_ON_ERROR);

        return $this->requestFactory->createRequest('POST', $this->transactionEndpoint.'/commit', $this->headers(), $body);
    }

    private function formatParams(array $params): array
    {
        foreach ($params as $key => $v) {
            if (is_array($v)) {
                if (empty($v)) {
                    $params[$key] = new stdClass();
                } else {
                    $params[$key] = $this->formatParams($params[$key]);
                }
            }
        }

        return $params;
    }

    /**
     * @throws Exception
     * @throws Neo4jException
     * @throws JsonException
     */
    public function begin(): ResponseInterface
    {
        $request = $this->requestFactory->createRequest('POST', $this->transactionEndpoint, $this->headers());

        try {
            return $this->httpClient->sendRequest($request);
        } catch (HttpException $e) {
            throw $this->decorateException($e);
        }
    }

    /**
     * @throws JsonException
     * @throws Neo4jException
     * @throws Exception
     */
    public function pushToTransaction(int $id, array $statementsStack): ResultCollection
    {
        $statements = $this->formatStatements($statementsStack);

        $body = json_encode(compact('statements'), JSON_THROW_ON_ERROR);

        $request = $this->requestFactory->createRequest(
            'POST',
            $this->transactionEndpoint.'/'.$id,
            $this->headers(),
            $body
        );

        $data = $this->handleRequest($request);

        return $this->responseFormatter->format($data, $statementsStack);
    }

    /**
     * @throws Neo4jException
     * @throws JsonException
     * @throws Exception
     */
    public function commitTransaction(int $id): void
    {
        $request = $this->requestFactory->createRequest(
            'POST',
            $this->transactionEndpoint.'/'.$id.'/commit',
            $this->headers()
        );
        $this->handleRequest($request);
    }

    /**
     * @throws Neo4jException
     * @throws Exception
     * @throws JsonException
     */
    public function rollbackTransaction(int $id): void
    {
        $request = $this->requestFactory->createRequest(
            'DELETE',
            $this->transactionEndpoint.'/'.$id,
            $this->headers()
        );
        $this->handleRequest($request);
    }

    /**
     * @throws JsonException
     *
     * @return HttpException|Neo4jException
     */
    private function decorateException(HttpException $e): Exception
    {
        $json = $e->getResponse()->getBody()->__toString();
        $body = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($body['code'])) {
            return $e;
        }
        $msg = sprintf('Neo4j Exception with code "%s" and message "%s"', $body['errors'][0]['code'], $body['errors'][0]['message']);
        $exception = new Neo4jException($msg, 0, $e);
        $exception->setNeo4jStatusCode($body['errors'][0]['code']);

        return $exception;
    }

    private function formatStatements(array $statementsStack): array
    {
        $statements = [];
        foreach ($statementsStack as $statement) {
            $st = [
                'statement' => $statement->text(),
                'resultDataContents' => ['REST', 'GRAPH'],
                'includeStats' => true,
            ];
            if (!empty($statement->parameters())) {
                $st['parameters'] = $this->formatParams($statement->parameters());
            }
            $statements[] = $st;
        }

        return $statements;
    }

    /**
     * @throws Neo4jException
     */
    private function throwIfErrorIsReceived(ResponseInterface $response): array
    {
        try {
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            if ($response->getStatusCode() >= 500) {
                throw new Neo4jException('Server error');
            }
            throw new Neo4jException('Invalid json format');
        }

        /* @noinspection DuplicatedCode */
        if (!empty($data['errors'])) {
            $msg = sprintf('Neo4j Exception with code "%s" and message "%s"', $data['errors'][0]['code'], $data['errors'][0]['message']);
            $exception = new Neo4jException($msg);
            $exception->setNeo4jStatusCode($data['errors'][0]['code']);

            throw $exception;
        }

        return $data;
    }

    /**
     * @throws Exception
     * @throws JsonException
     * @throws Neo4jException
     */
    private function handleRequest(RequestInterface $request): array
    {
        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (HttpException $e) {
            throw $this->decorateException($e);
        }

        return $this->throwIfErrorIsReceived($response);
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json;charset=UTF-8',
            'Content-Type' => 'application/json',
            'Authorization' => $this->authorizationHeader,
        ];
    }
}
