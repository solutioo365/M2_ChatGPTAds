<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Solutioo\ChatGptProductSearch\Model\Feed\StatusProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly StatusProvider $statusProvider
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('solutioo:chatgpt:feed:status')
            ->setDescription('Zeigt den ChatGPT-Feed-Status je Store');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($output) {
            foreach ($this->statusProvider->getAllStores() as $store) {
                $output->writeln(sprintf(
                    'Store %d %s | aktiv=%s | readiness=%d%% | file=%s | %s',
                    $store['store_id'],
                    $store['store_code'],
                    $store['enabled'] ? 'ja' : 'nein',
                    $store['readiness'],
                    $store['has_file'] ? 'ja' : 'nein',
                    $store['feed_url']
                ));
            }
        });
        return Command::SUCCESS;
    }
}
