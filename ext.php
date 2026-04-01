<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb;

class ext extends \phpbb\extension\base
{
    const ACP_CATEGORY_LANG = 'ACP_HELPDESKKB_TITLE';
    const ACP_MODULE_BASENAME = '\\mundophpbb\\helpdeskkb\\acp\\main_module';

    public function purge_step($old_state)
    {
        if ($old_state === false || $old_state === '')
        {
            $this->cleanup_helpdeskkb_acp_modules();
            return 'helpdeskkb_cleanup_modules';
        }

        return parent::purge_step($old_state);
    }

    protected function cleanup_helpdeskkb_acp_modules()
    {
        $db = $this->get_container_service('dbal.conn');
        $module_manager = $this->get_container_service('module.manager');
        $modules_table = $this->get_modules_table();

        if (!$db || !$module_manager || $modules_table === '')
        {
            return;
        }

        $sql = 'SELECT module_id, left_id, right_id
            FROM ' . $modules_table . "
            WHERE module_class = 'acp'
                AND (
                    module_basename = '" . $db->sql_escape(self::ACP_MODULE_BASENAME) . "'
                    OR module_langname = '" . $db->sql_escape(self::ACP_CATEGORY_LANG) . "'
                )
            ORDER BY (right_id - left_id) ASC, left_id DESC";
        $result = $db->sql_query($sql);
        $module_rows = [];
        while ($row = $db->sql_fetchrow($result))
        {
            $module_rows[] = $row;
        }
        $db->sql_freeresult($result);

        foreach ($module_rows as $row)
        {
            try
            {
                $module_manager->delete_module((int) $row['module_id'], 'acp');
            }
            catch (\Throwable $e)
            {
                // Raw fallback below will clean any leftover rows or broken trees.
            }
        }

        $this->raw_cleanup_module_subtrees(
            $db,
            $modules_table,
            "module_class = 'acp' AND module_langname = '" . $db->sql_escape(self::ACP_CATEGORY_LANG) . "'"
        );

        $this->raw_cleanup_module_subtrees(
            $db,
            $modules_table,
            "module_class = 'acp' AND module_basename = '" . $db->sql_escape(self::ACP_MODULE_BASENAME) . "'"
        );

        $this->clear_module_cache();
    }

    protected function raw_cleanup_module_subtrees($db, $modules_table, $where_sql)
    {
        while (true)
        {
            $sql = 'SELECT module_id, left_id, right_id
                FROM ' . $modules_table . '
                WHERE ' . $where_sql . '
                ORDER BY left_id DESC';
            $result = $db->sql_query_limit($sql, 1);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);

            if (!$row)
            {
                break;
            }

            $module_id = (int) $row['module_id'];
            $left_id = (int) $row['left_id'];
            $right_id = (int) $row['right_id'];

            if ($left_id <= 0 || $right_id < $left_id)
            {
                $db->sql_query('DELETE FROM ' . $modules_table . ' WHERE module_id = ' . $module_id);
                continue;
            }

            $width = $right_id - $left_id + 1;

            $db->sql_transaction('begin');
            $db->sql_query('DELETE FROM ' . $modules_table . '
                WHERE module_class = \'acp\'
                    AND left_id BETWEEN ' . $left_id . ' AND ' . $right_id);
            $db->sql_query('UPDATE ' . $modules_table . '
                SET right_id = right_id - ' . $width . '
                WHERE module_class = \'acp\'
                    AND right_id > ' . $right_id);
            $db->sql_query('UPDATE ' . $modules_table . '
                SET left_id = left_id - ' . $width . '
                WHERE module_class = \'acp\'
                    AND left_id > ' . $right_id);
            $db->sql_transaction('commit');
        }
    }

    protected function clear_module_cache()
    {
        $cache = $this->get_container_service('cache.driver');
        if (!$cache)
        {
            return;
        }

        foreach (['_modules_acp', 'modules_acp'] as $cache_key)
        {
            try
            {
                if (method_exists($cache, 'destroy'))
                {
                    $cache->destroy($cache_key);
                }
            }
            catch (\Throwable $e)
            {
                // Ignore cache cleanup failures.
            }
        }

        try
        {
            if (method_exists($cache, 'purge'))
            {
                $cache->purge();
            }
        }
        catch (\Throwable $e)
        {
            // Ignore cache cleanup failures.
        }
    }

    protected function get_container_service($service_name)
    {
        try
        {
            if (method_exists($this->container, 'has') && !$this->container->has($service_name))
            {
                return null;
            }

            return $this->container->get($service_name);
        }
        catch (\Throwable $e)
        {
            return null;
        }
    }

    protected function get_modules_table()
    {
        if (defined('MODULES_TABLE'))
        {
            return MODULES_TABLE;
        }

        try
        {
            if (method_exists($this->container, 'hasParameter') && $this->container->hasParameter('tables.modules'))
            {
                return (string) $this->container->getParameter('tables.modules');
            }

            if (method_exists($this->container, 'hasParameter') && $this->container->hasParameter('core.table_prefix'))
            {
                return (string) $this->container->getParameter('core.table_prefix') . 'modules';
            }
        }
        catch (\Throwable $e)
        {
            // Fall through to safe default.
        }

        return 'phpbb_modules';
    }
}
