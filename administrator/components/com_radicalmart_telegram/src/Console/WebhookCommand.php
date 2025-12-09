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
        }

        switch ($action) {
            case 'set':
                return $this->setWebhook($input, $output, $botToken, $params);
            case 'delete':
            case 'remove':
                return $this->deleteWebhook($output, $botToken);
            case 'info':
            case 'status':
                return $this->getWebhookInfo($output, $botToken);
            default:
                $output->writeln('<error>Unknown action: ' . $action . '. Use: set, delete, or info</error>');
                return 1;
        }
    }

    protected function setWebhook(InputInterface $input, OutputInterface $output, string $botToken, $params): int
    {
        $customUrl = $input->getOption('url');

        if ($customUrl) {
            $webhookUrl = $customUrl;
        } else {
            $webhookSecret = $params->get('webhook_secret', '');
            if (empty($webhookSecret)) {
                $output->writeln('<error>Webhook secret not configured. Set it in component settings or use --url option.</error>');
                return 1;
            }
            $webhookUrl = Uri::root() . 'index.php?option=com_radicalmart_telegram&task=webhook.receive&secret=' . urlencode($webhookSecret);
        }

        $output->writeln('<info>Setting webhook to: ' . $webhookUrl . '</info>');

        try {
            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/setWebhook';
            $postData = ['url' => $webhookUrl];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new \Exception('Connection error: ' . $error);
            }

            $data = json_decode($response, true);
            if (!$data || !isset($data['ok']) || !$data['ok']) {
                throw new \Exception($data['description'] ?? 'Unknown API error');
            }

            $output->writeln('<info>✓ Webhook set successfully!</info>');
            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to set webhook: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    protected function deleteWebhook(OutputInterface $output, string $botToken): int
    {
        $output->writeln('<info>Deleting webhook...</info>');

        try {
            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/deleteWebhook';
            $response = @file_get_contents($apiUrl);

            if ($response === false) {
                throw new \Exception('Connection error');
            }

            $data = json_decode($response, true);
            if (!$data || !isset($data['ok']) || !$data['ok']) {
                throw new \Exception($data['description'] ?? 'Unknown API error');
            }

            $output->writeln('<info>✓ Webhook deleted successfully!</info>');
            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to delete webhook: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }

    protected function getWebhookInfo(OutputInterface $output, string $botToken): int
    {
        try {
            $apiUrl = 'https://api.telegram.org/bot' . $botToken . '/getWebhookInfo';
            $response = @file_get_contents($apiUrl);

            if ($response === false) {
                throw new \Exception('Connection error');
            }

            $data = json_decode($response, true);
            if (!$data || !isset($data['ok']) || !$data['ok']) {
                throw new \Exception($data['description'] ?? 'Unknown API error');
            }

            $info = $data['result'];

            $output->writeln('<info>Webhook Info:</info>');
            $output->writeln('  URL: ' . ($info['url'] ?: '<comment>(not set)</comment>'));

            if (!empty($info['url'])) {
                $output->writeln('  Has custom certificate: ' . ($info['has_custom_certificate'] ? 'Yes' : 'No'));
                $output->writeln('  Pending updates: ' . ($info['pending_update_count'] ?? 0));

                if (!empty($info['last_error_date'])) {
                    $output->writeln('  <error>Last error: ' . date('Y-m-d H:i:s', $info['last_error_date']) . '</error>');
                    $output->writeln('  <error>Error message: ' . ($info['last_error_message'] ?? 'N/A') . '</error>');
                }

                if (!empty($info['ip_address'])) {
                    $output->writeln('  IP address: ' . $info['ip_address']);
                }

                if (!empty($info['max_connections'])) {
                    $output->writeln('  Max connections: ' . $info['max_connections']);
                }

                if (!empty($info['allowed_updates'])) {
                    $output->writeln('  Allowed updates: ' . implode(', ', $info['allowed_updates']));
                }
            }

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed to get webhook info: ' . $e->getMessage() . '</error>');
            return 1;
        }
    }
}
