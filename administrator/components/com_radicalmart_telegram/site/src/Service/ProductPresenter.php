<?php
/*
 * @package     com_radicalmart_telegram (site)
 */

namespace Joomla\Component\RadicalMartTelegram\Site\Service;

\defined('_JEXEC') or die;

class ProductPresenter
{
    public function toMessage(object $product): string
    {
        $lines = [];
        if (!empty($product->category->title)) { $lines[] = 'Категория: ' . $this->escape($product->category->title); }
        $lines[] = '«' . $this->escape($product->title ?? '') . '»';
        if (!empty($product->manufacturers)) {
            $names = [];
            foreach ($product->manufacturers as $m) { if (!empty($m->title)) { $names[] = $this->escape($m->title); } }
            if ($names) { $lines[] = 'Производитель: ' . implode(', ', $names); }
        }
        if (isset($product->in_stock)) { $lines[] = $product->in_stock ? 'В наличии' : 'Нет в наличии'; }
        if (!empty($product->price) && is_array($product->price)) {
            $p = $product->price;
            if (!empty($p['discount_enable']) && !empty($p['base_string'])) { $lines[] = 'Цена: ' . $p['final_string'] . ' (скидка, было ' . $p['base_string'] . ')'; }
            elseif (!empty($p['final_string'])) { $lines[] = 'Цена: ' . $p['final_string']; }
        }
        if (!empty($product->introtext)) {
            $desc = trim(strip_tags((string) $product->introtext));
            if ($desc !== '') { $lines[] = ''; $lines[] = $this->truncate($desc, 350); }
        }
        return implode("\n", $lines);
    }

    protected function escape(string $text): string
    { return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    protected function truncate(string $text, int $limit): string
    { return (mb_strlen($text, 'UTF-8') <= $limit) ? $text : rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . '…'; }
}

