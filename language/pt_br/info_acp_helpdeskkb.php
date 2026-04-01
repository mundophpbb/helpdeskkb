<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

if (!defined('IN_PHPBB'))
{
    exit;
}

if (empty($lang) || !is_array($lang))
{
    $lang = [];
}

$lang = array_merge($lang, [
    'ACP_HELPDESKKB_TITLE' => 'Base de Conhecimento do Help Desk',
    'ACP_HELPDESKKB_TAB_SETTINGS' => 'Configurações',
    'ACP_HELPDESKKB_TAB_STATS' => 'Estatísticas',
    'ACP_HELPDESKKB_TAB_CATEGORIES' => 'Categorias criadas',
    'ACP_HELPDESKKB_TAB_NEW_CATEGORY' => 'Nova categoria',
    'ACP_HELPDESKKB_TAB_ARTICLES' => 'Artigos criados',
    'ACP_HELPDESKKB_TAB_NEW_ARTICLE' => 'Novo artigo',
]);
