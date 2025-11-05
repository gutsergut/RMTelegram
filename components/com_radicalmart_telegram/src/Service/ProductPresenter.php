<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

class ProductPresenter
{
    public function toMessage(object $product): string
    {
        $lines = [];

        // Заголовок и категория
        if (!empty($product->category) && !empty($product->category->title)) {
            $lines[] = 'Категория: ' . $this->escape($product->category->title);
        }
        $lines[] = '«' . $this->escape($product->title ?? '') . '»';

        // Бейджи/производители кратко
        if (!empty($product->manufacturers)) {
            $names = [];
            foreach ($product->manufacturers as $m) {
                if (!empty($m->title)) { $names[] = $this->escape($m->title); }
            }
            if ($names) {
                $lines[] = 'Производитель: ' . implode(', ', $names);
            }
        }

        // Наличие
        if (isset($product->in_stock)) {
            $lines[] = $product->in_stock ? 'В наличии' : 'Нет в наличии';
        }

        // Цена (как в шаблонах: base_string/final_string/discount_enable)
        if (!empty($product->price) && is_array($product->price)) {
            $p = $product->price;
            if (!empty($p['discount_enable']) && !empty($p['base_string'])) {
                $lines[] = 'Цена: ' . $p['final_string'] . ' (скидка, было ' . $p['base_string'] . ')';
            } elseif (!empty($p['final_string'])) {
                $lines[] = 'Цена: ' . $p['final_string'];
            }
        }

        // Короткое описание при наличии
        if (!empty($product->introtext)) {
            $desc = trim(strip_tags((string) $product->introtext));
            if ($desc !== '') {
                $lines[] = '';
                $lines[] = $this->truncate($desc, 350);
            }
        }

        return implode("\n", $lines);
    }

    protected function escape(string $text): string
    {
        // Телеграм с parse_mode=HTML: экранируем спецсимволы
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    protected function truncate(string $text, int $limit): string
    {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . '…';
    }
}

