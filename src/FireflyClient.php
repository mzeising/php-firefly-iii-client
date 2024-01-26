<?php
declare(strict_types=1);

namespace MZeising;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Log\LoggerInterface;

/**
 * @see https://api-docs.firefly-iii.org/
 */
class FireflyClient
{
    public const TRANSACTION_TYPE_WITHDRAWAL = 'withdrawal';
    public const TRANSACTION_TYPE_DEPOSIT = 'deposit';

    private LoggerInterface $logger;
    private Client $httpClient;
    private string $personalAccessToken;

    public function __construct(string $baseUri, string $personalAccessToken, LoggerInterface $logger)
    {
        $this->httpClient = new Client([
            'base_uri' => $baseUri
        ]);
        $this->personalAccessToken = $personalAccessToken;
        $this->logger = $logger;
    }

    /**
     * @param string $method
     * @param string $path
     * @param string|null $body
     * @param array|null $query
     * @return array|string|null
     * @throws GuzzleException
     */
    private function request(string $method, string $path, ?string $body = null, ?array $query = null): array|string|null
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->personalAccessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.api+json'
            ]
        ];
        if ($body !== null) {
            $options['body'] = $body;
        }
        if ($query !== null) {
            $options['query'] = $query;
        }
        $res = $this->httpClient->request($method, $path, $options);
        // Guzzle prüft selber den response code und wirft entsprechende Exceptions
        $body = (string)$res->getBody();
        if ($res->getHeaderLine('Content-Type') === 'application/vnd.api+json') {
            $body = json_decode($body, true);
        }
        return $body;
    }

    /**
     * Buchungen eines Kontos anfragen
     *
     * @param string $accountId
     * @param int $page
     * @param int $limit
     * @return array
     * @throws GuzzleException
     */
    #[ArrayShape([
        'data' => [
            [
                'id' => 'string',
                'attributes' => [
                    'created_at' => 'string',
                    'transactions' => [
                        [
                            'book_date' => 'string'
                        ]
                    ]
                ],
            ]
        ]
    ])]
    public function queryTransactionsOfAccount(string $accountId, int $page = 1, int $limit = 50): array
    {
        return $this->request('GET', "/api/v1/accounts/{$accountId}/transactions", null, [
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * Neue Buchung speichern
     *
     * @throws ClientException 422 falls ein Duplikat erkannt wurde
     * @throws GuzzleException
     */
    #[ArrayShape(['data' => ['id' => 'string']])]
    public function storeTransaction(#[ArrayShape([
        'error_if_duplicate_hash' => 'bool',
        'transactions' => [[
            'type' => 'string', 'date' => 'string', 'amount' => 'double', 'description' => 'string', 'book_date' => 'string',
            'source_id' => 'string', 'source_name' => 'string', 'destination_id' => 'string', 'destination_name' => 'string'
        ]]])] array $body): array
    {
        return $this->request('POST', "/api/v1/transactions", json_encode($body));
    }

    /**
     * Buchung ändern
     *
     * - Quell- / Zielkonto muss mindestens (wieder) übergeben werden, auch wenn nur die Schlagwörter geändert werden!
     * - Schlagwörter werden bei Bedarf erstellt und anhand des Namens wieder erkannt
     *
     * @throws GuzzleException
     */
    public function updateTransaction(string $id, #[ArrayShape(['transactions' => [['source_id' => 'string', 'destination_id' => 'string', 'tags' => ['string']]]])] array $body): void
    {
        $this->request('PUT', "/api/v1/transactions/{$id}", json_encode($body));
    }
}