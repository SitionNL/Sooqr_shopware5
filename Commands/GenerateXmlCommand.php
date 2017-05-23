<?php
namespace Shopware\SitionSooqr\Commands;

use Shopware\Commands\ShopwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;
use Shopware\SitionSooqr\Components\SooqrXml;

class GenerateXmlCommand extends ShopwareCommand
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('sition:sooqr:xml')
            ->setDescription('Generate a xml for 1 or more shops')
            ->setHelp(<<<EOF
The <info>%command.name%</info> Generate a xml for 1 or more shops.
EOF
            )
            ->addArgument(
                'shops',
                InputArgument::REQUIRED,
                'Comma-separated list of shop ids'
            )
            ->addArgument('force')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $shopIds = explode(',', trim($input->getArgument('shops')));
        $force = !!$input->getArgument('force');

        try
        {
            foreach ($shopIds as $key => $shopId)
            {
                $sooqr = new SooqrXml($shopId);

                $startTime = microtime(true);
                $output->writeln("<info>Start building xml for shop {$shopId}</info>");

                if( $force )
                {
                    $echo = false;
                    $sooqr->buildXml($echo, $force);
                }
                else
                {
                    $maxSeconds = 3 * 60 * 60; // xml can be 3 hours old max

                    if( $sooqr->needBuilding($maxSeconds) )
                    {
                        $echoOutput = false;
                        $sooqr->buildXml($echoOutput);
                    }
                }

                $endTime = microtime(true);
                $time = $endTime - $startTime;

                $output->writeln("<info>Done building xml for shop {$shopId} in {$time} seconds.</info>");
            }
        }
        catch(Exception $ex)
        {
            $output->writeln("<info>Error: {$ex->getMessage()}</info>");
        }

    }
}
