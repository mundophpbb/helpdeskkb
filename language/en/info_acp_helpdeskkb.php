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
    'ACP_HELPDESKKB_TITLE' => 'Help Desk Knowledge Base',
    'ACP_HELPDESKKB_TAB_SETTINGS' => 'Settings',
    'ACP_HELPDESKKB_TAB_STATS' => 'Statistics',
    'ACP_HELPDESKKB_TAB_CATEGORIES' => 'Created categories',
    'ACP_HELPDESKKB_TAB_NEW_CATEGORY' => 'New category',
    'ACP_HELPDESKKB_TAB_ARTICLES' => 'Created articles',
    'ACP_HELPDESKKB_TAB_NEW_ARTICLE' => 'New article',
]);
