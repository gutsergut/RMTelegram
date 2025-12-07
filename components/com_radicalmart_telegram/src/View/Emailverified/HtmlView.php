<?php
namespace Joomla\Component\RadicalMartTelegram\Site\View\Emailverified;
\defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public string $status = 'error';
    public string $message = '';
    public bool $alreadyVerified = false;

    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $this->status = $app->input->getString('status', 'error');
        $this->alreadyVerified = (bool) $app->input->getInt('already', 0);
        if ($this->status === 'error') {
            $msg = $app->input->getString('msg', '');
            $this->message = $msg ?: Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFICATION_ERROR');
        } else {
            $this->message = $this->alreadyVerified
                ? Text::_('COM_RADICALMART_TELEGRAM_EMAIL_ALREADY_VERIFIED')
                : Text::_('COM_RADICALMART_TELEGRAM_EMAIL_VERIFIED_SUCCESS');
        }
        parent::display($tpl);
    }
}
