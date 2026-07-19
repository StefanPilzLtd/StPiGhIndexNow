<?php declare(strict_types=1);

namespace StPiGh\IndexNow\Command;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use StPiGh\IndexNow\Service\IndexNowClient;
use StPiGh\IndexNow\Service\KeyManager;
use StPiGh\IndexNow\Service\UrlCollector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'stpi:indexnow:submit', description: 'Submit canonical URLs to IndexNow (Bing/Yandex)')]
class SubmitCommand extends Command
{
    public function __construct(
        private readonly UrlCollector $collector,
        private readonly IndexNowClient $client,
        private readonly KeyManager $keyManager,
        private readonly SystemConfigService $systemConfig
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('full', null, InputOption::VALUE_NONE, 'Submit ALL URLs (ignore last-run timestamp)')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only URLs changed since (e.g. "2026-07-01")')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only print, do not submit')
            ->addOption('include-local', null, InputOption::VALUE_NONE, 'Also process non-public hosts (preview only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $since = null;
        if (!$input->getOption('full')) {
            $sinceOpt = $input->getOption('since');
            $sinceStr = $sinceOpt ?: (string) $this->systemConfig->get('StPiGhIndexNow.config.lastRun');
            if ($sinceStr !== '') {
                try {
                    $since = new \DateTimeImmutable($sinceStr);
                } catch (\Throwable $e) {
                    $since = null;
                }
            }
        }

        $key = $this->keyManager->getKey();
        $filePath = $this->keyManager->ensureKeyFile();
        $io->writeln(sprintf('Key: <info>%s</info>%s', $key, $filePath ? " (Datei: $filePath)" : ' (Key-Datei NICHT schreibbar!)'));
        $io->writeln('Modus: ' . ($since ? 'inkrementell seit ' . $since->format('Y-m-d H:i:s') : 'VOLL (alle URLs)') . ($dryRun ? ' [DRY-RUN]' : ''));
        $io->newLine();

        $groups = $this->collector->collect($since, $key);
        $totalSubmitted = 0;

        foreach ($groups as $g) {
            $count = count($g['urls']);
            if ($count === 0) {
                continue;
            }

            if (!$g['public'] && !$input->getOption('include-local')) {
                $io->writeln(sprintf('  <comment>%s</comment>: %d URLs — uebersprungen (kein oeffentlicher Host)', $g['host'], $count));
                continue;
            }

            $io->writeln(sprintf('  <info>%s</info>: %d URLs', $g['host'], $count));
            $io->writeln('    Beispiel: ' . implode(', ', array_slice($g['urls'], 0, 3)));

            if ($dryRun || !$g['public']) {
                continue;
            }

            $statuses = $this->client->submit($g['host'], $g['keyLocation'], $g['urls']);
            $io->writeln('    HTTP: ' . implode(', ', $statuses));
            $totalSubmitted += $count;
        }

        if (!$dryRun) {
            $this->systemConfig->set('StPiGhIndexNow.config.lastRun', (new \DateTimeImmutable())->format('Y-m-d H:i:s'));
        }

        $io->newLine();
        $io->success(($dryRun ? 'Dry-Run fertig. ' : 'Fertig. ') . $totalSubmitted . ' URLs gemeldet.');

        return Command::SUCCESS;
    }
}
