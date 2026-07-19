<?php declare(strict_types=1);

namespace StPiGh\IndexNow\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;

class UrlCollector
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Sammelt je Sales-Channel-Domain die kanonischen URLs (Home, Produkte, Kategorien,
     * Landingpages). $since begrenzt auf seit dann geaenderte Inhalte (null = alles).
     *
     * @return array<int, array{host:string, base:string, keyLocation:string, public:bool, urls:string[]}>
     */
    public function collect(?\DateTimeInterface $since, string $key): array
    {
        $sinceStr = $since?->format('Y-m-d H:i:s');

        $domains = $this->connection->fetchAllAssociative(
            'SELECT scd.url AS url,
                    LOWER(HEX(scd.sales_channel_id)) AS sc,
                    LOWER(HEX(scd.language_id)) AS lang
             FROM sales_channel_domain scd
             JOIN sales_channel s ON s.id = scd.sales_channel_id AND s.active = 1'
        );

        $groups = [];
        $seenHosts = [];

        foreach ($domains as $d) {
            $base = rtrim((string) $d['url'], '/');
            $host = (string) parse_url($base, PHP_URL_HOST);
            if ($host === '' || isset($seenHosts[$host])) {
                continue;
            }
            $seenHosts[$host] = true;

            $paths = array_merge(
                $this->productPaths($d['sc'], $d['lang'], $sinceStr),
                $this->categoryPaths($d['sc'], $d['lang'], $sinceStr),
                $this->landingPaths($d['sc'], $d['lang'], $sinceStr)
            );

            $urls = [];
            if ($since === null) {
                $urls[] = $base . '/';
            }
            foreach ($paths as $p) {
                $p = ltrim((string) $p, '/');
                if ($p !== '') {
                    $urls[] = $base . '/' . $p;
                }
            }

            $groups[] = [
                'host' => $host,
                'base' => $base,
                'keyLocation' => $base . '/' . $key . '.txt',
                'public' => $this->isPublicHost($host),
                'urls' => array_values(array_unique($urls)),
            ];
        }

        return $groups;
    }

    private function productPaths(string $sc, string $lang, ?string $since): array
    {
        $sql = 'SELECT su.seo_path_info
                FROM seo_url su
                JOIN product p ON p.id = su.foreign_key AND p.version_id = UNHEX(:live)
                WHERE su.sales_channel_id = UNHEX(:sc) AND su.language_id = UNHEX(:lang)
                  AND su.is_canonical = 1 AND su.is_deleted = 0
                  AND su.route_name = "frontend.detail.page" AND p.active = 1';
        if ($since !== null) {
            $sql .= ' AND COALESCE(p.updated_at, p.created_at) >= :since';
        }

        return $this->run($sql, $sc, $lang, $since);
    }

    private function categoryPaths(string $sc, string $lang, ?string $since): array
    {
        $sql = 'SELECT su.seo_path_info
                FROM seo_url su
                JOIN category c ON c.id = su.foreign_key AND c.version_id = UNHEX(:live)
                WHERE su.sales_channel_id = UNHEX(:sc) AND su.language_id = UNHEX(:lang)
                  AND su.is_canonical = 1 AND su.is_deleted = 0
                  AND su.route_name = "frontend.navigation.page" AND c.active = 1
                  AND (c.type IS NULL OR c.type = "page")';
        if ($since !== null) {
            $sql .= ' AND COALESCE(c.updated_at, c.created_at) >= :since';
        }

        return $this->run($sql, $sc, $lang, $since);
    }

    private function landingPaths(string $sc, string $lang, ?string $since): array
    {
        // landing_page existiert evtl. nicht in jeder Version -> defensiv
        try {
            $sql = 'SELECT su.seo_path_info
                    FROM seo_url su
                    JOIN landing_page lp ON lp.id = su.foreign_key AND lp.version_id = UNHEX(:live)
                    WHERE su.sales_channel_id = UNHEX(:sc) AND su.language_id = UNHEX(:lang)
                      AND su.is_canonical = 1 AND su.is_deleted = 0
                      AND su.route_name = "frontend.landing.page" AND lp.active = 1';
            if ($since !== null) {
                $sql .= ' AND COALESCE(lp.updated_at, lp.created_at) >= :since';
            }

            return $this->run($sql, $sc, $lang, $since);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function run(string $sql, string $sc, string $lang, ?string $since): array
    {
        $params = ['live' => Defaults::LIVE_VERSION, 'sc' => $sc, 'lang' => $lang];
        if ($since !== null) {
            $params['since'] = $since;
        }

        return $this->connection->fetchFirstColumn($sql, $params);
    }

    private function isPublicHost(string $host): bool
    {
        $host = strtolower($host);
        if ($host === '' || $host === 'localhost' || $host === '::1') {
            return false;
        }
        if (str_starts_with($host, '127.') || str_starts_with($host, '192.168.') || str_starts_with($host, '10.')) {
            return false;
        }

        return str_contains($host, '.');
    }
}
