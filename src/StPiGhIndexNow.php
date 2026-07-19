<?php declare(strict_types=1);

namespace StPiGh\IndexNow;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class StPiGhIndexNow extends Plugin
{
    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->ensureKeyAndFile();
    }

    /**
     * Beim Aktivieren: falls noch kein Key gesetzt ist, einen zufaelligen erzeugen
     * und die Key-Datei public/<key>.txt schreiben (Besitznachweis fuer IndexNow).
     */
    private function ensureKeyAndFile(): void
    {
        /** @var SystemConfigService $config */
        $config = $this->container->get(SystemConfigService::class);

        $key = trim((string) $config->get('StPiGhIndexNow.config.key'));
        if ($key === '' || !preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $key)) {
            $key = bin2hex(random_bytes(16));
            $config->set('StPiGhIndexNow.config.key', $key);
        }

        if ($config->get('StPiGhIndexNow.config.endpoint') === null || $config->get('StPiGhIndexNow.config.endpoint') === '') {
            $config->set('StPiGhIndexNow.config.endpoint', 'https://api.indexnow.org/indexnow');
        }

        $projectDir = (string) $this->container->getParameter('kernel.project_dir');
        $publicDir = $projectDir . '/public';
        if (is_dir($publicDir) && is_writable($publicDir)) {
            @file_put_contents($publicDir . '/' . $key . '.txt', $key);
        }
    }
}
