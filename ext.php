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
        // Manual purge hotfix: bypass the phpBB migrator for this extension because
        // previously-installed menu migrations can fail to revert safely after many
        // ACP menu reorders. We remove only this extension's data and state.
        if ($old_state === false || $old_state === '')
        {
            $this->manual_purge_helpdeskkb();
        }

        return false;
    }

    protected function manual_purge_helpdeskkb()
    {
        $db = $this->get_container_service('dbal.conn');
        if (!$db)
        {
            return;
        }

        $modules_table = $this->get_table_name('modules');
        $config_table = $this->get_table_name('config');
        $migrations_table = $this->get_table_name('migrations');
        $categories_table = $this->get_table_name('helpdesk_kb_categories');
        $articles_table = $this->get_table_name('helpdesk_kb_articles');

        if ($modules_table !== '')
        {
            $this->raw_cleanup_module_subtrees(
                $db,
                $modules_table,
                "module_class = 'acp' AND module_basename = '" . $db->sql_escape(self::ACP_MODULE_BASENAME) . "'"
            );

            $this->raw_cleanup_module_subtrees(
                $db,
                $modules_table,
                "module_class = 'acp' AND module_langname = '" . $db->sql_escape(self::ACP_CATEGORY_LANG) . "'"
            );
        }

        if ($config_table !== '')
        {
            $db->sql_query('DELETE FROM ' . $config_table . "
                WHERE config_name LIKE '" . $db->sql_escape('helpdeskkb_') . "%'"
            );
        }

        if ($migrations_table !== '')
        {
            $migration_classes = [
                '\\mundophpbb\\helpdeskkb\\migrations\\v1000_install',
                '\\mundophpbb\\helpdeskkb\\migrations\\v1001_mode',
                '\\mundophpbb\\helpdeskkb\\migrations\\v1002_article_menu',
                '\\mundophpbb\\helpdeskkb\\migrations\\v1003_category_menu',
                '\\mundophpbb\\helpdeskkb\\migrations\\v1004_menu_order',
                '\\mundophpbb\\helpdeskkb\\migrations\\v1005_stats_menu',
                '\\mundophpbb\\helpdeskkb\\migrations\\v1006_menu_finish',
            ];

            $db->sql_query('DELETE FROM ' . $migrations_table . '
                WHERE ' . $db->sql_in_set('migration_name', array_map('strval', $migration_classes)));
        }

        foreach ([$articles_table, $categories_table] as $table_name)
        {
            if ($table_name === '')
            {
                continue;
            }

            try
            {
                $db->sql_query('DROP TABLE IF EXISTS ' . $table_name);
            }
            catch (\Throwable $e)
            {
                // Ignore drop failures; this is best-effort cleanup.
            }
        }

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

    protected function get_table_name($table_key)
    {
        $map = [
            'modules' => ['constant' => 'MODULES_TABLE', 'suffix' => 'modules', 'parameter' => 'tables.modules'],
            'config' => ['constant' => 'CONFIG_TABLE', 'suffix' => 'config', 'parameter' => 'tables.config'],
            'migrations' => ['constant' => null, 'suffix' => 'migrations', 'parameter' => 'tables.migrations'],
            'helpdesk_kb_categories' => ['constant' => null, 'suffix' => 'helpdesk_kb_categories', 'parameter' => null],
            'helpdesk_kb_articles' => ['constant' => null, 'suffix' => 'helpdesk_kb_articles', 'parameter' => null],
        ];

        if (!isset($map[$table_key]))
        {
            return '';
        }

        $meta = $map[$table_key];

        if (!empty($meta['constant']) && defined($meta['constant']))
        {
            return constant($meta['constant']);
        }

        try
        {
            if (!empty($meta['parameter']) && method_exists($this->container, 'hasParameter') && $this->container->hasParameter($meta['parameter']))
            {
                return (string) $this->container->getParameter($meta['parameter']);
            }

            if (method_exists($this->container, 'hasParameter') && $this->container->hasParameter('core.table_prefix'))
            {
                return (string) $this->container->getParameter('core.table_prefix') . $meta['suffix'];
            }
        }
        catch (\Throwable $e)
        {
            // Fall through to safe default.
        }

        return 'phpbb_' . $meta['suffix'];
    }
}
