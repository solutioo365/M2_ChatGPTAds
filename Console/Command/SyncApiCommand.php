<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Solutioo\ChatGptProductSearch\Model\Feed\Publisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncApiCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly Publisher $publisher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('solutioo:chatgpt:feed:sync-api')
            ->setDescription('Synchronisiert Produkte per OpenAI Commerce/Ads API')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Store-ID', '1')
            ->addOption('sku', null, InputOption::VALUE_REQUIRED, 'Nur diese SKU übertragen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = (int) $input->getOption('store');
        $sku = trim((string) ($input->getOption('sku') ?? ''));
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($storeId, $sku, $output) {
            $result = $this->publisher->syncApi($storeId, [], [], $sku !== '' ? $sku : null);
            $style = !empty($result['success']) ? 'info' : 'comment';
            $output->writeln('<' . $style . '>' . $result['message'] . '</' . $style . '>');
        });
        return Command::SUCCESS;
    }
}
