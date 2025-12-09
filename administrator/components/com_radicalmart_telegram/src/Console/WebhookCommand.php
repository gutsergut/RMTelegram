<?php
/*
 * @package     com_radicalmart_telegram (admin)
 * CLI command for managing Telegram bot webhook
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Console;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WebhookCommand extends AbstractCommand
{
    protected static $defaultName = 'com_radicalmart_telegram:webhook';

    protected function configure(): void
    {
        $this->setDescription('Manage Telegram bot webhook (set/delete/info)');
        $this->addArgument('action', InputArgument::REQUIRED, 'Action: set, delete, or info');
        $this->addOption('url', null, InputOption::VALUE_OPTIONAL, 'Custom webhook URL (for set action)');
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $action = strtolower($input->getArgument('action'));
        $params = ComponentHelper::getParams('com_radicalmart_telegram');
        $botToken = $params->get('bot_token', '');

        if (empty($botToken)) {
            $output->writeln('<error>Bot token not configured in component settings.</error>');
            return 1;














































































































































}    }        }            return 1;            $output->writeln('<error>Failed to get webhook info: ' . $e->getMessage() . '</error>');        } catch (\Exception $e) {            return 0;            }                }                    $output->writeln('  Allowed updates: ' . implode(', ', $info['allowed_updates']));                if (!empty($info['allowed_updates'])) {                                }                    $output->writeln('  Max connections: ' . $info['max_connections']);                if (!empty($info['max_connections'])) {                                }                    $output->writeln('  IP address: ' . $info['ip_address']);                if (!empty($info['ip_address'])) {                                }                    $output->writeln('  <error>Error message: ' . ($info['last_error_message'] ?? 'N/A') . '</error>');                    $output->writeln('  <error>Last error: ' . date('Y-m-d H:i:s', $info['last_error_date']) . '</error>');                if (!empty($info['last_error_date'])) {                                $output->writeln('  Pending updates: ' . ($info['pending_update_count'] ?? 0));                $output->writeln('  Has custom certificate: ' . ($info['has_custom_certificate'] ? 'Yes' : 'No'));            if (!empty($info['url'])) {                        $output->writeln('  URL: ' . ($info['url'] ?: '<comment>(not set)</comment>'));            $output->writeln('<info>Webhook Info:</info>');                        $info = $data['result'];            }                throw new \Exception($data['description'] ?? 'Unknown API error');            if (!$data || !isset($data['ok']) || !$data['ok']) {            $data = json_decode($response, true);            }                throw new \Exception('Connection error');            if ($response === false) {            $response = @file_get_contents($apiUrl);            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/getWebhookInfo';        try {    {    protected function getWebhookInfo(OutputInterface $output, string $botToken): int    }        }            return 1;            $output->writeln('<error>Failed to delete webhook: ' . $e->getMessage() . '</error>');        } catch (\Exception $e) {            return 0;            $output->writeln('<info>✓ Webhook deleted successfully!</info>');            }                throw new \Exception($data['description'] ?? 'Unknown API error');            if (!$data || !isset($data['ok']) || !$data['ok']) {            $data = json_decode($response, true);            }                throw new \Exception('Connection error');            if ($response === false) {            $response = @file_get_contents($apiUrl);            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/deleteWebhook';        try {        $output->writeln('<info>Deleting webhook...</info>');    {    protected function deleteWebhook(OutputInterface $output, string $botToken): int    }        }            return 1;            $output->writeln('<error>Failed to set webhook: ' . $e->getMessage() . '</error>');        } catch (\Exception $e) {            return 0;            $output->writeln('<info>✓ Webhook set successfully!</info>');            }                throw new \Exception($data['description'] ?? 'Unknown API error');            if (!$data || !isset($data['ok']) || !$data['ok']) {            $data = json_decode($response, true);            }                throw new \Exception('Connection error: ' . $error);            if ($response === false) {            curl_close($ch);            $error = curl_error($ch);            $response = curl_exec($ch);            curl_setopt($ch, CURLOPT_TIMEOUT, 30);            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));            curl_setopt($ch, CURLOPT_POST, true);            $ch = curl_init($apiUrl);                        $postData = ['url' => $webhookUrl];            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/setWebhook';        try {        $output->writeln('<info>Setting webhook to: ' . $webhookUrl . '</info>');        }            $webhookUrl = Uri::root() . 'index.php?option=com_radicalmart_telegram&task=webhook.receive&secret=' . urlencode($webhookSecret);            }                return 1;                $output->writeln('<error>Webhook secret not configured. Set it in component settings or use --url option.</error>');            if (empty($webhookSecret)) {            $webhookSecret = $params->get('webhook_secret', '');        } else {            $webhookUrl = $customUrl;        if ($customUrl) {                $customUrl = $input->getOption('url');    {    protected function setWebhook(InputInterface $input, OutputInterface $output, string $botToken, $params): int    }        }                return 1;                $output->writeln('<error>Unknown action: ' . $action . '. Use: set, delete, or info</error>');            default:                return $this->getWebhookInfo($output, $botToken);            case 'status':            case 'info':                return $this->deleteWebhook($output, $botToken);            case 'remove':            case 'delete':                return $this->setWebhook($input, $output, $botToken, $params);            case 'set':        switch ($action) {        }
