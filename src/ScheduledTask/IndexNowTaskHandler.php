<?php declare(strict_types=1);

namespace StPiGh\IndexNow\ScheduledTask;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use StPiGh\IndexNow\Service\IndexNowClient;
use StPiGh\IndexNow\Service\KeyManager;
use StPiGh\IndexNow\Service\UrlCollector;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: IndexNowTask::class)]
class IndexNowTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly UrlCollector $collector,
        private readonly IndexNowClient $client,
        private readonly KeyManager $keyManager,
        private readonly SystemConfigService $systemConfig
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        if (!$this->keyManager->isEnabled()) {
            return;
        }

        $key = $this->keyManager->getKey();
        $this->keyManager->ensureKeyFile();

        $sinceStr = (string) $this->systemConfig->get('StPiGhIndexNow.config.lastRun');
        $since = null;
        if ($sinceStr !== '') {
            try {
                $since = new \DateTimeImmutable($sinceStr);
            } catch (\Throwable $e) {
                $since = null;
            }
        }

        foreach ($this->collector->collect($since, $key) as $group) {
            if (!$group['public'] || $group['urls'] === []) {
                continue;
            }
            $this->client->submit($group['host'], $group['keyLocation'], $group['urls']);
        }

        $this->systemConfig->set('StPiGhIndexNow.config.lastRun', (new \DateTimeImmutable())->format('Y-m-d H:i:s'));
    }
}
