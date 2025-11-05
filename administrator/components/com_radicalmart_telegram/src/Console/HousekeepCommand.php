<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Console;

\defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HousekeepCommand extends AbstractCommand
{
    protected static $defaultName = 'com_radicalmart_telegram:housekeep';

    protected function configure(): void
    {
        $this->setDescription('Cleanup old ratelimits and nonces for com_radicalmart_telegram');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $now = new Date();
        $nonceKeepHours = 24;    // keep nonces for 1 day
        $rlKeepHours    = 48;    // keep ratelimits for 2 days

        $nonceThreshold = new Date($now->toUnix() - $nonceKeepHours * 3600);
        $rlThreshold    = new Date($now->toUnix() - $rlKeepHours * 3600);

        try {
            // Cleanup nonces
            $q1 = $db->getQuery(true)
                ->delete($db->quoteName('#__radicalmart_telegram_nonces'))
                ->where($db->quoteName('created') . ' < :d1')
                ->bind(':d1', $nonceThreshold->toSql());
            $db->setQuery($q1)->execute();
        } catch (\Throwable $e) { /* ignore */ }

        try {
            // Cleanup ratelimits (older than threshold)
            $q2 = $db->getQuery(true)
                ->delete($db->quoteName('#__radicalmart_telegram_ratelimits'))
                ->where($db->quoteName('window_start') . ' < :d2')
                ->bind(':d2', $rlThreshold->toSql());
            $db->setQuery($q2)->execute();
        } catch (\Throwable $e) { /* ignore */ }

        $output->writeln('<info>Housekeeping completed.</info>');
        return 0;
    }
}

?>

