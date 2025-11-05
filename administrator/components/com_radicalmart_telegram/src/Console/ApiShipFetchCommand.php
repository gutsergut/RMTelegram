<?php
/*
 * Console command: com_radicalmart_telegram:apiship:fetch
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Console;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Component\RadicalMartTelegram\Administrator\Helper\ApiShipFetchHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ApiShipFetchCommand extends AbstractCommand
{
    protected static $defaultName = 'com_radicalmart_telegram:apiship:fetch';
    protected static $defaultDescription = 'Fetch ApiShip pickup points into DB (weekly full update).';

    protected function configure(): void
    {
        $this->addOption('providers', null, InputOption::VALUE_REQUIRED, 'Comma-separated providers list (e.g. yataxi,cdek,x5)');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $providersList = null;

        if ($input->getOption('providers'))
        {
            $list = (string) $input->getOption('providers');
            $providersList = array_filter(array_map('trim', explode(',', $list)));
        }

        $result = ApiShipFetchHelper::fetchAllPoints($providersList);

        if ($result['success'])
        {
            $output->writeln('<info>' . $result['message'] . '</info>');
            return 0;
        }
        else
        {
            $output->writeln('<error>' . $result['message'] . '</error>');
            return 1;
        }
    }
}

