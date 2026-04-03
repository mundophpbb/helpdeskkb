<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    public function main($id, $mode)
    {
        global $config, $request, $template, $user, $phpbb_container, $phpbb_root_path, $phpEx;

        $helper = $phpbb_container->get('controller.helper');

        $user->add_lang_ext('mundophpbb/helpdeskkb', 'common');
        $user->add_lang_ext('mundophpbb/helpdeskkb', 'acp');

        if (!function_exists('generate_text_for_storage'))
        {
            include_once $phpbb_root_path . 'includes/functions_content.' . $phpEx;
        }

        /** @var \mundophpbb\helpdeskkb\service\kb_manager $manager */
        $manager = $phpbb_container->get('mundophpbb.helpdeskkb.service.manager');

        $mode = (string) $mode;
        if (!in_array($mode, ['settings', 'stats', 'categories', 'category_new', 'articles', 'article_new'], true))
        {
            $mode = 'settings';
        }

        $this->tpl_name = 'acp_helpdeskkb_body';
        $this->page_title = $user->lang('ACP_HELPDESKKB_TITLE');
        if ($mode === 'stats')
        {
            $this->page_title = $user->lang('ACP_HELPDESKKB_TAB_STATS');
        }
        else if ($mode === 'categories')
        {
            $this->page_title = $user->lang('ACP_HELPDESKKB_TAB_CATEGORIES');
        }
        else if ($mode === 'category_new')
        {
            $this->page_title = ((int) $request->variable('category_id', 0) > 0 || $request->is_set('edit_category')) ? $user->lang('ACP_HELPDESKKB_EDIT_CATEGORY') : $user->lang('ACP_HELPDESKKB_TAB_NEW_CATEGORY');
        }
        else if ($mode === 'articles')
        {
            $this->page_title = $user->lang('ACP_HELPDESKKB_TAB_ARTICLES');
        }
        else if ($mode === 'article_new')
        {
            $this->page_title = ((int) $request->variable('article_id', 0) > 0 || $request->is_set('edit_article')) ? $user->lang('ACP_HELPDESKKB_EDIT_ARTICLE') : $user->lang('ACP_HELPDESKKB_TAB_NEW_ARTICLE');
        }

        add_form_key('mundophpbb_helpdeskkb_acp');

        $message = '';
        $error = '';
        $edit_category = [];
        $edit_article = [];
        $category_form = $this->category_form_from_source();
        $article_form = $this->article_form_from_source();
        $article_preview_html = '';
        $article_filters = [
            'keywords' => trim((string) $request->variable('article_filter_keywords', '', true)),
            'category_id' => (int) $request->variable('article_filter_category_id', 0),
            'forum_id' => (int) $request->variable('article_filter_forum_id', 0),
            'department_key' => trim((string) $request->variable('article_filter_department_key', '', true)),
            'enabled' => trim((string) $request->variable('article_filter_enabled', '', true)),
        ];
        if (!in_array($article_filters['enabled'], ['', '1', '0'], true))
        {
            $article_filters['enabled'] = '';
        }

        if ($mode === 'settings' && $request->is_set_post('submit_settings'))
        {
            if (!check_form_key('mundophpbb_helpdeskkb_acp'))
            {
                $error = $user->lang('FORM_INVALID');
            }
            else
            {
                $mode_value = (string) $request->variable('helpdeskkb_mode', 'integrated', true);
                if (!in_array($mode_value, ['standalone', 'integrated'], true))
                {
                    $mode_value = 'integrated';
                }

                $config->set('helpdeskkb_enabled', $request->variable('helpdeskkb_enabled', 0));
                $config->set('helpdeskkb_navbar', $request->variable('helpdeskkb_navbar', 0));
                $config->set('helpdeskkb_mode', $mode_value);
                $config->set('helpdeskkb_suggestions_enabled', $request->variable('helpdeskkb_suggestions_enabled', 0));
                $config->set('helpdeskkb_suggestions_limit', max(1, min(10, $request->variable('helpdeskkb_suggestions_limit', 3))));
                $message = $user->lang('ACP_HELPDESKKB_SETTINGS_SAVED');
            }
        }

        if ($mode === 'categories' && $request->is_set('move_category'))
        {
            $direction = (string) $request->variable('direction', '', true);
            if (in_array($direction, ['up', 'down'], true))
            {
                $manager->move_category((int) $request->variable('category_id', 0), $direction);
                $message = $user->lang('ACP_HELPDESKKB_CATEGORY_ORDER_SAVED');
            }
        }

        if ($mode === 'category_new' && $request->is_set('edit_category'))
        {
            $edit_category = $manager->fetch_category_admin((int) $request->variable('category_id', 0));
            if (!empty($edit_category))
            {
                $category_form = $this->category_form_from_source($edit_category);
            }
        }

        if ($mode === 'category_new' && $request->is_set_post('save_category'))
        {
            $category_form = $this->category_form_from_source([
                'category_id' => (int) $request->variable('category_id', 0),
                'category_name' => trim((string) $request->variable('category_name', '', true)),
                'category_desc' => (string) $request->variable('category_desc', '', true),
                'forum_id' => (int) $request->variable('category_forum_id', 0),
                'department_key' => trim((string) $request->variable('category_department_key', '', true)),
                'sort_order' => (int) $request->variable('category_sort_order', 0),
                'category_enabled' => (int) $request->variable('category_enabled', 0),
            ]);

            if (!check_form_key('mundophpbb_helpdeskkb_acp'))
            {
                $error = $user->lang('FORM_INVALID');
            }
            else if ($category_form['category_name'] === '')
            {
                $error = $user->lang('ACP_HELPDESKKB_ERROR_CATEGORY_NAME');
            }
            else
            {
                $category_id = $manager->save_category([
                    'category_name' => $category_form['category_name'],
                    'category_desc' => $category_form['category_desc'],
                    'forum_id' => $category_form['forum_id'],
                    'department_key' => $category_form['department_key'],
                    'sort_order' => $category_form['sort_order'],
                    'category_enabled' => $category_form['category_enabled'] ? 1 : 0,
                ], (int) $category_form['category_id']);

                $edit_category = $manager->fetch_category_admin($category_id);
                $category_form = $this->category_form_from_source($edit_category);
                $message = $user->lang('ACP_HELPDESKKB_CATEGORY_SAVED');
            }
        }

        if ($mode === 'category_new' && $request->is_set_post('delete_category'))
        {
            if (!check_form_key('mundophpbb_helpdeskkb_acp'))
            {
                $error = $user->lang('FORM_INVALID');
            }
            else
            {
                $manager->delete_category((int) $request->variable('category_id', 0));
                $category_form = $this->category_form_from_source();
                $edit_category = [];
                $message = $user->lang('ACP_HELPDESKKB_CATEGORY_DELETED');
            }
        }

        if ($mode === 'articles' && $request->is_set('move_article'))
        {
            $direction = (string) $request->variable('direction', '', true);
            if (in_array($direction, ['up', 'down'], true))
            {
                $manager->move_article((int) $request->variable('article_id', 0), $direction);
                $message = $user->lang('ACP_HELPDESKKB_ARTICLE_ORDER_SAVED');
            }
        }

        if ($mode === 'article_new' && $request->is_set('edit_article'))
        {
            $edit_article = $manager->fetch_article_admin((int) $request->variable('article_id', 0));
            if (!empty($edit_article))
            {
                $article_form = $this->article_form_from_source($edit_article);
                $article_preview_html = $manager->parse_for_display($edit_article);
            }
        }

        if ($mode === 'article_new' && $request->is_set_post('save_article'))
        {
            $article_form = $this->article_form_from_source([
                'article_id' => (int) $request->variable('article_id', 0),
                'category_id' => (int) $request->variable('article_category_id', 0),
                'article_title' => trim((string) $request->variable('article_title', '', true)),
                'article_slug' => trim((string) $request->variable('article_slug', '', true)),
                'article_summary' => trim((string) $request->variable('article_summary', '', true)),
                'article_text' => (string) $request->variable('article_text', '', true),
                'article_keywords' => trim((string) $request->variable('article_keywords', '', true)),
                'forum_id' => (int) $request->variable('article_forum_id', 0),
                'department_key' => trim((string) $request->variable('article_department_key', '', true)),
                'sort_order' => (int) $request->variable('article_sort_order', 0),
                'article_enabled' => (int) $request->variable('article_enabled', 0),
            ]);

            if (!check_form_key('mundophpbb_helpdeskkb_acp'))
            {
                $error = $user->lang('FORM_INVALID');
            }
            else if ($article_form['article_title'] === '' || $article_form['article_text'] === '' || $article_form['category_id'] <= 0)
            {
                $error = $user->lang('ACP_HELPDESKKB_ERROR_ARTICLE_REQUIRED');
            }
            else
            {
                $article_form['article_summary'] = $this->sanitize_summary_text($article_form['article_summary']);
                $article_form['article_keywords'] = $this->normalize_keyword_input($article_form['article_keywords']);

                if ($article_form['article_summary'] === '')
                {
                    $article_form['article_summary'] = $this->build_summary_from_source_text($article_form['article_text']);
                }

                $uid = '';
                $bitfield = '';
                $options = 7;
                $allow_bbcode = true;
                $allow_urls = true;
                $allow_smilies = true;
                $article_text_storage = $article_form['article_text'];
                generate_text_for_storage($article_text_storage, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);

                $article_id = $manager->save_article([
                    'category_id' => $article_form['category_id'],
                    'article_title' => $article_form['article_title'],
                    'article_slug' => $article_form['article_slug'],
                    'article_summary' => $article_form['article_summary'],
                    'article_text' => $article_text_storage,
                    'article_bbcode_uid' => $uid,
                    'article_bbcode_bitfield' => $bitfield,
                    'article_bbcode_options' => $options,
                    'article_keywords' => $article_form['article_keywords'],
                    'forum_id' => $article_form['forum_id'],
                    'department_key' => $article_form['department_key'],
                    'sort_order' => $article_form['sort_order'],
                    'article_enabled' => $article_form['article_enabled'] ? 1 : 0,
                ], (int) $article_form['article_id']);

                $edit_article = $manager->fetch_article_admin($article_id);
                $article_form = $this->article_form_from_source($edit_article);
                $article_preview_html = $manager->parse_for_display($edit_article);
                $message = $user->lang('ACP_HELPDESKKB_ARTICLE_SAVED');
            }
        }

        if ($mode === 'article_new' && $request->is_set_post('delete_article'))
        {
            if (!check_form_key('mundophpbb_helpdeskkb_acp'))
            {
                $error = $user->lang('FORM_INVALID');
            }
            else
            {
                $manager->delete_article((int) $request->variable('article_id', 0));
                $article_form = $this->article_form_from_source();
                $edit_article = [];
                $article_preview_html = '';
                $message = $user->lang('ACP_HELPDESKKB_ARTICLE_DELETED');
            }
        }

        if ($mode === 'article_new' && $request->is_set_post('save_article') && $article_preview_html === '' && $article_form['article_text'] !== '')
        {
            $uid = '';
            $bitfield = '';
            $options = 7;
            $allow_bbcode = true;
            $allow_urls = true;
            $allow_smilies = true;
            $article_text_preview = $article_form['article_text'];
            generate_text_for_storage($article_text_preview, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);
            $article_preview_html = $manager->parse_for_display([
                'article_text' => $article_text_preview,
                'article_bbcode_uid' => $uid,
                'article_bbcode_bitfield' => $bitfield,
                'article_bbcode_options' => $options,
            ]);
        }

        $categories = $manager->fetch_all_categories_admin();
        $articles = $manager->fetch_all_articles_admin($article_filters);
        $article_order_rows = $manager->fetch_all_articles_admin();
        $article_order_state = [];
        $article_groups = [];
        foreach ($article_order_rows as $order_row)
        {
            $group_key = (int) ($order_row['category_id'] ?? 0);
            if (!isset($article_groups[$group_key]))
            {
                $article_groups[$group_key] = [];
            }
            $article_groups[$group_key][] = (int) $order_row['article_id'];
        }
        foreach ($article_groups as $group_ids)
        {
            $last_index = count($group_ids) - 1;
            foreach ($group_ids as $position => $group_article_id)
            {
                $article_order_state[$group_article_id] = [
                    'first' => ($position === 0),
                    'last' => ($position === $last_index),
                ];
            }
        }
        $article_filter_query = $this->build_article_filter_query($article_filters);

        $category_page = max(1, (int) $request->variable('category_page', 1));
        $category_per_page = 20;
        $category_total_results = count($categories);
        $category_total_pages = max(1, (int) ceil($category_total_results / $category_per_page));
        if ($category_page > $category_total_pages)
        {
            $category_page = $category_total_pages;
        }
        $category_offset = max(0, ($category_page - 1) * $category_per_page);
        $category_rows = array_slice($categories, $category_offset, $category_per_page);
        $category_results_from = ($category_total_results > 0) ? ($category_offset + 1) : 0;
        $category_results_to = ($category_total_results > 0) ? min($category_offset + count($category_rows), $category_total_results) : 0;
        $category_page_rows = $this->build_pagination_rows($this->append_query_params($this->mode_url('categories'), []), 'category_page', $category_page, $category_total_pages);

        $article_page = max(1, (int) $request->variable('article_page', 1));
        $article_per_page = 20;
        $article_total_results = count($articles);
        $article_total_pages = max(1, (int) ceil($article_total_results / $article_per_page));
        if ($article_page > $article_total_pages)
        {
            $article_page = $article_total_pages;
        }
        $article_offset = max(0, ($article_page - 1) * $article_per_page);
        $article_rows = array_slice($articles, $article_offset, $article_per_page);
        $article_results_from = ($article_total_results > 0) ? ($article_offset + 1) : 0;
        $article_results_to = ($article_total_results > 0) ? min($article_offset + count($article_rows), $article_total_results) : 0;
        $article_page_rows = $this->build_pagination_rows(
            $this->append_query_params($this->mode_url('articles'), $this->build_article_filter_params($article_filters)),
            'article_page',
            $article_page,
            $article_total_pages
        );


        $stats_rows = $article_order_rows;
        $stats_summary = [
            'total_categories' => count($categories),
            'enabled_categories' => 0,
            'total_articles' => count($stats_rows),
            'enabled_articles' => 0,
            'total_views' => 0,
            'helpful_yes' => 0,
            'helpful_no' => 0,
            'helpful_score' => 0,
            'average_views' => 0,
        ];
        $stats_category_views = [];
        foreach ($categories as $stats_category_row)
        {
            if (!empty($stats_category_row['category_enabled']))
            {
                $stats_summary['enabled_categories']++;
            }
            $stats_category_views[(int) $stats_category_row['category_id']] = 0;
        }

        foreach ($stats_rows as $stats_article_row)
        {
            if (!empty($stats_article_row['article_enabled']))
            {
                $stats_summary['enabled_articles']++;
            }

            $stats_summary['total_views'] += (int) ($stats_article_row['article_views'] ?? 0);
            $stats_summary['helpful_yes'] += (int) ($stats_article_row['article_helpful_yes'] ?? 0);
            $stats_summary['helpful_no'] += (int) ($stats_article_row['article_helpful_no'] ?? 0);

            $stats_category_id = (int) ($stats_article_row['category_id'] ?? 0);
            if (!isset($stats_category_views[$stats_category_id]))
            {
                $stats_category_views[$stats_category_id] = 0;
            }
            $stats_category_views[$stats_category_id] += (int) ($stats_article_row['article_views'] ?? 0);
        }
        $stats_summary['helpful_score'] = $stats_summary['helpful_yes'] - $stats_summary['helpful_no'];
        $stats_summary['average_views'] = ($stats_summary['total_articles'] > 0) ? (int) round($stats_summary['total_views'] / $stats_summary['total_articles']) : 0;

        $stats_top_viewed = $stats_rows;
        usort($stats_top_viewed, function ($left, $right) {
            $left_views = (int) ($left['article_views'] ?? 0);
            $right_views = (int) ($right['article_views'] ?? 0);
            if ($left_views !== $right_views)
            {
                return ($right_views <=> $left_views);
            }
            return strcasecmp((string) ($left['article_title'] ?? ''), (string) ($right['article_title'] ?? ''));
        });
        $stats_top_viewed = array_slice($stats_top_viewed, 0, 5);

        $stats_top_helpful = $stats_rows;
        usort($stats_top_helpful, function ($left, $right) {
            $left_score = (int) ($left['article_helpful_yes'] ?? 0) - (int) ($left['article_helpful_no'] ?? 0);
            $right_score = (int) ($right['article_helpful_yes'] ?? 0) - (int) ($right['article_helpful_no'] ?? 0);
            if ($left_score !== $right_score)
            {
                return ($right_score <=> $left_score);
            }
            $left_yes = (int) ($left['article_helpful_yes'] ?? 0);
            $right_yes = (int) ($right['article_helpful_yes'] ?? 0);
            if ($left_yes !== $right_yes)
            {
                return ($right_yes <=> $left_yes);
            }
            return strcasecmp((string) ($left['article_title'] ?? ''), (string) ($right['article_title'] ?? ''));
        });
        $stats_top_helpful = array_slice($stats_top_helpful, 0, 5);

        $stats_categories = [];
        foreach ($categories as $stats_category_row)
        {
            $stats_categories[] = [
                'category_id' => (int) ($stats_category_row['category_id'] ?? 0),
                'category_name' => (string) ($stats_category_row['category_name'] ?? ''),
                'article_count' => (int) ($stats_category_row['article_count'] ?? 0),
                'category_views' => (int) ($stats_category_views[(int) ($stats_category_row['category_id'] ?? 0)] ?? 0),
                'category_enabled' => !empty($stats_category_row['category_enabled']),
            ];
        }
        usort($stats_categories, function ($left, $right) {
            if ((int) $left['article_count'] !== (int) $right['article_count'])
            {
                return ((int) $right['article_count'] <=> (int) $left['article_count']);
            }
            if ((int) $left['category_views'] !== (int) $right['category_views'])
            {
                return ((int) $right['category_views'] <=> (int) $left['category_views']);
            }
            return strcasecmp((string) $left['category_name'], (string) $right['category_name']);
        });
        $stats_categories = array_slice($stats_categories, 0, 5);

        $category_total = count($category_rows);

        foreach ($stats_top_viewed as $stats_row)
        {
            $template->assign_block_vars('stats_top_viewed', [
                'ARTICLE_TITLE' => (string) ($stats_row['article_title'] ?? ''),
                'CATEGORY_NAME' => (string) ($stats_row['category_name'] ?? ''),
                'ARTICLE_VIEWS' => (int) ($stats_row['article_views'] ?? 0),
            ]);
        }

        foreach ($stats_top_helpful as $stats_row)
        {
            $template->assign_block_vars('stats_top_helpful', [
                'ARTICLE_TITLE' => (string) ($stats_row['article_title'] ?? ''),
                'CATEGORY_NAME' => (string) ($stats_row['category_name'] ?? ''),
                'HELPFUL_SCORE' => ((int) ($stats_row['article_helpful_yes'] ?? 0) - (int) ($stats_row['article_helpful_no'] ?? 0)),
                'HELPFUL_YES' => (int) ($stats_row['article_helpful_yes'] ?? 0),
            ]);
        }

        foreach ($stats_categories as $stats_row)
        {
            $template->assign_block_vars('stats_categories', [
                'CATEGORY_NAME' => (string) ($stats_row['category_name'] ?? ''),
                'ARTICLE_COUNT' => (int) ($stats_row['article_count'] ?? 0),
                'CATEGORY_VIEWS' => (int) ($stats_row['category_views'] ?? 0),
                'CATEGORY_ENABLED' => !empty($stats_row['category_enabled']) ? $user->lang('YES') : $user->lang('NO'),
            ]);
        }

        foreach ($category_rows as $category_index => $row)
        {
            $template->assign_block_vars('category_rows', [
                'CATEGORY_ID' => (int) $row['category_id'],
                'CATEGORY_NAME' => (string) $row['category_name'],
                'CATEGORY_DESC' => (string) $row['category_desc'],
                'FORUM_ID' => (int) $row['forum_id'],
                'DEPARTMENT_KEY' => (string) $row['department_key'],
                'SORT_ORDER' => (int) $row['sort_order'],
                'ARTICLE_COUNT' => (int) ($row['article_count'] ?? 0),
                'CATEGORY_ENABLED' => !empty($row['category_enabled']) ? $user->lang('YES') : $user->lang('NO'),
                'TARGET_LABEL' => $this->build_target_label((int) ($row['forum_id'] ?? 0), (string) ($row['department_key'] ?? ''), $user),
                'U_EDIT' => $this->append_query_params($this->mode_url('category_new'), ['edit_category' => 1, 'category_id' => (int) $row['category_id']]),
                'U_VIEW_ARTICLES' => $this->append_query_params($this->mode_url('articles'), ['article_filter_category_id' => (int) $row['category_id']]),
                'U_VIEW_PUBLIC' => $helper->route('mundophpbb_helpdeskkb_category_slug_controller', [
                    'category_id' => (int) $row['category_id'],
                    'slug' => $manager->build_category_slug((string) ($row['category_name'] ?? '')),
                ]),
                'U_MOVE_UP' => $this->append_query_params($this->mode_url('categories'), ['move_category' => 1, 'direction' => 'up', 'category_id' => (int) $row['category_id'], 'category_page' => $category_page]),
                'U_MOVE_DOWN' => $this->append_query_params($this->mode_url('categories'), ['move_category' => 1, 'direction' => 'down', 'category_id' => (int) $row['category_id'], 'category_page' => $category_page]),
                'S_FIRST_ROW' => ($category_index === 0),
                'S_LAST_ROW' => ($category_index === ($category_total - 1)),
            ]);

            $template->assign_block_vars('article_category_options', [
                'VALUE' => (int) $row['category_id'],
                'LABEL' => (string) $row['category_name'],
                'S_SELECTED' => ((int) $article_form['category_id'] === (int) $row['category_id']),
            ]);

            $template->assign_block_vars('article_filter_category_options', [
                'VALUE' => (int) $row['category_id'],
                'LABEL' => (string) $row['category_name'],
                'S_SELECTED' => ((int) $article_filters['category_id'] === (int) $row['category_id']),
            ]);
        }

        foreach ($article_rows as $row)
        {
            $effective_forum_id = (int) ($row['forum_id'] ?: ($row['category_forum_id'] ?? 0));
            $effective_department_key = (string) ($row['department_key'] !== '' ? $row['department_key'] : ($row['category_department_key'] ?? ''));
            $article_id = (int) $row['article_id'];
            $order_state = $article_order_state[$article_id] ?? ['first' => true, 'last' => true];

            $template->assign_block_vars('article_rows', [
                'ARTICLE_ID' => $article_id,
                'ARTICLE_TITLE' => (string) $row['article_title'],
                'CATEGORY_NAME' => (string) ($row['category_name'] ?? ''),
                'FORUM_ID' => (int) $row['forum_id'],
                'DEPARTMENT_KEY' => (string) $row['department_key'],
                'TARGET_LABEL' => $this->build_target_label($effective_forum_id, $effective_department_key, $user),
                'SORT_ORDER' => (int) $row['sort_order'],
                'ARTICLE_VIEWS' => (int) $row['article_views'],
                'ARTICLE_HELPFUL' => (int) $row['article_helpful_yes'] . ' / ' . (int) $row['article_helpful_no'],
                'ARTICLE_ENABLED' => !empty($row['article_enabled']) ? $user->lang('YES') : $user->lang('NO'),
                'U_EDIT' => $this->append_query_params($this->mode_url('article_new'), ['edit_article' => 1, 'article_id' => $article_id]),
                'U_MOVE_UP' => $this->append_query_params($this->mode_url('articles'), array_merge($this->build_article_filter_params($article_filters), ['move_article' => 1, 'direction' => 'up', 'article_id' => $article_id, 'article_page' => $article_page])),
                'U_MOVE_DOWN' => $this->append_query_params($this->mode_url('articles'), array_merge($this->build_article_filter_params($article_filters), ['move_article' => 1, 'direction' => 'down', 'article_id' => $article_id, 'article_page' => $article_page])),
                'S_FIRST_ROW' => !empty($order_state['first']),
                'S_LAST_ROW' => !empty($order_state['last']),
                'U_VIEW' => !empty($row['article_enabled']) ? $helper->route('mundophpbb_helpdeskkb_article_slug_controller', [
                    'article_id' => $article_id,
                    'slug' => (string) ($row['article_slug'] ?? ''),
                ]) : '',
                'S_CAN_VIEW' => !empty($row['article_enabled']),
            ]);
        }


        foreach ($category_page_rows as $page_row)
        {
            $template->assign_block_vars('category_page_rows', $page_row);
        }

        foreach ($article_page_rows as $page_row)
        {
            $template->assign_block_vars('article_page_rows', $page_row);
        }

        $template->assign_vars([
            'U_ACTION' => $this->u_action,
            'S_TAB_SETTINGS' => ($mode === 'settings'),
            'S_TAB_STATS' => ($mode === 'stats'),
            'S_TAB_CATEGORIES' => ($mode === 'categories'),
            'S_TAB_CATEGORY_NEW' => ($mode === 'category_new'),
            'S_TAB_ARTICLES' => ($mode === 'articles'),
            'S_TAB_ARTICLE_NEW' => ($mode === 'article_new'),
            'MESSAGE_TEXT' => $message,
            'ERROR_TEXT' => $error,
            'S_HAS_MESSAGE' => ($message !== ''),
            'S_HAS_ERROR' => ($error !== ''),
            'HELPDESKKB_ENABLED' => !empty($config['helpdeskkb_enabled']),
            'HELPDESKKB_NAVBAR' => !empty($config['helpdeskkb_navbar']),
            'HELPDESKKB_MODE' => isset($config['helpdeskkb_mode']) ? (string) $config['helpdeskkb_mode'] : 'integrated',
            'S_HELPDESKKB_MODE_STANDALONE' => ((isset($config['helpdeskkb_mode']) ? (string) $config['helpdeskkb_mode'] : 'integrated') === 'standalone'),
            'S_HELPDESKKB_MODE_INTEGRATED' => ((isset($config['helpdeskkb_mode']) ? (string) $config['helpdeskkb_mode'] : 'integrated') === 'integrated'),
            'HELPDESKKB_SUGGESTIONS_ENABLED' => !empty($config['helpdeskkb_suggestions_enabled']),
            'HELPDESKKB_SUGGESTIONS_LIMIT' => (int) $config['helpdeskkb_suggestions_limit'],
            'HELPDESKKB_STATS_TOTAL_CATEGORIES' => (int) $stats_summary['total_categories'],
            'HELPDESKKB_STATS_ENABLED_CATEGORIES' => (int) $stats_summary['enabled_categories'],
            'HELPDESKKB_STATS_TOTAL_ARTICLES' => (int) $stats_summary['total_articles'],
            'HELPDESKKB_STATS_ENABLED_ARTICLES' => (int) $stats_summary['enabled_articles'],
            'HELPDESKKB_STATS_TOTAL_VIEWS' => (int) $stats_summary['total_views'],
            'HELPDESKKB_STATS_HELPFUL_YES' => (int) $stats_summary['helpful_yes'],
            'HELPDESKKB_STATS_HELPFUL_NO' => (int) $stats_summary['helpful_no'],
            'HELPDESKKB_STATS_HELPFUL_SCORE' => (int) $stats_summary['helpful_score'],
            'HELPDESKKB_STATS_AVERAGE_VIEWS' => (int) $stats_summary['average_views'],
            'CATEGORY_ID' => (int) $category_form['category_id'],
            'CATEGORY_NAME' => (string) $category_form['category_name'],
            'CATEGORY_DESC' => (string) $category_form['category_desc'],
            'CATEGORY_FORUM_ID' => (int) $category_form['forum_id'],
            'CATEGORY_DEPARTMENT_KEY' => (string) $category_form['department_key'],
            'CATEGORY_SORT_ORDER' => (int) $category_form['sort_order'],
            'CATEGORY_ENABLED_BOOL' => !empty($category_form['category_enabled']),
            'CATEGORY_PUBLIC_SLUG' => $manager->build_category_slug((string) $category_form['category_name']),
            'U_CATEGORY_PUBLIC' => ((int) $category_form['category_id'] > 0) ? $helper->route('mundophpbb_helpdeskkb_category_slug_controller', [
                'category_id' => (int) $category_form['category_id'],
                'slug' => $manager->build_category_slug((string) $category_form['category_name']),
            ]) : '',
            'ARTICLE_ID' => (int) $article_form['article_id'],
            'ARTICLE_TITLE' => (string) $article_form['article_title'],
            'ARTICLE_SLUG' => (string) $article_form['article_slug'],
            'ARTICLE_SUMMARY' => (string) $article_form['article_summary'],
            'ARTICLE_TEXT' => (string) $article_form['article_text'],
            'ARTICLE_KEYWORDS' => (string) $article_form['article_keywords'],
            'ARTICLE_FORUM_ID' => (int) $article_form['forum_id'],
            'ARTICLE_DEPARTMENT_KEY' => (string) $article_form['department_key'],
            'ARTICLE_SORT_ORDER' => (int) $article_form['sort_order'],
            'ARTICLE_ENABLED_BOOL' => !empty($article_form['article_enabled']),
            'ARTICLE_FILTER_KEYWORDS' => (string) $article_filters['keywords'],
            'ARTICLE_FILTER_CATEGORY_ID' => (int) $article_filters['category_id'],
            'ARTICLE_FILTER_FORUM_ID' => (int) $article_filters['forum_id'],
            'ARTICLE_FILTER_DEPARTMENT_KEY' => (string) $article_filters['department_key'],
            'ARTICLE_FILTER_ENABLED' => (string) $article_filters['enabled'],
            'ARTICLE_FILTER_RESULTS' => $article_total_results,
            'CATEGORY_RESULTS_FROM' => $category_results_from,
            'CATEGORY_RESULTS_TO' => $category_results_to,
            'CATEGORY_RESULTS_TOTAL' => $category_total_results,
            'S_HAS_CATEGORIES' => !empty($category_rows),
            'S_HAS_CATEGORY_PAGINATION' => ($category_total_pages > 1),
            'S_HAS_CATEGORY_PREV_PAGE' => ($category_page > 1),
            'S_HAS_CATEGORY_NEXT_PAGE' => ($category_page < $category_total_pages),
            'U_CATEGORY_PAGE_PREV' => ($category_page > 1) ? $this->append_query_params($this->mode_url('categories'), ['category_page' => $category_page - 1]) : '',
            'U_CATEGORY_PAGE_NEXT' => ($category_page < $category_total_pages) ? $this->append_query_params($this->mode_url('categories'), ['category_page' => $category_page + 1]) : '',
            'ARTICLE_RESULTS_FROM' => $article_results_from,
            'ARTICLE_RESULTS_TO' => $article_results_to,
            'ARTICLE_RESULTS_TOTAL' => $article_total_results,
            'S_HAS_ARTICLES' => !empty($article_rows),
            'S_HAS_ARTICLE_PAGINATION' => ($article_total_pages > 1),
            'S_HAS_ARTICLE_PREV_PAGE' => ($article_page > 1),
            'S_HAS_ARTICLE_NEXT_PAGE' => ($article_page < $article_total_pages),
            'U_ARTICLE_PAGE_PREV' => ($article_page > 1) ? $this->append_query_params($this->mode_url('articles'), array_merge($this->build_article_filter_params($article_filters), ['article_page' => $article_page - 1])) : '',
            'U_ARTICLE_PAGE_NEXT' => ($article_page < $article_total_pages) ? $this->append_query_params($this->mode_url('articles'), array_merge($this->build_article_filter_params($article_filters), ['article_page' => $article_page + 1])) : '',
            'S_HAS_ACTIVE_ARTICLE_FILTERS' => ($article_filters['keywords'] !== '' || $article_filters['category_id'] > 0 || $article_filters['forum_id'] > 0 || $article_filters['department_key'] !== '' || $article_filters['enabled'] !== ''),
            'S_EDIT_CATEGORY' => ((int) $category_form['category_id'] > 0),
            'S_EDIT_ARTICLE' => ((int) $article_form['article_id'] > 0),
            'CATEGORY_FORM_TITLE' => ((int) $category_form['category_id'] > 0) ? $user->lang('ACP_HELPDESKKB_EDIT_CATEGORY') : $user->lang('ACP_HELPDESKKB_TAB_NEW_CATEGORY'),
            'ARTICLE_FORM_TITLE' => ((int) $article_form['article_id'] > 0) ? $user->lang('ACP_HELPDESKKB_EDIT_ARTICLE') : $user->lang('ACP_HELPDESKKB_TAB_NEW_ARTICLE'),
            'S_ARTICLE_PREVIEW' => ($article_preview_html !== ''),
            'ARTICLE_PREVIEW_HTML' => $article_preview_html,
            'ARTICLE_TARGET_SUMMARY' => $this->build_target_label((int) $article_form['forum_id'], (string) $article_form['department_key'], $user, true),
            'U_STATS' => $this->mode_url('stats'),
            'U_CATEGORY_RESET' => $this->mode_url('category_new'),
            'U_CATEGORY_NEW' => $this->mode_url('category_new'),
            'U_CATEGORY_LIST' => $this->mode_url('categories'),
            'U_ARTICLE_RESET' => $this->mode_url('article_new'),
            'U_ARTICLE_NEW' => $this->mode_url('article_new'),
            'U_ARTICLE_LIST' => $this->mode_url('articles'),
        ]);
    }

    protected function mode_url($target_mode)
    {
        $target_mode = (string) $target_mode;

        if (preg_match('/([?&])mode=[^&]*/', $this->u_action))
        {
            return preg_replace('/([?&])mode=[^&]*/', '$1mode=' . $target_mode, $this->u_action, 1);
        }

        return $this->u_action . ((strpos($this->u_action, '?') !== false) ? '&' : '?') . 'mode=' . $target_mode;
    }


    protected function append_query_params($url, array $params)
    {
        $clean = [];
        foreach ($params as $key => $value)
        {
            if ($value === null || $value === '' || $value === 0 || $value === '0')
            {
                if (in_array((string) $value, ['0'], true) && in_array((string) $key, ['article_filter_enabled'], true))
                {
                    $clean[$key] = '0';
                }
                else
                {
                    continue;
                }
            }
            $clean[$key] = $value;
        }

        if (empty($clean))
        {
            return $url;
        }

        return $url . ((strpos($url, '?') !== false) ? '&' : '?') . http_build_query($clean, '', '&');
    }

    protected function build_pagination_rows($base_url, $page_param, $current_page, $total_pages)
    {
        $rows = [];
        if ($total_pages <= 1)
        {
            return $rows;
        }

        $start = max(1, $current_page - 2);
        $end = min($total_pages, $current_page + 2);
        if (($end - $start) < 4)
        {
            if ($start === 1)
            {
                $end = min($total_pages, $start + 4);
            }
            else if ($end === $total_pages)
            {
                $start = max(1, $end - 4);
            }
        }

        for ($page = $start; $page <= $end; $page++)
        {
            $rows[] = [
                'PAGE_NUMBER' => $page,
                'U_PAGE' => $this->append_query_params($base_url, [$page_param => $page]),
                'S_CURRENT' => ($page === $current_page),
            ];
        }

        return $rows;
    }

    protected function build_article_filter_params(array $filters)
    {
        $params = [];
        if (!empty($filters['keywords']))
        {
            $params['article_filter_keywords'] = (string) $filters['keywords'];
        }
        if (!empty($filters['category_id']))
        {
            $params['article_filter_category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['forum_id']))
        {
            $params['article_filter_forum_id'] = (int) $filters['forum_id'];
        }
        if (!empty($filters['department_key']))
        {
            $params['article_filter_department_key'] = (string) $filters['department_key'];
        }
        if (isset($filters['enabled']) && in_array((string) $filters['enabled'], ['0', '1'], true))
        {
            $params['article_filter_enabled'] = (string) $filters['enabled'];
        }

        return $params;
    }

    protected function category_form_from_source(array $source = [])
    {
        return [
            'category_id' => (int) ($source['category_id'] ?? 0),
            'category_name' => (string) ($source['category_name'] ?? ''),
            'category_desc' => (string) ($source['category_desc'] ?? ''),
            'forum_id' => (int) ($source['forum_id'] ?? 0),
            'department_key' => (string) ($source['department_key'] ?? ''),
            'sort_order' => (int) ($source['sort_order'] ?? 0),
            'category_enabled' => isset($source['category_enabled']) ? !empty($source['category_enabled']) : true,
        ];
    }

    protected function article_form_from_source(array $source = [])
    {
        $article_text = (string) ($source['article_text'] ?? '');

        if ($article_text !== '' && (isset($source['article_bbcode_uid']) || isset($source['article_bbcode_options'])))
        {
            $edit_data = generate_text_for_edit(
                $article_text,
                (string) ($source['article_bbcode_uid'] ?? ''),
                (int) ($source['article_bbcode_options'] ?? 7)
            );
            $article_text = (string) ($edit_data['text'] ?? $article_text);
        }

        return [
            'article_id' => (int) ($source['article_id'] ?? 0),
            'category_id' => (int) ($source['category_id'] ?? 0),
            'article_title' => (string) ($source['article_title'] ?? ''),
            'article_slug' => (string) ($source['article_slug'] ?? ''),
            'article_summary' => $this->sanitize_summary_text((string) ($source['article_summary'] ?? '')),
            'article_text' => $article_text,
            'article_keywords' => $this->normalize_keyword_input((string) ($source['article_keywords'] ?? '')),
            'forum_id' => (int) ($source['forum_id'] ?? 0),
            'department_key' => (string) ($source['department_key'] ?? ''),
            'sort_order' => (int) ($source['sort_order'] ?? 0),
            'article_enabled' => isset($source['article_enabled']) ? !empty($source['article_enabled']) : true,
        ];
    }

    protected function build_article_filter_query(array $filters)
    {
        $params = [];

        if (!empty($filters['keywords']))
        {
            $params['article_filter_keywords'] = (string) $filters['keywords'];
        }
        if (!empty($filters['category_id']))
        {
            $params['article_filter_category_id'] = (int) $filters['category_id'];
        }
        if (!empty($filters['forum_id']))
        {
            $params['article_filter_forum_id'] = (int) $filters['forum_id'];
        }
        if (!empty($filters['department_key']))
        {
            $params['article_filter_department_key'] = (string) $filters['department_key'];
        }
        if (isset($filters['enabled']) && in_array((string) $filters['enabled'], ['0', '1'], true))
        {
            $params['article_filter_enabled'] = (string) $filters['enabled'];
        }

        if (empty($params))
        {
            return '';
        }

        return '&' . http_build_query($params, '', '&');
    }

    protected function build_target_label($forum_id, $department_key, $user, $treat_empty_as_inherit = false)
    {
        $forum_id = (int) $forum_id;
        $department_key = trim((string) $department_key);

        if ($forum_id <= 0 && $department_key === '')
        {
            return $treat_empty_as_inherit ? $user->lang('ACP_HELPDESKKB_TARGET_INHERIT') : $user->lang('ACP_HELPDESKKB_TARGET_GLOBAL');
        }

        $parts = [];
        if ($forum_id > 0)
        {
            $parts[] = sprintf($user->lang('ACP_HELPDESKKB_TARGET_FORUM'), $forum_id);
        }
        if ($department_key !== '')
        {
            $parts[] = sprintf($user->lang('ACP_HELPDESKKB_TARGET_DEPARTMENT'), $department_key);
        }

        return implode(' · ', $parts);
    }

    protected function sanitize_summary_text($text)
    {
        $text = (string) $text;
        if ($text === '')
        {
            return '';
        }

        $text = preg_replace('/\[(?:\/?)[^\]]+\]/u', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', trim($text));

        return (string) $text;
    }

    protected function build_summary_from_source_text($text, $limit = 260)
    {
        $text = $this->sanitize_summary_text($text);
        $limit = max(80, (int) $limit);

        if ($text === '')
        {
            return '';
        }

        if (mb_strlen($text) <= $limit)
        {
            return $text;
        }

        $summary = trim((string) mb_substr($text, 0, $limit));
        $last_space = mb_strrpos($summary, ' ');
        if ($last_space !== false && $last_space > (int) ($limit * 0.6))
        {
            $summary = trim((string) mb_substr($summary, 0, $last_space));
        }

        return rtrim($summary, " 	

 .,;:-") . '…';
    }

    protected function normalize_keyword_input($keywords)
    {
        $keywords = (string) $keywords;
        if ($keywords === '')
        {
            return '';
        }

        $parts = preg_split('/[,;

	]+/u', $keywords);
        $normalized = [];
        $seen = [];

        foreach ((array) $parts as $part)
        {
            $part = trim((string) $part);
            if ($part === '')
            {
                continue;
            }

            $key = mb_strtolower($part);
            if (isset($seen[$key]))
            {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $part;
        }

        return implode(', ', $normalized);
    }

}
