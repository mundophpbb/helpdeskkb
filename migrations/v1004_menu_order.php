<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\migrations;

class v1004_menu_order extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['helpdeskkb_version']) && version_compare($this->config['helpdeskkb_version'], '1.0.4', '>=');
    }

    static public function depends_on()
    {
        return ['\mundophpbb\helpdeskkb\migrations\v1003_category_menu'];
    }

    public function update_data()
    {
        return [
            ['module.remove', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['article_new'],
                ],
            ]],
            ['module.remove', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['articles'],
                ],
            ]],
            ['module.remove', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['category_new'],
                ],
            ]],
            ['module.remove', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['categories'],
                ],
            ]],
            ['module.remove', [
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
                    'modes' => ['settings'],
                ],
            ]],
            ['module.add', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['category_new'],
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
                    'modes' => ['article_new'],
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
            ['config.update', ['helpdeskkb_version', '1.0.4']],
        ];
    }
}
