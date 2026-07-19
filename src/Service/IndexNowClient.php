<?php declare(strict_types=1);

namespace StPiGh\IndexNow\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;

class IndexNowClient
{
    private const MAX_URLS_PER_REQUEST = 10000;

    public function __construct(
        private readonly KeyManager $keyManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Meldet URLs eines Hosts an IndexNow. Gibt eine Liste von HTTP-Statuscodes zurueck.
     *
     * @param string[] $urls
     * @return int[]
     */
    public function submit(string $host, string $keyLocation, array $urls): array
    {
        $urls = array_values(array_unique(array_filter($urls)));
        if ($urls === []) {
            return [];
        }

        $key = $this->keyManager->getKey();
        $endpoint = $this->keyManager->getEndpoint();
        $client = HttpClient::create(['timeout' => 20]);
        $statuses = [];

        foreach (array_chunk($urls, self::MAX_URLS_PER_REQUEST) as $chunk) {
            try {
                $response = $client->request('POST', $endpoint, [
                    'json' => [
                        'host' => $host,
                        'key' => $key,
                        'keyLocation' => $keyLocation,
                        'urlList' => array_values($chunk),
                    ],
                ]);
                $status = $response->getStatusCode();
                $statuses[] = $status;
                $this->logger->info('IndexNow submit', [
                    'host' => $host,
                    'count' => count($chunk),
                    'status' => $status,
                ]);
            } catch (\Throwable $e) {
                $statuses[] = 0;
                $this->logger->error('IndexNow submit failed', [
                    'host' => $host,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $statuses;
    }
}
