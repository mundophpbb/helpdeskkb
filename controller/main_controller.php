<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class main_controller
{
    protected $config;
    protected $helper;
    protected $request;
    protected $template;
    protected $user;
    protected $auth;
    protected $manager;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\controller\helper $helper,
        \phpbb\request\request_interface $request,
        \phpbb\template\template $template,
        \phpbb\user $user,
        \phpbb\auth\auth $auth,
        \mundophpbb\helpdeskkb\service\kb_manager $manager
    ) {
        $this->config = $config;
        $this->helper = $helper;
        $this->request = $request;
        $this->template = $template;
        $this->user = $user;
        $this->auth = $auth;
        $this->manager = $manager;
    }

    public function index()
    {
        $this->user->add_lang_ext('mundophpbb/helpdeskkb', 'common');

        if (!$this->manager->extension_enabled())
        {
            trigger_error('NOT_AUTHORISED');
        }

        $query = trim((string) $this->request->variable('q', '', true));
        $selected_category_id = (int) $this->request->variable('category_id', 0);
        $search_sort = $this->normalize_search_sort((string) $this->request->variable('sort', 'relevance', true));
        $search_per_page = 12;
        $search_page = max(1, (int) $this->request->variable('page', 1));
        $categories = $this->manager->fetch_categories_with_counts();
        $selected_category = $this->find_category($categories, $selected_category_id);
        if (empty($selected_category))
        {
            $selected_category_id = 0;
        }

        $index_back_params = $this->build_index_back_params($query, $selected_category_id, $search_sort, $search_page);

        foreach ($categories as $category)
        {
            $this->template->assign_block_vars('kb_category_options', [
                'VALUE' => (int) $category['category_id'],
                'LABEL' => (string) $category['category_name'],
                'S_SELECTED' => ((int) $category['category_id'] === $selected_category_id),
            ]);
        }

        $search_active = ($query !== '' || $selected_category_id > 0);
        $query_active = ($query !== '');

        foreach ($this->build_active_filter_rows($query, $selected_category_id, $selected_category, $search_sort) as $filter_row)
        {
            $this->template->assign_block_vars('kb_active_filters', $filter_row);
        }

        $matched_articles = $search_active
            ? $this->manager->fetch_public_articles([
                'keywords' => $query,
                'category_id' => $selected_category_id,
            ])
            : [];

        if ($query_active)
        {
            $matched_articles = $this->sort_search_results($matched_articles, $search_sort);
        }

        $search_total_results = $query_active ? count($matched_articles) : 0;
        $search_total_pages = $query_active ? max(1, (int) ceil($search_total_results / $search_per_page)) : 1;
        if ($query_active && $search_page > $search_total_pages)
        {
            $search_page = $search_total_pages;
        }
        $search_offset = $query_active ? max(0, ($search_page - 1) * $search_per_page) : 0;
        $paged_matched_articles = $query_active ? array_slice($matched_articles, $search_offset, $search_per_page) : [];
        $search_results_from = ($search_total_results > 0) ? ($search_offset + 1) : 0;
        $search_results_to = ($search_total_results > 0) ? min($search_offset + count($paged_matched_articles), $search_total_results) : 0;
        $search_no_results = ($query_active && $search_total_results === 0);

        $popular_articles = (!$search_active || $search_no_results) ? $this->manager->fetch_popular_articles(6) : [];
        $helpful_articles = (!$search_active || $search_no_results) ? $this->manager->fetch_helpful_articles(6) : [];
        $recent_articles = (!$search_active || $search_no_results) ? $this->manager->fetch_recent_articles(6) : [];

        $matched_by_category = [];
        foreach ($matched_articles as $article)
        {
            $matched_by_category[(int) $article['category_id']][] = $article;
        }

        if (!$search_active)
        {
            $featured_categories = array_slice($categories, 0, 4);
            foreach ($featured_categories as $category)
            {
                $category_id = (int) $category['category_id'];
                $category_slug = $this->manager->build_category_slug((string) ($category['category_name'] ?? ''));
                $curated_articles = $this->curate_home_articles($this->manager->fetch_articles_by_category($category_id, true), 1);
                $featured_article = !empty($curated_articles) ? $curated_articles[0] : [];

                $this->template->assign_block_vars('kb_featured_categories', [
                    'CATEGORY_ID' => $category_id,
                    'CATEGORY_NAME' => (string) $category['category_name'],
                    'CATEGORY_DESC' => (string) $category['category_desc'],
                    'ARTICLE_COUNT' => (int) ($category['article_count'] ?? 0),
                    'CATEGORY_SCOPE_LABEL' => $this->manager->build_category_scope_label($category),
                    'U_CATEGORY' => $this->append_query($this->category_url($category_id, $category_slug), $index_back_params),
                    'S_HAS_FEATURED_ARTICLE' => !empty($featured_article),
                    'FEATURED_ARTICLE_TITLE' => (string) ($featured_article['article_title'] ?? ''),
                    'FEATURED_ARTICLE_LABEL' => !empty($featured_article) ? $this->home_article_badge($featured_article) : '',
                    'U_FEATURED_ARTICLE' => !empty($featured_article)
                        ? $this->append_query($this->article_url((int) $featured_article['article_id'], (string) ($featured_article['article_slug'] ?? '')), $index_back_params)
                        : '',
                ]);
            }
        }

        foreach ($this->search_sort_options() as $sort_key => $sort_label)
        {
            $this->template->assign_block_vars('kb_search_sort_options', [
                'VALUE' => $sort_key,
                'LABEL' => $sort_label,
                'S_SELECTED' => ($search_sort === $sort_key),
            ]);
        }

        if ($query_active)
        {
            foreach ($paged_matched_articles as $article)
            {
                $result_title = (string) ($article['article_title'] ?? '');
                $result_snippet = (string) ($article['search_snippet'] ?? $article['article_summary'] ?? '');
                $this->template->assign_block_vars('kb_search_results', [
                    'ARTICLE_ID' => (int) $article['article_id'],
                    'ARTICLE_TITLE' => $result_title,
                    'ARTICLE_TITLE_DISPLAY' => $this->highlight_text($result_title, $query),
                    'ARTICLE_SUMMARY' => $result_snippet,
                    'ARTICLE_SUMMARY_DISPLAY' => $this->highlight_text($result_snippet, $query),
                    'ARTICLE_VIEWS' => (int) ($article['article_views'] ?? 0),
                    'ARTICLE_UPDATED_AT' => !empty($article['updated_time']) ? $this->user->format_date((int) $article['updated_time']) : '',
                    'ARTICLE_HELPFUL_YES' => (int) ($article['article_helpful_yes'] ?? 0),
                    'CATEGORY_NAME' => (string) ($article['category_name'] ?? ''),
                    'CATEGORY_NAME_DISPLAY' => $this->highlight_text((string) ($article['category_name'] ?? ''), $query),
                    'MATCH_SCORE' => (int) ($article['search_score'] ?? 0),
                    'U_ARTICLE' => $this->append_query(
                        $this->article_url((int) $article['article_id'], (string) ($article['article_slug'] ?? '')),
                        $index_back_params
                    ),
                    'U_CATEGORY' => $this->append_query(
                        $this->category_url((int) ($article['category_id'] ?? 0), $this->manager->build_category_slug((string) ($article['category_name'] ?? ''))),
                        $index_back_params
                    ),
                ]);
            }

            foreach ($this->build_pagination_rows($search_page, $search_total_pages) as $page_row)
            {
                $this->template->assign_block_vars('kb_search_page_rows', [
                    'PAGE_NUMBER' => (int) $page_row['page'],
                    'PAGE_LABEL' => !empty($page_row['ellipsis']) ? '…' : (int) $page_row['page'],
                    'U_PAGE' => !empty($page_row['ellipsis']) ? '' : $this->index_url([
                        'q' => $query,
                        'category_id' => $selected_category_id,
                        'sort' => $search_sort,
                        'page' => (int) $page_row['page'],
                    ]),
                    'S_CURRENT' => !empty($page_row['current']),
                    'S_ELLIPSIS' => !empty($page_row['ellipsis']),
                ]);
            }
        }

        $total_categories = count($categories);
        $total_articles = 0;
        $visible_categories = 0;
        $visible_articles = 0;

        foreach ($categories as $category)
        {
            $total_articles += (int) ($category['article_count'] ?? 0);

            $category_id = (int) $category['category_id'];
            if ($selected_category_id > 0 && $category_id !== $selected_category_id)
            {
                continue;
            }

            if ($query_active && empty($matched_by_category[$category_id]))
            {
                continue;
            }

            $category_slug = $this->manager->build_category_slug((string) ($category['category_name'] ?? ''));
            $category_articles = (!$query_active && $search_active)
                ? (isset($matched_by_category[$category_id]) ? $matched_by_category[$category_id] : [])
                : $this->curate_home_articles($this->manager->fetch_articles_by_category($category_id, true), 4);

            $display_article_count = (!$query_active && $search_active)
                ? count($category_articles)
                : (int) ($category['article_count'] ?? 0);

            $visible_categories++;
            $visible_articles += $display_article_count;

            $featured_article = (!$query_active && !$search_active && !empty($category_articles)) ? array_shift($category_articles) : [];

            $this->template->assign_block_vars('kb_categories', [
                'CATEGORY_ID' => $category_id,
                'CATEGORY_NAME' => (string) $category['category_name'],
                'CATEGORY_DESC' => (string) $category['category_desc'],
                'CATEGORY_SLUG' => $category_slug,
                'ARTICLE_COUNT' => $display_article_count,
                'CATEGORY_SCOPE_LABEL' => $this->manager->build_category_scope_label($category),
                'CATEGORY_STATE_LABEL' => $this->manager->build_category_state_label($display_article_count),
                'U_CATEGORY' => $this->append_query($this->category_url($category_id, $category_slug), $index_back_params),
                'S_HAS_MORE_ARTICLES' => (!$search_active && (int) ($category['article_count'] ?? 0) > 1 + count($category_articles)),
                'S_HAS_FEATURED_ARTICLE' => !empty($featured_article),
                'FEATURED_ARTICLE_TITLE' => (string) ($featured_article['article_title'] ?? ''),
                'FEATURED_ARTICLE_SUMMARY' => !empty($featured_article) ? $this->home_article_summary($featured_article) : '',
                'FEATURED_ARTICLE_LABEL' => !empty($featured_article) ? $this->home_article_badge($featured_article) : '',
                'FEATURED_ARTICLE_META' => !empty($featured_article) ? $this->home_article_meta($featured_article) : '',
                'U_FEATURED_ARTICLE' => !empty($featured_article)
                    ? $this->append_query($this->article_url((int) $featured_article['article_id'], (string) ($featured_article['article_slug'] ?? '')), $index_back_params)
                    : '',
            ]);

            foreach ($category_articles as $article)
            {
                $this->template->assign_block_vars('kb_categories.kb_articles', [
                    'ARTICLE_ID' => (int) $article['article_id'],
                    'ARTICLE_TITLE' => (string) $article['article_title'],
                    'ARTICLE_SUMMARY' => (string) $article['article_summary'],
                    'ARTICLE_VIEWS' => (int) $article['article_views'],
                    'ARTICLE_HELPFUL_YES' => (int) ($article['article_helpful_yes'] ?? 0),
                    'ARTICLE_BADGE' => $this->home_article_badge($article),
                    'U_ARTICLE' => $this->append_query($this->article_url((int) $article['article_id'], (string) ($article['article_slug'] ?? '')), $index_back_params),
                ]);
            }
        }

        foreach ($popular_articles as $article)
        {
            $this->template->assign_block_vars('kb_popular_articles', [
                'ARTICLE_ID' => (int) $article['article_id'],
                'ARTICLE_TITLE' => (string) $article['article_title'],
                'ARTICLE_VIEWS' => (int) $article['article_views'],
                'CATEGORY_NAME' => (string) ($article['category_name'] ?? ''),
                'U_ARTICLE' => $this->append_query($this->article_url((int) $article['article_id'], (string) ($article['article_slug'] ?? '')), $index_back_params),
            ]);
        }

        foreach ($helpful_articles as $article)
        {
            $this->template->assign_block_vars('kb_helpful_articles', [
                'ARTICLE_ID' => (int) $article['article_id'],
                'ARTICLE_TITLE' => (string) $article['article_title'],
                'ARTICLE_HELPFUL_SCORE' => $this->manager->helpful_score($article),
                'ARTICLE_HELPFUL_YES' => (int) ($article['article_helpful_yes'] ?? 0),
                'CATEGORY_NAME' => (string) ($article['category_name'] ?? ''),
                'U_ARTICLE' => $this->append_query($this->article_url((int) $article['article_id'], (string) ($article['article_slug'] ?? '')), $index_back_params),
            ]);
        }

        foreach ($recent_articles as $article)
        {
            $this->template->assign_block_vars('kb_recent_articles', [
                'ARTICLE_ID' => (int) $article['article_id'],
                'ARTICLE_TITLE' => (string) $article['article_title'],
                'ARTICLE_UPDATED_AT' => !empty($article['updated_time']) ? $this->user->format_date((int) $article['updated_time']) : '',
                'CATEGORY_NAME' => (string) ($article['category_name'] ?? ''),
                'U_ARTICLE' => $this->append_query($this->article_url((int) $article['article_id'], (string) ($article['article_slug'] ?? '')), $index_back_params),
            ]);
        }


        $keyword_source_articles = !$search_active
            ? array_merge($helpful_articles, $popular_articles, $recent_articles)
            : ($query_active ? $paged_matched_articles : []);
        $home_keywords = $this->manager->collect_keyword_counts($keyword_source_articles, 12);
        $search_no_results_tips = $this->build_no_results_tips($query, !empty($selected_category));
        foreach ($home_keywords as $keyword_row)
        {
            $this->template->assign_block_vars('kb_home_keywords', [
                'KEYWORD' => (string) ($keyword_row['keyword'] ?? ''),
                'COUNT' => (int) ($keyword_row['count'] ?? 0),
                'U_SEARCH' => $this->index_url([
                    'q' => (string) ($keyword_row['keyword'] ?? ''),
                    'category_id' => $selected_category_id,
                ]),
            ]);
        }

        $this->template->assign_vars([
            'S_HELPDESKKB_INDEX' => true,
            'S_HELPDESKKB_HAS_CATEGORIES' => ($visible_categories > 0),
            'S_HELPDESKKB_SEARCH_ACTIVE' => $search_active,
            'S_HELPDESKKB_QUERY_ACTIVE' => $query_active,
            'S_HELPDESKKB_NO_SEARCH_RESULTS' => $search_no_results,
            'S_HELPDESKKB_HAS_POPULAR' => !empty($popular_articles),
            'S_HELPDESKKB_HAS_HELPFUL' => !empty($helpful_articles),
            'S_HELPDESKKB_HAS_RECENT' => !empty($recent_articles),
            'S_HELPDESKKB_HAS_HOME_KEYWORDS' => !empty($home_keywords),
            'S_HELPDESKKB_HAS_ACTIVE_FILTERS' => ($search_active && !empty($this->build_active_filter_rows($query, $selected_category_id, $selected_category, $search_sort))),
            'HELPDESKKB_SEARCH_QUERY' => $query,
            'HELPDESKKB_TOTAL_CATEGORIES' => $total_categories,
            'HELPDESKKB_TOTAL_ARTICLES' => $total_articles,
            'HELPDESKKB_VISIBLE_CATEGORIES' => $visible_categories,
            'HELPDESKKB_VISIBLE_ARTICLES' => $visible_articles,
            'HELPDESKKB_RESULTS_TEXT' => sprintf($this->user->lang('HELPDESKKB_RESULTS_SUMMARY'), $visible_categories, $visible_articles),
            'HELPDESKKB_SEARCH_RESULT_COUNT' => $search_total_results,
            'HELPDESKKB_SEARCH_RESULTS_FROM' => $search_results_from,
            'HELPDESKKB_SEARCH_RESULTS_TO' => $search_results_to,
            'HELPDESKKB_SEARCH_RESULTS_TOTAL' => $search_total_results,
            'HELPDESKKB_SEARCH_PAGE' => $search_page,
            'HELPDESKKB_SEARCH_TOTAL_PAGES' => $search_total_pages,
            'HELPDESKKB_SEARCH_PAGE_TEXT' => ($query_active && $search_total_results > 0)
                ? sprintf($this->user->lang('HELPDESKKB_PAGE_SUMMARY'), $search_results_from, $search_results_to, $search_total_results)
                : '',
            'HELPDESKKB_SEARCH_SORT' => $search_sort,
            'HELPDESKKB_SEARCH_TEXT' => ($query !== '') ? sprintf($this->user->lang('HELPDESKKB_SEARCH_RESULTS_QUERY'), $query) : '',
            'HELPDESKKB_NO_RESULTS_QUERY' => $query,
            'HELPDESKKB_FILTER_CATEGORY_TEXT' => !empty($selected_category)
                ? sprintf($this->user->lang('HELPDESKKB_SEARCH_RESULTS_CATEGORY'), (string) $selected_category['category_name'])
                : '',
            'S_HELPDESKKB_SEARCH_HAS_PAGINATION' => ($query_active && $search_total_pages > 1),
            'S_HELPDESKKB_SEARCH_HAS_PREV_PAGE' => ($query_active && $search_page > 1),
            'S_HELPDESKKB_SEARCH_HAS_NEXT_PAGE' => ($query_active && $search_page < $search_total_pages),
            'U_HELPDESKKB_SEARCH_PAGE_PREV' => ($query_active && $search_page > 1)
                ? $this->index_url(['q' => $query, 'category_id' => $selected_category_id, 'sort' => $search_sort, 'page' => $search_page - 1])
                : '',
            'U_HELPDESKKB_SEARCH_PAGE_NEXT' => ($query_active && $search_page < $search_total_pages)
                ? $this->index_url(['q' => $query, 'category_id' => $selected_category_id, 'sort' => $search_sort, 'page' => $search_page + 1])
                : '',
            'U_HELPDESKKB_INDEX' => $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
            'S_HELPDESKKB_HAS_CONTEXT_BACK' => !empty($back_context),
            'HELPDESKKB_CONTEXT_BACK_LABEL' => !empty($back_context) ? (string) ($back_context['label'] ?? '') : '',
            'U_HELPDESKKB_CONTEXT_BACK' => !empty($back_context) ? (string) ($back_context['url'] ?? '') : '',
            'U_HELPDESKKB_CLEAR_FILTERS' => $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
            'U_HELPDESKKB_NO_RESULTS_BROWSE_ALL' => $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
            'U_HELPDESKKB_NO_RESULTS_BROWSE_CATEGORY' => !empty($selected_category)
                ? $this->category_url((int) $selected_category['category_id'], $this->manager->build_category_slug((string) ($selected_category['category_name'] ?? '')))
                : '',
            'HELPDESKKB_NO_RESULTS_CATEGORY_NAME' => !empty($selected_category) ? (string) ($selected_category['category_name'] ?? '') : '',
        ]);

        foreach ($search_no_results_tips as $tip_text)
        {
            $this->template->assign_block_vars('kb_no_result_tips', [
                'TEXT' => (string) $tip_text,
            ]);
        }

        return $this->helper->render('helpdeskkb_body.html', $this->user->lang('HELPDESKKB_TITLE'));
    }

    protected function build_no_results_tips($query, $has_category_filter = false)
    {
        $tips = [
            $this->user->lang('HELPDESKKB_NO_RESULTS_TIP_BROADEN'),
            $this->user->lang('HELPDESKKB_NO_RESULTS_TIP_KEYWORDS'),
        ];

        if ($has_category_filter)
        {
            $tips[] = $this->user->lang('HELPDESKKB_NO_RESULTS_TIP_CATEGORY');
        }

        if (trim((string) $query) !== '' && mb_strpos((string) $query, ' ') !== false)
        {
            $tips[] = $this->user->lang('HELPDESKKB_NO_RESULTS_TIP_SHORTER');
        }

        return array_values(array_unique(array_filter($tips)));
    }

    public function category($category_id, $slug = '')
    {
        $this->user->add_lang_ext('mundophpbb/helpdeskkb', 'common');

        if (!$this->manager->extension_enabled())
        {
            trigger_error('NOT_AUTHORISED');
        }

        $category = $this->manager->fetch_category((int) $category_id);
        if (empty($category))
        {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $canonical_slug = $this->manager->build_category_slug((string) ($category['category_name'] ?? ''));
        $canonical_url = $this->category_url((int) $category['category_id'], $canonical_slug);
        if ((string) $slug !== $canonical_slug)
        {
            return new RedirectResponse($canonical_url);
        }

        $category_sort = $this->normalize_category_sort((string) $this->request->variable('sort', 'order', true));
        $category_page = max(1, (int) $this->request->variable('page', 1));
        $category_per_page = 15;
        $back_context = $this->current_back_context();
        $category_back_params = !empty($back_context['params'])
            ? $back_context['params']
            : $this->build_category_back_params((int) $category['category_id'], $canonical_slug, $category_sort, $category_page);
        $category_search_query = trim((string) $this->request->variable('q', '', true));
        $articles = $this->manager->fetch_articles_by_category((int) $category['category_id']);
        $articles = $this->sort_category_articles($articles, $category_sort);
        $category_total_articles = count($articles);
        $category_total_pages = max(1, (int) ceil($category_total_articles / $category_per_page));
        if ($category_page > $category_total_pages)
        {
            $category_page = $category_total_pages;
        }
        $category_offset = max(0, ($category_page - 1) * $category_per_page);
        $paged_articles = array_slice($articles, $category_offset, $category_per_page);
        $category_results_from = ($category_total_articles > 0) ? ($category_offset + 1) : 0;
        $category_results_to = ($category_total_articles > 0) ? min($category_offset + count($paged_articles), $category_total_articles) : 0;

        foreach ($this->category_sort_options() as $sort_key => $sort_label)
        {
            $this->template->assign_block_vars('kb_category_sort_options', [
                'VALUE' => $sort_key,
                'LABEL' => $sort_label,
                'S_SELECTED' => ($category_sort === $sort_key),
            ]);
        }

        foreach ($paged_articles as $article)
        {
            $this->template->assign_block_vars('kb_articles', [
                'ARTICLE_ID' => (int) $article['article_id'],
                'ARTICLE_TITLE' => (string) $article['article_title'],
                'ARTICLE_SUMMARY' => (string) $article['article_summary'],
                'ARTICLE_VIEWS' => (int) $article['article_views'],
                'ARTICLE_UPDATED_AT' => !empty($article['updated_time']) ? $this->user->format_date((int) $article['updated_time']) : '',
                'U_ARTICLE' => $this->append_query($this->article_url((int) $article['article_id'], (string) ($article['article_slug'] ?? '')), $category_back_params),
            ]);
        }


        $category_keywords = $this->manager->collect_keyword_counts($articles, 10);
        foreach ($category_keywords as $keyword_row)
        {
            $this->template->assign_block_vars('kb_category_keywords', [
                'KEYWORD' => (string) ($keyword_row['keyword'] ?? ''),
                'COUNT' => (int) ($keyword_row['count'] ?? 0),
                'U_SEARCH' => $this->index_url([
                    'q' => (string) ($keyword_row['keyword'] ?? ''),
                    'category_id' => (int) $category['category_id'],
                ]),
            ]);
        }

        foreach ($this->build_pagination_rows($category_page, $category_total_pages) as $page_row)
        {
            $this->template->assign_block_vars('kb_category_page_rows', [
                'PAGE_NUMBER' => (int) $page_row['page'],
                'PAGE_LABEL' => !empty($page_row['ellipsis']) ? '…' : (int) $page_row['page'],
                'U_PAGE' => !empty($page_row['ellipsis']) ? '' : $this->category_current_url((int) $category['category_id'], $canonical_slug, array_merge([
                    'sort' => $category_sort,
                    'page' => (int) $page_row['page'],
                ], $category_back_params)),
                'S_CURRENT' => !empty($page_row['current']),
                'S_ELLIPSIS' => !empty($page_row['ellipsis']),
            ]);
        }

        $this->template->assign_vars([
            'S_HELPDESKKB_CATEGORY' => true,
            'CATEGORY_ID' => (int) $category['category_id'],
            'CATEGORY_NAME' => (string) $category['category_name'],
            'CATEGORY_DESC' => (string) $category['category_desc'],
            'CATEGORY_ARTICLE_COUNT' => $category_total_articles,
            'CATEGORY_SCOPE_LABEL' => $this->manager->build_category_scope_label($category),
            'CATEGORY_STATE_LABEL' => $this->manager->build_category_state_label($category_total_articles),
            'CATEGORY_SORT' => $category_sort,
            'CATEGORY_PAGE' => $category_page,
            'CATEGORY_SEARCH_QUERY' => $category_search_query,
            'CATEGORY_TOTAL_PAGES' => $category_total_pages,
            'CATEGORY_RESULTS_TEXT' => ($category_total_articles > 0)
                ? sprintf($this->user->lang('HELPDESKKB_PAGE_SUMMARY'), $category_results_from, $category_results_to, $category_total_articles)
                : '',
            'U_HELPDESKKB_INDEX' => $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
            'U_HELPDESKKB_INDEX_FILTERED' => $this->helper->route('mundophpbb_helpdeskkb_index_controller', [
                'category_id' => (int) $category['category_id'],
            ]),
            'U_HELPDESKKB_CATEGORY_SEARCH' => $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
            'U_CATEGORY' => $canonical_url,
            'S_HELPDESKKB_HAS_ARTICLES' => !empty($paged_articles),
            'S_HELPDESKKB_HAS_CONTEXT_BACK' => !empty($back_context),
            'HELPDESKKB_CONTEXT_BACK_LABEL' => !empty($back_context) ? (string) ($back_context['label'] ?? '') : '',
            'U_HELPDESKKB_CONTEXT_BACK' => !empty($back_context) ? (string) ($back_context['url'] ?? '') : '',
            'S_HELPDESKKB_CATEGORY_HAS_PAGINATION' => ($category_total_pages > 1),
            'S_HELPDESKKB_CATEGORY_HAS_PREV_PAGE' => ($category_page > 1),
            'S_HELPDESKKB_CATEGORY_HAS_NEXT_PAGE' => ($category_page < $category_total_pages),
            'S_HELPDESKKB_HAS_CATEGORY_KEYWORDS' => !empty($category_keywords),
            'U_HELPDESKKB_CATEGORY_PAGE_PREV' => ($category_page > 1)
                ? $this->category_current_url((int) $category['category_id'], $canonical_slug, array_merge(['sort' => $category_sort, 'page' => $category_page - 1], $category_back_params))
                : '',
            'U_HELPDESKKB_CATEGORY_PAGE_NEXT' => ($category_page < $category_total_pages)
                ? $this->category_current_url((int) $category['category_id'], $canonical_slug, array_merge(['sort' => $category_sort, 'page' => $category_page + 1], $category_back_params))
                : '',
        ]);

        return $this->helper->render('helpdeskkb_category_body.html', (string) $category['category_name']);
    }

    public function article($article_id, $slug = '')
    {
        $this->user->add_lang_ext('mundophpbb/helpdeskkb', 'common');

        if (!$this->manager->extension_enabled())
        {
            trigger_error('NOT_AUTHORISED');
        }

        $article = $this->manager->fetch_article((int) $article_id);
        if (empty($article))
        {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $canonical_slug = trim((string) ($article['article_slug'] ?? ''));
        if ($canonical_slug === '')
        {
            $canonical_slug = $this->manager->slugify((string) ($article['article_title'] ?? ''), 'article');
        }

        $canonical_url = $this->article_url((int) $article['article_id'], $canonical_slug);
        $back_context = $this->current_back_context();

        if ($this->request->is_set_post('vote_helpful'))
        {
            \add_form_key('mundophpbb_helpdeskkb_vote');
            if (!check_form_key('mundophpbb_helpdeskkb_vote'))
            {
                trigger_error('FORM_INVALID');
            }

            $vote = (string) $this->request->variable('vote', '', true);
            if (in_array($vote, ['yes', 'no'], true))
            {
                $this->manager->vote_article((int) $article['article_id'], $vote);
                return new RedirectResponse($this->append_query($canonical_url, ['voted' => $vote]));
            }

            return new RedirectResponse($canonical_url);
        }

        if ((string) $slug !== $canonical_slug)
        {
            return new RedirectResponse($canonical_url);
        }

        $voted = (string) $this->request->variable('voted', '', true);
        if (!in_array($voted, ['yes', 'no'], true))
        {
            $voted = '';
        }

        $this->manager->increment_article_view((int) $article['article_id']);
        $article_html = $this->manager->parse_for_display($article);
        $category_url = '';
        if (!empty($article['category_id']))
        {
            $category_url = $this->category_url((int) $article['category_id'], $this->manager->build_category_slug((string) ($article['category_name'] ?? '')));
        }

        $neighbors = $this->manager->fetch_article_neighbors((int) $article['article_id'], (int) ($article['category_id'] ?? 0));
        $related_articles = $this->manager->fetch_related_articles($article, 4);
        $category_articles = !empty($article['category_id'])
            ? $this->manager->fetch_articles_by_category((int) $article['category_id'], true)
            : [];
        $article_position = 0;
        $article_count_in_category = count($category_articles);
        $article_reading_minutes = $this->estimate_article_reading_minutes((string) ($article['article_text'] ?? ''));

        if (!empty($category_articles))
        {
            foreach ($category_articles as $index => $category_article)
            {
                if ((int) ($category_article['article_id'] ?? 0) === (int) $article['article_id'])
                {
                    $article_position = $index + 1;
                    break;
                }
            }
        }

        if (!empty($neighbors['previous']))
        {
            $this->template->assign_block_vars('kb_article_navigation_previous', [
                'ARTICLE_TITLE' => (string) ($neighbors['previous']['article_title'] ?? ''),
                'ARTICLE_SUMMARY' => (string) ($neighbors['previous']['article_summary'] ?? ''),
                'U_ARTICLE' => $this->append_query($this->article_url((int) ($neighbors['previous']['article_id'] ?? 0), (string) ($neighbors['previous']['article_slug'] ?? '')), !empty($back_context['params']) ? $back_context['params'] : []),
            ]);
        }

        if (!empty($neighbors['next']))
        {
            $this->template->assign_block_vars('kb_article_navigation_next', [
                'ARTICLE_TITLE' => (string) ($neighbors['next']['article_title'] ?? ''),
                'ARTICLE_SUMMARY' => (string) ($neighbors['next']['article_summary'] ?? ''),
                'U_ARTICLE' => $this->append_query($this->article_url((int) ($neighbors['next']['article_id'] ?? 0), (string) ($neighbors['next']['article_slug'] ?? '')), !empty($back_context['params']) ? $back_context['params'] : []),
            ]);
        }


        $article_keywords = $this->manager->extract_keywords((string) ($article['article_keywords'] ?? ''), 12);
        foreach ($article_keywords as $keyword)
        {
            $this->template->assign_block_vars('kb_article_keywords', [
                'KEYWORD' => (string) $keyword,
                'U_SEARCH' => $this->index_url(['q' => (string) $keyword]),
            ]);
        }

        if (!empty($category_articles))
        {
            foreach ($category_articles as $category_article)
            {
                if ((int) ($category_article['article_id'] ?? 0) === (int) $article['article_id'])
                {
                    continue;
                }

                $this->template->assign_block_vars('kb_this_category_articles', [
                    'ARTICLE_TITLE' => (string) ($category_article['article_title'] ?? ''),
                    'U_ARTICLE' => $this->append_query($this->article_url((int) ($category_article['article_id'] ?? 0), (string) ($category_article['article_slug'] ?? '')), !empty($back_context['params']) ? $back_context['params'] : []),
                ]);
            }
        }

        foreach ($related_articles as $related_article)
        {
            $this->template->assign_block_vars('kb_related_articles', [
                'ARTICLE_TITLE' => (string) ($related_article['article_title'] ?? ''),
                'U_ARTICLE' => $this->append_query($this->article_url((int) ($related_article['article_id'] ?? 0), (string) ($related_article['article_slug'] ?? '')), !empty($back_context['params']) ? $back_context['params'] : []),
            ]);
        }

        \add_form_key('mundophpbb_helpdeskkb_vote');

        $article_scope_label = $this->manager->build_category_scope_label([
            'forum_id' => !empty($article['forum_id']) ? (int) $article['forum_id'] : (!empty($article['category_forum_id']) ? (int) $article['category_forum_id'] : 0),
            'department_key' => !empty($article['department_key']) ? (string) $article['department_key'] : (!empty($article['category_department_key']) ? (string) $article['category_department_key'] : ''),
        ]);

        $article_search_query = (!empty($back_context['params']['back_q'])) ? trim((string) $back_context['params']['back_q']) : '';

        $this->template->assign_vars([
            'S_HELPDESKKB_ARTICLE' => true,
            'ARTICLE_ID' => (int) $article['article_id'],
            'ARTICLE_TITLE' => (string) $article['article_title'],
            'ARTICLE_SUMMARY' => (string) $article['article_summary'],
            'ARTICLE_SUMMARY_DISPLAY' => ($article_search_query !== '')
                ? $this->highlight_text((string) $article['article_summary'], $article_search_query)
                : htmlspecialchars((string) $article['article_summary'], ENT_QUOTES, 'UTF-8'),
            'ARTICLE_CONTENT' => $article_html,
            'ARTICLE_CATEGORY' => (string) $article['category_name'],
            'ARTICLE_VIEWS' => (int) $article['article_views'] + 1,
            'ARTICLE_HELPFUL_YES' => (int) $article['article_helpful_yes'],
            'ARTICLE_HELPFUL_NO' => (int) $article['article_helpful_no'],
            'ARTICLE_UPDATED_AT' => !empty($article['updated_time']) ? $this->user->format_date((int) $article['updated_time']) : '',
            'ARTICLE_SCOPE_LABEL' => $article_scope_label,
            'ARTICLE_HELPFUL_SCORE' => $this->manager->helpful_score($article),
            'ARTICLE_READING_TIME_TEXT' => sprintf($this->user->lang('HELPDESKKB_READING_TIME_VALUE'), $article_reading_minutes),
            'ARTICLE_POSITION_TEXT' => ($article_position > 0 && $article_count_in_category > 0)
                ? sprintf($this->user->lang('HELPDESKKB_POSITION_IN_CATEGORY'), $article_position, $article_count_in_category)
                : '',
            'S_HAS_ARTICLE_POSITION' => ($article_position > 0 && $article_count_in_category > 0),
            'S_HELPDESKKB_SEARCH_CONTEXT' => ($article_search_query !== ''),
            'HELPDESKKB_SEARCH_CONTEXT_TEXT' => ($article_search_query !== '')
                ? sprintf($this->user->lang('HELPDESKKB_SEARCH_RESULTS_QUERY'), htmlspecialchars($article_search_query, ENT_QUOTES, 'UTF-8'))
                : '',
            'S_HELPDESKKB_VOTED' => ($voted !== ''),
            'HELPDESKKB_VOTED_TEXT' => ($voted === 'yes') ? $this->user->lang('HELPDESKKB_VOTE_THANK_YES') : (($voted === 'no') ? $this->user->lang('HELPDESKKB_VOTE_THANK_NO') : ''),
            'S_HELPDESKKB_HAS_CONTEXT_BACK' => !empty($back_context),
            'HELPDESKKB_CONTEXT_BACK_LABEL' => !empty($back_context) ? (string) ($back_context['label'] ?? '') : '',
            'U_HELPDESKKB_CONTEXT_BACK' => !empty($back_context) ? (string) ($back_context['url'] ?? '') : '',
            'U_HELPDESKKB_INDEX' => $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
            'U_HELPDESKKB_INDEX_FILTERED' => !empty($article['category_id'])
                ? $this->helper->route('mundophpbb_helpdeskkb_index_controller', ['category_id' => (int) $article['category_id']])
                : $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
            'U_CATEGORY' => $this->append_query($category_url, !empty($back_context['params']) ? $back_context['params'] : []),
            'U_ARTICLE' => $this->append_query($canonical_url, !empty($back_context['params']) ? $back_context['params'] : []),
            'S_HAS_CATEGORY_URL' => ($category_url !== ''),
            'S_HAS_PREVIOUS_ARTICLE' => !empty($neighbors['previous']),
            'S_HAS_NEXT_ARTICLE' => !empty($neighbors['next']),
            'S_HAS_RELATED_ARTICLES' => !empty($related_articles),
            'S_HAS_ARTICLE_KEYWORDS' => !empty($article_keywords),
            'S_HAS_THIS_CATEGORY_ARTICLES' => ($article_count_in_category > 1),
        ]);
        return $this->helper->render('helpdeskkb_article_body.html', (string) $article['article_title']);
    }

    protected function estimate_article_reading_minutes($article_text)
    {
        $plain = trim($this->manager->search_plain_text((string) $article_text));
        if ($plain === '')
        {
            return 1;
        }

        $word_count = preg_match_all('/\S+/u', $plain, $matches);
        $minutes = (int) ceil(max(1, (int) $word_count) / 180);

        return max(1, $minutes);
    }


    protected function search_sort_options()
    {
        return [
            'relevance' => $this->user->lang('HELPDESKKB_SORT_RELEVANCE'),
            'recent' => $this->user->lang('HELPDESKKB_SORT_RECENT'),
            'views' => $this->user->lang('HELPDESKKB_SORT_VIEWS'),
            'helpful' => $this->user->lang('HELPDESKKB_SORT_HELPFUL'),
            'title' => $this->user->lang('HELPDESKKB_SORT_TITLE'),
        ];
    }

    protected function category_sort_options()
    {
        return [
            'order' => $this->user->lang('HELPDESKKB_SORT_ORDER'),
            'recent' => $this->user->lang('HELPDESKKB_SORT_RECENT'),
            'views' => $this->user->lang('HELPDESKKB_SORT_VIEWS'),
            'helpful' => $this->user->lang('HELPDESKKB_SORT_HELPFUL'),
            'title' => $this->user->lang('HELPDESKKB_SORT_TITLE'),
        ];
    }

    protected function normalize_search_sort($value)
    {
        $value = trim((string) $value);
        return in_array($value, ['relevance', 'recent', 'views', 'helpful', 'title'], true) ? $value : 'relevance';
    }

    protected function normalize_category_sort($value)
    {
        $value = trim((string) $value);
        return in_array($value, ['order', 'recent', 'views', 'helpful', 'title'], true) ? $value : 'order';
    }

    protected function highlight_text($text, $query)
    {
        $text = trim((string) $text);
        $query = trim((string) $query);

        if ($text === '')
        {
            return '';
        }

        if ($query === '')
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        $terms = preg_split('/\s+/u', mb_strtolower($query));
        $terms = array_values(array_filter(array_unique(array_map('trim', (array) $terms)), function ($term) {
            return mb_strlen($term) >= 2;
        }));

        if (empty($terms))
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        usort($terms, function ($left, $right) {
            return mb_strlen($right) <=> mb_strlen($left);
        });

        $pattern = '/(' . implode('|', array_map(function ($term) {
            return preg_quote($term, '/');
        }, $terms)) . ')/iu';

        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false)
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        $output = '';
        foreach ($parts as $part)
        {
            $escaped = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
            if (preg_match($pattern, $part))
            {
                $output .= '<mark class="helpdeskkb-hit">' . $escaped . '</mark>';
            }
            else
            {
                $output .= $escaped;
            }
        }

        return $output;
    }


    protected function sort_search_results(array $rows, $sort)
    {
        $sort = $this->normalize_search_sort($sort);
        usort($rows, function ($left, $right) use ($sort) {
            switch ($sort)
            {
                case 'recent':
                    $l = (int) ($left['updated_time'] ?? $left['created_time'] ?? 0);
                    $r = (int) ($right['updated_time'] ?? $right['created_time'] ?? 0);
                    if ($l !== $r)
                    {
                        return $r <=> $l;
                    }
                    break;

                case 'views':
                    $l = (int) ($left['article_views'] ?? 0);
                    $r = (int) ($right['article_views'] ?? 0);
                    if ($l !== $r)
                    {
                        return $r <=> $l;
                    }
                    break;

                case 'helpful':
                    $l = $this->manager->helpful_score($left);
                    $r = $this->manager->helpful_score($right);
                    if ($l !== $r)
                    {
                        return $r <=> $l;
                    }
                    break;

                case 'title':
                    $l = strcasecmp((string) ($left['article_title'] ?? ''), (string) ($right['article_title'] ?? ''));
                    if ($l !== 0)
                    {
                        return $l;
                    }
                    break;

                case 'relevance':
                default:
                    $l = (int) ($left['search_score'] ?? 0);
                    $r = (int) ($right['search_score'] ?? 0);
                    if ($l !== $r)
                    {
                        return $r <=> $l;
                    }
                    break;
            }

            $scoreLeft = (int) ($left['search_score'] ?? 0);
            $scoreRight = (int) ($right['search_score'] ?? 0);
            if ($scoreLeft !== $scoreRight)
            {
                return $scoreRight <=> $scoreLeft;
            }

            $helpfulLeft = $this->manager->helpful_score($left);
            $helpfulRight = $this->manager->helpful_score($right);
            if ($helpfulLeft !== $helpfulRight)
            {
                return $helpfulRight <=> $helpfulLeft;
            }

            $helpfulLeft = $this->manager->helpful_score($left);
            $helpfulRight = $this->manager->helpful_score($right);
            if ($helpfulLeft !== $helpfulRight)
            {
                return $helpfulRight <=> $helpfulLeft;
            }

            $titleCompare = strcasecmp((string) ($left['article_title'] ?? ''), (string) ($right['article_title'] ?? ''));
            if ($titleCompare !== 0)
            {
                return $titleCompare;
            }

            return ((int) ($left['article_id'] ?? 0)) <=> ((int) ($right['article_id'] ?? 0));
        });

        return $rows;
    }

    protected function sort_category_articles(array $rows, $sort)
    {
        $sort = $this->normalize_category_sort($sort);
        usort($rows, function ($left, $right) use ($sort) {
            switch ($sort)
            {
                case 'recent':
                    $l = (int) ($left['updated_time'] ?? $left['created_time'] ?? 0);
                    $r = (int) ($right['updated_time'] ?? $right['created_time'] ?? 0);
                    if ($l !== $r)
                    {
                        return $r <=> $l;
                    }
                    break;

                case 'views':
                    $l = (int) ($left['article_views'] ?? 0);
                    $r = (int) ($right['article_views'] ?? 0);
                    if ($l !== $r)
                    {
                        return $r <=> $l;
                    }
                    break;

                case 'helpful':
                    $l = $this->manager->helpful_score($left);
                    $r = $this->manager->helpful_score($right);
                    if ($l !== $r)
                    {
                        return $r <=> $l;
                    }
                    break;

                case 'title':
                    $cmp = strcasecmp((string) ($left['article_title'] ?? ''), (string) ($right['article_title'] ?? ''));
                    if ($cmp !== 0)
                    {
                        return $cmp;
                    }
                    break;

                case 'order':
                default:
                    $l = (int) ($left['sort_order'] ?? 0);
                    $r = (int) ($right['sort_order'] ?? 0);
                    if ($l !== $r)
                    {
                        return $l <=> $r;
                    }
                    break;
            }

            $titleCompare = strcasecmp((string) ($left['article_title'] ?? ''), (string) ($right['article_title'] ?? ''));
            if ($titleCompare !== 0)
            {
                return $titleCompare;
            }

            return ((int) ($left['article_id'] ?? 0)) <=> ((int) ($right['article_id'] ?? 0));
        });

        return $rows;
    }

    protected function curate_home_articles(array $rows, $limit = 4)
    {
        usort($rows, function ($left, $right) {
            $scoreLeft = $this->home_article_priority($left);
            $scoreRight = $this->home_article_priority($right);
            if ($scoreLeft !== $scoreRight)
            {
                return $scoreRight <=> $scoreLeft;
            }

            $titleCompare = strcasecmp((string) ($left['article_title'] ?? ''), (string) ($right['article_title'] ?? ''));
            if ($titleCompare !== 0)
            {
                return $titleCompare;
            }

            return ((int) ($left['article_id'] ?? 0)) <=> ((int) ($right['article_id'] ?? 0));
        });

        return array_slice($rows, 0, max(1, (int) $limit));
    }

    protected function home_article_priority(array $row)
    {
        $helpful = max(0, $this->manager->helpful_score($row));
        $views = (int) ($row['article_views'] ?? 0);
        $updated = (int) ($row['updated_time'] ?? $row['created_time'] ?? 0);
        $sortOrder = (int) ($row['sort_order'] ?? 9999);
        $recencyBonus = 0;
        if ($updated > 0)
        {
            $days = max(0, (int) floor((time() - $updated) / 86400));
            $recencyBonus = max(0, 45 - min(45, $days));
        }

        return ($helpful * 30) + min(160, $views * 2) + $recencyBonus + max(0, 35 - min(35, $sortOrder));
    }

    protected function home_article_badge(array $row)
    {
        $helpfulYes = (int) ($row['article_helpful_yes'] ?? 0);
        $views = (int) ($row['article_views'] ?? 0);
        $updated = (int) ($row['updated_time'] ?? $row['created_time'] ?? 0);

        if ($helpfulYes >= 3)
        {
            return $this->user->lang('HELPDESKKB_HOME_BADGE_HELPFUL');
        }

        if ($views >= 25)
        {
            return $this->user->lang('HELPDESKKB_HOME_BADGE_POPULAR');
        }

        if ($updated > 0 && (time() - $updated) <= 1209600)
        {
            return $this->user->lang('HELPDESKKB_HOME_BADGE_RECENT');
        }

        return $this->user->lang('HELPDESKKB_HOME_BADGE_RECOMMENDED');
    }

    protected function home_article_summary(array $row)
    {
        $summary = trim((string) ($row['article_summary'] ?? ''));
        if ($summary === '')
        {
            $summary = trim($this->manager->search_plain_text((string) ($row['article_text'] ?? '')));
        }

        if ($summary === '')
        {
            return '';
        }

        if (mb_strlen($summary) > 140)
        {
            $summary = rtrim(mb_substr($summary, 0, 137)) . '…';
        }

        return $summary;
    }

    protected function home_article_meta(array $row)
    {
        $parts = [];
        $views = (int) ($row['article_views'] ?? 0);
        $helpfulYes = (int) ($row['article_helpful_yes'] ?? 0);
        $updated = (int) ($row['updated_time'] ?? $row['created_time'] ?? 0);

        if ($views > 0)
        {
            $parts[] = $views . ' ' . $this->user->lang('HELPDESKKB_VIEWS_LABEL');
        }
        if ($helpfulYes > 0)
        {
            $parts[] = $helpfulYes . ' ' . $this->user->lang('HELPDESKKB_HELPFUL_YES_LABEL');
        }
        if ($updated > 0)
        {
            $parts[] = $this->user->format_date($updated);
        }

        return implode(' • ', $parts);
    }

    protected function build_pagination_rows($current_page, $total_pages)
    {
        $current_page = max(1, (int) $current_page);
        $total_pages = max(1, (int) $total_pages);
        $rows = [];

        if ($total_pages <= 7)
        {
            for ($page = 1; $page <= $total_pages; $page++)
            {
                $rows[] = [
                    'page' => $page,
                    'current' => ($page === $current_page),
                    'ellipsis' => false,
                ];
            }
            return $rows;
        }

        $pages = [1, 2, $current_page - 1, $current_page, $current_page + 1, $total_pages - 1, $total_pages];
        $pages = array_values(array_unique(array_filter($pages, function ($page) use ($total_pages) {
            return $page >= 1 && $page <= $total_pages;
        })));
        sort($pages);

        $last = 0;
        foreach ($pages as $page)
        {
            if ($last > 0 && $page - $last > 1)
            {
                $rows[] = [
                    'page' => 0,
                    'current' => false,
                    'ellipsis' => true,
                ];
            }

            $rows[] = [
                'page' => $page,
                'current' => ($page === $current_page),
                'ellipsis' => false,
            ];
            $last = $page;
        }

        return $rows;
    }


    protected function build_active_filter_rows($query, $selected_category_id, array $selected_category, $search_sort)
    {
        $rows = [];
        $query = trim((string) $query);
        $selected_category_id = (int) $selected_category_id;
        $search_sort = $this->normalize_search_sort($search_sort);

        if ($query !== '')
        {
            $rows[] = [
                'LABEL' => sprintf($this->user->lang('HELPDESKKB_FILTER_QUERY_LABEL'), $query),
                'U_REMOVE' => $this->index_url([
                    'category_id' => $selected_category_id,
                ]),
            ];
        }

        if ($selected_category_id > 0 && !empty($selected_category))
        {
            $rows[] = [
                'LABEL' => sprintf($this->user->lang('HELPDESKKB_FILTER_CATEGORY_LABEL'), (string) ($selected_category['category_name'] ?? '')),
                'U_REMOVE' => $this->index_url([
                    'q' => $query,
                ]),
            ];
        }

        if ($query !== '' && $search_sort !== 'relevance')
        {
            $rows[] = [
                'LABEL' => sprintf($this->user->lang('HELPDESKKB_FILTER_SORT_LABEL'), (string) ($this->search_sort_options()[$search_sort] ?? $search_sort)),
                'U_REMOVE' => $this->index_url([
                    'q' => $query,
                    'category_id' => $selected_category_id,
                ]),
            ];
        }

        return $rows;
    }

    protected function build_index_back_params($query, $category_id, $sort, $page)
    {
        return [
            'back_to' => 'index',
            'back_q' => trim((string) $query),
            'back_category_id' => (int) $category_id,
            'back_sort' => ($this->normalize_search_sort($sort) !== 'relevance') ? $this->normalize_search_sort($sort) : '',
            'back_page' => max(1, (int) $page),
        ];
    }

    protected function build_category_back_params($category_id, $slug, $sort, $page)
    {
        return [
            'back_to' => 'category',
            'back_category_id' => (int) $category_id,
            'back_category_slug' => (string) $slug,
            'back_category_sort' => ($this->normalize_category_sort($sort) !== 'order') ? $this->normalize_category_sort($sort) : '',
            'back_category_page' => max(1, (int) $page),
        ];
    }

    protected function current_back_context()
    {
        $back_to = trim((string) $this->request->variable('back_to', '', true));
        if ($back_to === 'index')
        {
            $query = trim((string) $this->request->variable('back_q', '', true));
            $category_id = (int) $this->request->variable('back_category_id', 0);
            $sort = $this->normalize_search_sort((string) $this->request->variable('back_sort', 'relevance', true));
            $page = max(1, (int) $this->request->variable('back_page', 1));
            $params = $this->build_index_back_params($query, $category_id, $sort, $page);

            return [
                'label' => $this->user->lang('HELPDESKKB_BACK_TO_RESULTS'),
                'url' => $this->index_url([
                    'q' => $query,
                    'category_id' => $category_id,
                    'sort' => $sort,
                    'page' => $page,
                ]),
                'params' => $params,
            ];
        }

        if ($back_to === 'category')
        {
            $category_id = (int) $this->request->variable('back_category_id', 0);
            if ($category_id <= 0)
            {
                return [];
            }

            $slug = trim((string) $this->request->variable('back_category_slug', '', true));
            $sort = $this->normalize_category_sort((string) $this->request->variable('back_category_sort', 'order', true));
            $page = max(1, (int) $this->request->variable('back_category_page', 1));
            $params = $this->build_category_back_params($category_id, $slug, $sort, $page);

            return [
                'label' => $this->user->lang('HELPDESKKB_BACK_TO_CATEGORY_PAGE'),
                'url' => $this->category_current_url($category_id, $slug, [
                    'sort' => $sort,
                    'page' => $page,
                ]),
                'params' => $params,
            ];
        }

        return [];
    }

    protected function index_url(array $params = [])
    {
        return $this->append_query($this->helper->route('mundophpbb_helpdeskkb_index_controller'), $params);
    }

    protected function category_current_url($category_id, $slug = '', array $params = [])
    {
        return $this->append_query($this->category_url($category_id, $slug), $params);
    }

    protected function append_query($url, array $params)
    {
        $clean = [];
        foreach ($params as $key => $value)
        {
            if ($value === null || $value === '' || $value === 0 || $value === '0')
            {
                continue;
            }
            $clean[$key] = $value;
        }

        if (empty($clean))
        {
            return $url;
        }

        return $url . ((strpos($url, '?') === false) ? '?' : '&') . http_build_query($clean);
    }

    protected function category_url($category_id, $slug = '')
    {
        $slug = trim((string) $slug);
        if ($slug === '')
        {
            return $this->helper->route('mundophpbb_helpdeskkb_category_controller', [
                'category_id' => (int) $category_id,
            ]);
        }

        return $this->helper->route('mundophpbb_helpdeskkb_category_slug_controller', [
            'category_id' => (int) $category_id,
            'slug' => $slug,
        ]);
    }

    protected function article_url($article_id, $slug = '')
    {
        $slug = trim((string) $slug);
        if ($slug === '')
        {
            return $this->helper->route('mundophpbb_helpdeskkb_article_controller', [
                'article_id' => (int) $article_id,
            ]);
        }

        return $this->helper->route('mundophpbb_helpdeskkb_article_slug_controller', [
            'article_id' => (int) $article_id,
            'slug' => $slug,
        ]);
    }

    protected function find_category(array $categories, $category_id)
    {
        $category_id = (int) $category_id;
        foreach ($categories as $category)
        {
            if ((int) $category['category_id'] === $category_id)
            {
                return $category;
            }
        }

        return [];
    }


    public function posting_suggestions($forum_id)
    {
        $query = trim((string) $this->request->variable('q', '', true));
        $department_key = trim((string) $this->request->variable('department_key', '', true));
        $limit = max(1, min(6, (int) $this->request->variable('limit', 4)));

        if (!$this->manager->helpdesk_integration_enabled() || empty($this->config['helpdeskkb_suggestions_enabled']))
        {
            return new JsonResponse([
                'success' => false,
                'items' => [],
            ]);
        }

        if (mb_strlen($query) < 4)
        {
            return new JsonResponse([
                'success' => true,
                'items' => [],
            ]);
        }

        $suggestions = $this->manager->build_topic_suggestions((int) $forum_id, $query, $limit, $department_key);
        $items = [];
        foreach ($suggestions as $suggestion)
        {
            $items[] = [
                'id' => (int) $suggestion['article_id'],
                'title' => (string) ($suggestion['article_title'] ?? ''),
                'summary' => (string) ($suggestion['article_summary'] ?? ''),
                'category' => (string) ($suggestion['category_name'] ?? ''),
                'url' => $this->article_url((int) $suggestion['article_id'], (string) ($suggestion['article_slug'] ?? '')),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'items' => $items,
        ]);
    }

}
