<?php
/*
 * @package     plg_system_radicalmart_telegram
 */

namespace Joomla\Plugin\System\Radicalmart_telegram\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Http\Http;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu\AdministratorMenuItem;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Event\Event;

class RadicalMartTelegram extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    protected bool $removeAdministratorMenu = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onRadicalMartAfterChangeOrderStatus' => 'onAfterChangeOrderStatus',
            'onRadicalMartPreprocessSubmenu' => 'onRadicalMartPreprocessSubmenu',
            'onPreprocessMenuItems' => 'onPreprocessMenuItems',
        ];
    }

    public function onRadicalMartPreprocessSubmenu(array &$results, AdministratorMenuItem $parent, Registry $params): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator'))
        {
            return;
        }

        $this->addTelegramSubMenu($results);
    }

    protected function addTelegramSubMenu(array &$results): void
    {
        foreach ($results as $item)
        {
            if ($item instanceof AdministratorMenuItem && $item->link === 'index.php?option=com_radicalmart_telegram')
            {
                return;
            }
        }

        $language = Factory::getApplication()->getLanguage();
        $language->load('com_radicalmart_telegram.sys', JPATH_ADMINISTRATOR);

        $root = new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM',
            'type'      => 'container',
            'link'      => 'index.php?option=com_radicalmart_telegram',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => '',
            'dashboard' => '',
            'scope'     => 'default',
        ]);

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_STATUS',
            'type'      => 'component',
            'link'      => 'index.php?option=com_radicalmart_telegram&view=status',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_LINKS',
            'type'      => 'component',
            'link'      => 'index.php?option=com_radicalmart_telegram&view=links',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_PAYMENTS',
            'type'      => 'component',
            'link'      => 'index.php?option=com_radicalmart_telegram&view=payments',
            'element'   => 'com_radicalmart_telegram',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $root->addChild(new AdministratorMenuItem([
            'title'     => 'COM_RADICALMART_TELEGRAM_MENU_CONFIGURATION',
            'type'      => 'component',
            'link'      => 'index.php?option=com_config&view=component&component=com_radicalmart_telegram',
            'element'   => 'com_config',
            'class'     => '',
            'ajaxbadge' => null,
            'dashboard' => null,
            'scope'     => 'default',
            'params'    => new Registry(),
        ]));

        $results[] = $root;
    }

    public function onPreprocessMenuItems(Event $event): void
    {
        $context  = $event->getArgument(0);
        $children = $event->getArgument(1);

        $this->removeTelegramAdministratorComponentsMenuItem($context, $children);

        $event->setArgument(1, $children);
    }

    protected function removeTelegramAdministratorComponentsMenuItem(?string $context = null, array $children = []): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator') || $context !== 'com_menus.administrator.module' || $this->removeAdministratorMenu)
        {
            return;
        }

        $component = ComponentHelper::getComponent('com_radicalmart_telegram');
        if (!$component || empty($component->id))
        {
            return;
        }

        foreach ($children as $child)
        {
            if ($child instanceof AdministratorMenuItem
                && $child->type === 'component'
                && (int) $child->component_id === (int) $component->id)
            {
                $parent = $child->getParent();
                if ($parent)
                {
                    $parent->removeChild($child);
                }

                $this->removeAdministratorMenu = true;
            }
        }
    }

    public function onAfterChangeOrderStatus(?string $context = null, ?object $order = null,
                                             int $oldStatus = 0, int $newStatus = 0, bool $isNew = false)
    {
        try {
            if (!$order || empty($order->id) || empty($order->created_by)) {
                return;
            }

            // Get chat mapping
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('chat_id')
                ->from($db->quoteName('#__radicalmart_telegram_users'))
                ->where($db->quoteName('user_id') . ' = :uid')
                ->bind(':uid', (int) $order->created_by);
            $chatId = (int) $db->setQuery($query, 0, 1)->loadResult();
            if ($chatId <= 0) {
                return; // user not linked to bot
            }

            // Get token from component params
            $params = Factory::getApplication()->getParams('com_radicalmart_telegram');
            $token  = (string) $params->get('bot_token', '');
            if ($token === '') {
                return;
            }

            // Compose message
            $statusText = '';
            if (!empty($order->status) && !empty($order->status->title)) {
                $statusText = (string) $order->status->title;
            }
            $number = (string) ($order->number ?? ('#' . (int) $order->id));
            $lines = [];
            $lines[] = Text::sprintf('PLG_SYSTEM_RADICALMART_TELEGRAM_ORDER_STATUS_CHANGED', $number);
            if ($statusText !== '') {
                $lines[] = Text::sprintf('PLG_SYSTEM_RADICALMART_TELEGRAM_NEW_STATUS', $statusText);
            }
            $text = implode("\n", $lines);

            // Send via Telegram API
            $http = new Http();
            $http->setOption('transport.curl', [CURLOPT_SSL_VERIFYHOST => 0, CURLOPT_SSL_VERIFYPEER => 0]);
            $url = 'https://api.telegram.org/bot' . $token . '/sendMessage?' . http_build_query([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => 'true',
            ]);
            $response = $http->get($url);
            // Optional: parse $response->body if needed
        } catch (\Throwable $e) {
            // swallow for now; add logging later
        }
    }
}
