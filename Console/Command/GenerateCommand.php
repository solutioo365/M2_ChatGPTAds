<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;
use Solutioo\ChatGptProductSearch\Model\Config;
use Solutioo\ChatGptProductSearch\Model\Feed\Publisher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly Config $config,
        private readonly Publisher $publisher,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('solutioo:chatgpt:feed:generate')
            ->setDescription('Erzeugt den ChatGPT-Produktfeed')
            ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store-ID (leer = alle aktiven)')
            ->addOption('sftp', null, InputOption::VALUE_NONE, 'Anschließend per SFTP hochladen')
            ->addOption('api', null, InputOption::VALUE_NONE, 'Anschließend per OpenAI API syncen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($input, $output) {
            $storeOption = $input->getOption('store');
            $storeIds = $storeOption !== null && $storeOption !== ''
                ? [(int) $storeOption]
                : $this->enabledStoreIds();

            foreach ($storeIds as $storeId) {
                $result = $this->publisher->publish(
                    $storeId,
                    (bool) $input->getOption('sftp'),
                    (bool) $input->getOption('api')
                );
                $output->writeln(sprintf(
                    '<info>Store %d:</info> %d Produkte, %d Varianten → %s (%d ms)',
                    $storeId,
                    $result['product_count'],
                    $result['variant_count'],
                    $result['file'],
                    $result['duration_ms']
                ));
            }
        });
        return Command::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function enabledStoreIds(): array
    {
        $ids = [];
        foreach ($this->storeManager->getStores() as $store) {
            $id = (int) $store->getId();
            if ($store->getIsActive() && $this->config->isEnabled($id)) {
                $ids[] = $id;
            }
        }
        return $ids !== [] ? $ids : [1];
    }
}
