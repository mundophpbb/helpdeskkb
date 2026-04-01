<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\migrations;

class v1002_article_menu extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['helpdeskkb_version']) && version_compare($this->config['helpdeskkb_version'], '1.0.2', '>=');
    }

    static public function depends_on()
    {
        return ['\mundophpbb\helpdeskkb\migrations\v1001_mode'];
    }

    public function update_data()
    {
        return [
            ['module.add', [
                'acp',
                'ACP_HELPDESKKB_TITLE',
                [
                    'module_basename' => '\\mundophpbb\\helpdeskkb\\acp\\main_module',
                    'modes' => ['article_new'],
                ],
            ]],
            ['config.update', ['helpdeskkb_version', '1.0.2']],
        ];
    }
}
