<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\acp;

class main_info
{
    public function module()
    {
        return [
            'filename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
            'title' => 'ACP_HELPDESKKB_TITLE',
            'modes' => [
                'settings' => [
                    'title' => 'ACP_HELPDESKKB_TAB_SETTINGS',
                    'auth' => 'ext_mundophpbb/helpdeskkb && acl_a_board',
                    'cat' => ['ACP_HELPDESKKB_TITLE'],
                ],
                'category_new' => [
                    'title' => 'ACP_HELPDESKKB_TAB_NEW_CATEGORY',
                    'auth' => 'ext_mundophpbb/helpdeskkb && acl_a_board',
                    'cat' => ['ACP_HELPDESKKB_TITLE'],
                ],
                'categories' => [
                    'title' => 'ACP_HELPDESKKB_TAB_CATEGORIES',
                    'auth' => 'ext_mundophpbb/helpdeskkb && acl_a_board',
                    'cat' => ['ACP_HELPDESKKB_TITLE'],
                ],
                'article_new' => [
                    'title' => 'ACP_HELPDESKKB_TAB_NEW_ARTICLE',
                    'auth' => 'ext_mundophpbb/helpdeskkb && acl_a_board',
                    'cat' => ['ACP_HELPDESKKB_TITLE'],
                ],
                'articles' => [
                    'title' => 'ACP_HELPDESKKB_TAB_ARTICLES',
                    'auth' => 'ext_mundophpbb/helpdeskkb && acl_a_board',
                    'cat' => ['ACP_HELPDESKKB_TITLE'],
                ],
                'stats' => [
                    'title' => 'ACP_HELPDESKKB_TAB_STATS',
                    'auth' => 'ext_mundophpbb/helpdeskkb && acl_a_board',
                    'cat' => ['ACP_HELPDESKKB_TITLE'],
                ],
            ],
        ];
    }
}
