<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\migrations;

class v1001_mode extends \phpbb\db\migration\migration
{
    public function effectively_installed()
    {
        return isset($this->config['helpdeskkb_version']) && version_compare($this->config['helpdeskkb_version'], '1.0.1', '>=');
    }

    static public function depends_on()
    {
        return ['\mundophpbb\helpdeskkb\migrations\v1000_install'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['helpdeskkb_mode', 'integrated']],
            ['config.update', ['helpdeskkb_version', '1.0.1']],
        ];
    }
}
