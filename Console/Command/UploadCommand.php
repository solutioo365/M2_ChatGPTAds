<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Solutioo\ChatGptProductSearch\Model\Sftp\Uploader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UploadCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly Uploader $uploader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('solutioo:chatgpt:feed:upload')
            ->setDescription('Lädt den erzeugten Feed per SFTP zu OpenAI hoch')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Store-ID', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = (int) $input->getOption('store');
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($storeId, $output) {
            $result = $this->uploader->upload($storeId);
            $output->writeln('<info>' . $result['message'] . '</info>');
        });
        return Command::SUCCESS;
    }
}
