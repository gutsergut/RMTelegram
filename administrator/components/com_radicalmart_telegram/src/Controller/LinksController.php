<?php
/*
 * @package     com_radicalmart_telegram (admin)
 */

namespace Joomla\Component\RadicalMartTelegram\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\Component\RadicalMart\Administrator\Helper\UserHelper as RMUserHelper;

class LinksController extends BaseController
{
    public function unlink(): void
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
            return;
        }
        $chat = $app->input->getInt('chat_id', 0);
        if ($chat <= 0) {
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_ERR_INVALID_CHAT'), 'error');
            $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
            return;
        }
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $q  = $db->getQuery(true)
                ->update($db->quoteName('#__radicalmart_telegram_users'))
                ->set($db->quoteName('user_id') . ' = 0')
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':chat', $chat);
            $db->setQuery($q)->execute();
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_UNLINK_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_UNLINK_ERROR', $e->getMessage()), 'error');
        }
        $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
    }

    public function attach(): void
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
            return;
        }
        $chat   = $app->input->getInt('chat_id', 0);
        $userId = $app->input->getInt('user_id', 0);
        if ($chat <= 0 || $userId <= 0) {
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_ATTACH_ERROR_PARAMS'), 'error');
            $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
            return;
        }
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            // Validate user exists
            $q = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('id') . ' = :uid')
                ->bind(':uid', $userId);
            $exists = (int) $db->setQuery($q, 0, 1)->loadResult();
            if ($exists <= 0) {
                throw new \RuntimeException('User not found');
            }
            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__radicalmart_telegram_users'))
                ->set($db->quoteName('user_id') . ' = :uid')
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':uid', $userId)
                ->bind(':chat', $chat);
            $db->setQuery($upd)->execute();
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_ATTACH_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_ATTACH_ERROR', $e->getMessage()), 'error');
        }
        $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
    }

    public function attachByPhone(): void
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
            return;
        }
        $chat  = $app->input->getInt('chat_id', 0);
        $phone = trim((string) $app->input->get('phone', '', 'string'));
        if ($chat <= 0 || $phone === '') {
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_ATTACH_ERROR_PARAMS'), 'error');
            $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
            return;
        }
        $norm = preg_replace('#[^0-9\+]#', '', $phone);
        if ($norm && $norm[0] === '8' && strlen($norm) === 11) { $norm = '+7' . substr($norm, 1); }
        if ($norm && $norm[0] === '7' && strlen($norm) === 11) { $norm = '+7' . substr($norm, 1); }
        if ($norm && $norm[0] !== '+') { $norm = '+' . $norm; }
        try {
            $user = RMUserHelper::findUser(['phone' => $norm]);
            if (!$user || empty($user->id)) {
                throw new \RuntimeException('User not found by phone');
            }
            $db = Factory::getContainer()->get('DatabaseDriver');
            $upd = $db->getQuery(true)
                ->update($db->quoteName('#__radicalmart_telegram_users'))
                ->set($db->quoteName('user_id') . ' = :uid')
                ->set($db->quoteName('phone') . ' = :ph')
                ->where($db->quoteName('chat_id') . ' = :chat')
                ->bind(':uid', (int) $user->id)
                ->bind(':ph', $norm)
                ->bind(':chat', $chat);
            $db->setQuery($upd)->execute();
            $app->enqueueMessage(Text::_('COM_RADICALMART_TELEGRAM_ATTACH_BY_PHONE_SUCCESS'), 'message');
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::sprintf('COM_RADICALMART_TELEGRAM_ATTACH_BY_PHONE_ERROR', $e->getMessage()), 'error');
        }
        $app->redirect(Route::_('index.php?option=com_radicalmart_telegram&view=links', false));
    }
}

?>
