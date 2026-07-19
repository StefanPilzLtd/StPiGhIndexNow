<?php declare(strict_types=1);

namespace StPiGh\IndexNow\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class KeyManager
{
    public function __construct(
        private readonly SystemConfigService $systemConfig,
        private readonly string $projectDir
    ) {
    }

    public function getKey(): string
    {
        $key = trim((string) $this->systemConfig->get('StPiGhIndexNow.config.key'));
        if ($key === '' || !preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $key)) {
            $key = bin2hex(random_bytes(16));
            $this->systemConfig->set('StPiGhIndexNow.config.key', $key);
        }

        return $key;
    }

    public function getEndpoint(): string
    {
        $endpoint = trim((string) $this->systemConfig->get('StPiGhIndexNow.config.endpoint'));

        return $endpoint !== '' ? $endpoint : 'https://api.indexnow.org/indexnow';
    }

    public function isEnabled(): bool
    {
        return (bool) $this->systemConfig->get('StPiGhIndexNow.config.enabled');
    }

    /**
     * Schreibt public/<key>.txt und gibt den absoluten Pfad zurueck (oder null bei Fehler).
     */
    public function ensureKeyFile(): ?string
    {
        $key = $this->getKey();
        $publicDir = $this->projectDir . '/public';
        if (!is_dir($publicDir) || !is_writable($publicDir)) {
            return null;
        }

        $path = $publicDir . '/' . $key . '.txt';
        if (@file_put_contents($path, $key) === false) {
            return null;
        }

        return $path;
    }
}
