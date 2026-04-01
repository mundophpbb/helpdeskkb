<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\migrations;

class v1000_install extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['helpdeskkb_version']) && version_compare($this->config['helpdeskkb_version'], '1.0.0', '>=');
    }

    static public function depends_on()
    {
        return ['\\phpbb\\db\\migration\\data\\v330\\v330'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'helpdesk_kb_categories' => [
                    'COLUMNS' => [
                        'category_id'      => ['UINT', null, 'auto_increment'],
                        'category_name'    => ['VCHAR:255', ''],
                        'category_desc'    => ['MTEXT_UNI', ''],
                        'forum_id'         => ['UINT', 0],
                        'department_key'   => ['VCHAR:100', ''],
                        'sort_order'       => ['UINT', 0],
                        'category_enabled' => ['BOOL', 1],
                        'created_time'     => ['TIMESTAMP', 0],
                        'updated_time'     => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'category_id',
                ],
                $this->table_prefix . 'helpdesk_kb_articles' => [
                    'COLUMNS' => [
                        'article_id'              => ['UINT', null, 'auto_increment'],
                        'category_id'             => ['UINT', 0],
                        'article_title'           => ['VCHAR:255', ''],
                        'article_slug'            => ['VCHAR:255', ''],
                        'article_summary'         => ['MTEXT_UNI', ''],
                        'article_text'            => ['MTEXT_UNI', ''],
                        'article_bbcode_uid'      => ['VCHAR:8', ''],
                        'article_bbcode_bitfield' => ['VCHAR:255', ''],
                        'article_bbcode_options'  => ['UINT:11', 7],
                        'article_keywords'        => ['TEXT_UNI', ''],
                        'forum_id'                => ['UINT', 0],
                        'department_key'          => ['VCHAR:100', ''],
                        'sort_order'              => ['UINT', 0],
                        'article_views'           => ['UINT', 0],
                        'article_helpful_yes'     => ['UINT', 0],
                        'article_helpful_no'      => ['UINT', 0],
                        'article_enabled'         => ['BOOL', 1],
                        'created_by'              => ['UINT', 0],
                        'created_time'            => ['TIMESTAMP', 0],
                        'updated_time'            => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'article_id',
                    'KEYS' => [
                        'kb_article_cat' => ['INDEX', 'category_id'],
                        'kb_article_forum' => ['INDEX', 'forum_id'],
                        'kb_article_slug' => ['INDEX', 'article_slug'],
                    ],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'helpdesk_kb_categories',
                $this->table_prefix . 'helpdesk_kb_articles',
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.add', ['helpdeskkb_version', '1.0.0']],
            ['config.add', ['helpdeskkb_enabled', 1]],
            ['config.add', ['helpdeskkb_navbar', 1]],
            ['config.add', ['helpdeskkb_suggestions_enabled', 1]],
            ['config.add', ['helpdeskkb_suggestions_limit', 3]],
            ['module.add', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_HELPDESKKB_TITLE',
            ]],
            ['module.add', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['settings'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['categories'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['articles'],
                ],
            ]],
        ];
    }
}
