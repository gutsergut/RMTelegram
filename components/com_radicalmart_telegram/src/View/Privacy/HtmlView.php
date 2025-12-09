<?php
/*
 * @package     com_radicalmart_telegram (site)
 * Privacy View - управление согласиями и удаление данных
 */

namespace Joomla\Component\RadicalMartTelegram\Site\View\Privacy;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\TelegramUserHelper;
use Joomla\Component\RadicalMartTelegram\Site\Helper\ConsentHelper;

class HtmlView extends BaseHtmlView
{
    protected $params;
    public $tgUser = null;
    public $consents = [];
    public $documentUrls = [];

    public function display($tpl = null)
    {
        $lang = Factory::getLanguage();
        $lang->load('com_radicalmart_telegram', JPATH_SITE);

        $this->params = Factory::getApplication()->getParams('com_radicalmart_telegram');
        $this->tgUser = TelegramUserHelper::getCurrentUser();

        $chatId = $this->tgUser['chat_id'] ?? 0;
        if ($chatId > 0) {
            $this->consents = ConsentHelper::getConsents($chatId);
        }

        $this->documentUrls = ConsentHelper::getAllDocumentUrls();

        $app = Factory::getApplication();

        if ($app->getTemplate() !== 'yootheme') {
            $app->setTemplate('yootheme');
        }

        HTMLHelper::_('jquery.framework');

        $doc = $app->getDocument();
        $wa = $doc->getWebAssetManager();

        $wa->registerAndUseStyle('yootheme.theme', 'templates/yootheme_cacao/css/theme.9.css?1745431273');
        $wa->registerAndUseStyle('yootheme.custom', 'templates/yootheme_cacao/css/custom.css?4.5.9');

        $wa->registerAndUseScript('uikit.js', 'templates/yootheme/vendor/assets/uikit/dist/js/uikit.min.js?4.5.9', [], ['defer' => false]);
        $wa->registerAndUseScript('yootheme.theme', 'templates/yootheme/js/theme.js?4.5.9', ['uikit.js'], ['defer' => false]);

        parent::display($tpl);
    }
}
