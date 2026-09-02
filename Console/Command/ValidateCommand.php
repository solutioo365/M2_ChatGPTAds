<?php

declare(strict_types=1);

namespace Solutioo\ChatGptProductSearch\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Solutioo\ChatGptProductSearch\Model\Feed\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateCommand extends Command
{
    public function __construct(
        private readonly State $appState,
        private readonly Generator $generator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('solutioo:chatgpt:feed:validate')
            ->setDescription('Validiert den ChatGPT-Feed gegen die OpenAI-Pflichtfelder')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Store-ID', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeId = (int) $input->getOption('store');
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, function () use ($storeId, $output) {
            $report = $this->generator->validateOnly($storeId);
            $output->writeln(sprintf(
                'Produkte: %d | gültig: %d | ungültig: %d | Warnungen: %d',
                $report['product_count'],
                $report['valid'],
                $report['invalid'],
                count($report['warnings'])
            ));
            foreach (array_slice($report['errors'], 0, 20) as $error) {
                $output->writeln(sprintf(
                    '<error>%s %s: %s</error>',
                    $error['product_id'] ?? '',
                    $error['field'] ?? '',
                    $error['message'] ?? ''
                ));
            }
        });
        return Command::SUCCESS;
    }
}
