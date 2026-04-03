<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\service;

class kb_manager
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    /** @var \phpbb\config\config */
    protected $config;

    /** @var \phpbb\user */
    protected $user;

    /** @var string */
    protected $table_prefix;

    public function __construct(
        \phpbb\db\driver\driver_interface $db,
        \phpbb\config\config $config,
        \phpbb\user $user,
        $table_prefix
    ) {
        $this->db = $db;
        $this->config = $config;
        $this->user = $user;
        $this->table_prefix = (string) $table_prefix;
    }

    public function categories_table()
    {
        return $this->table_prefix . 'helpdesk_kb_categories';
    }

    public function articles_table()
    {
        return $this->table_prefix . 'helpdesk_kb_articles';
    }

    public function helpdesk_integration_enabled()
    {
        $mode = isset($this->config['helpdeskkb_mode']) ? (string) $this->config['helpdeskkb_mode'] : 'integrated';
        return $this->extension_enabled() && $mode === 'integrated';
    }

    public function operation_mode()
    {
        $mode = isset($this->config['helpdeskkb_mode']) ? (string) $this->config['helpdeskkb_mode'] : 'integrated';
        return in_array($mode, ['standalone', 'integrated'], true) ? $mode : 'integrated';
    }

    public function extension_enabled()
    {
        return !empty($this->config['helpdeskkb_enabled']);
    }

    public function fetch_categories_with_counts()
    {
        $sql = 'SELECT c.*, COUNT(a.article_id) AS article_count
            FROM ' . $this->categories_table() . ' c
            LEFT JOIN ' . $this->articles_table() . ' a
                ON a.category_id = c.category_id
                AND a.article_enabled = 1
            WHERE c.category_enabled = 1
            GROUP BY c.category_id, c.category_name, c.category_desc, c.forum_id, c.department_key, c.sort_order, c.category_enabled, c.created_time, c.updated_time
            ORDER BY c.sort_order ASC, c.category_name ASC, c.category_id ASC';
        $result = $this->db->sql_query($sql);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    public function fetch_category($category_id)
    {
        $sql = 'SELECT c.*, COUNT(a.article_id) AS article_count
            FROM ' . $this->categories_table() . ' c
            LEFT JOIN ' . $this->articles_table() . ' a
                ON a.category_id = c.category_id
                AND a.article_enabled = 1
            WHERE c.category_id = ' . (int) $category_id . '
                AND c.category_enabled = 1
            GROUP BY c.category_id, c.category_name, c.category_desc, c.forum_id, c.department_key, c.sort_order, c.category_enabled, c.created_time, c.updated_time';
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ?: [];
    }

    public function fetch_articles_by_category($category_id, $enabled_only = true, $limit = 0)
    {
        $sql = 'SELECT *
            FROM ' . $this->articles_table() . '
            WHERE category_id = ' . (int) $category_id;

        if ($enabled_only)
        {
            $sql .= "\n                AND article_enabled = 1";
        }

        $sql .= "\n            ORDER BY sort_order ASC, article_title ASC, article_id ASC";
        $result = ((int) $limit > 0)
            ? $this->db->sql_query_limit($sql, (int) $limit)
            : $this->db->sql_query($sql);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    public function fetch_article($article_id)
    {
        $sql = 'SELECT a.*, c.category_id, c.category_name, c.forum_id AS category_forum_id, c.department_key AS category_department_key
            FROM ' . $this->articles_table() . ' a
            LEFT JOIN ' . $this->categories_table() . ' c
                ON c.category_id = a.category_id
            WHERE a.article_id = ' . (int) $article_id . '
                AND a.article_enabled = 1';
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ?: [];
    }

    public function fetch_all_categories_admin()
    {
        $sql = 'SELECT c.*, COUNT(a.article_id) AS article_count
            FROM ' . $this->categories_table() . ' c
            LEFT JOIN ' . $this->articles_table() . ' a
                ON a.category_id = c.category_id
            GROUP BY c.category_id, c.category_name, c.category_desc, c.forum_id, c.department_key, c.sort_order, c.category_enabled, c.created_time, c.updated_time
            ORDER BY c.sort_order ASC, c.category_name ASC, c.category_id ASC';
        $result = $this->db->sql_query($sql);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    public function fetch_all_articles_admin(array $filters = [])
    {
        $keywords = trim((string) ($filters['keywords'] ?? ''));
        $category_id = (int) ($filters['category_id'] ?? 0);
        $forum_id = (int) ($filters['forum_id'] ?? 0);
        $department_key = trim((string) ($filters['department_key'] ?? ''));
        $enabled = (string) ($filters['enabled'] ?? '');

        $conditions = [];

        if ($category_id > 0)
        {
            $conditions[] = 'a.category_id = ' . $category_id;
        }

        if ($forum_id > 0)
        {
            $conditions[] = 'a.forum_id = ' . $forum_id;
        }

        if ($department_key !== '')
        {
            $conditions[] = "a.department_key = '" . $this->db->sql_escape($department_key) . "'";
        }

        if ($enabled === '1' || $enabled === '0')
        {
            $conditions[] = 'a.article_enabled = ' . (int) $enabled;
        }

        if ($keywords !== '')
        {
            $needle = $this->db->get_any_char() . strtolower($this->db->sql_escape($keywords)) . $this->db->get_any_char();
            $conditions[] = '(' .
                'LOWER(a.article_title) ' . $this->db->sql_like_expression($needle) . ' OR ' .
                'LOWER(a.article_summary) ' . $this->db->sql_like_expression($needle) . ' OR ' .
                'LOWER(a.article_keywords) ' . $this->db->sql_like_expression($needle) .
            ')';
        }

        $sql = 'SELECT a.*, c.category_name, c.forum_id AS category_forum_id, c.department_key AS category_department_key
            FROM ' . $this->articles_table() . ' a
            LEFT JOIN ' . $this->categories_table() . ' c
                ON c.category_id = a.category_id';

        if (!empty($conditions))
        {
            $sql .= "\n            WHERE " . implode("\n                AND ", $conditions);
        }

        $sql .= "\n            ORDER BY c.sort_order ASC, c.category_name ASC, a.sort_order ASC, a.article_title ASC, a.article_id ASC";
        $result = $this->db->sql_query($sql);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }


    public function fetch_public_articles(array $filters = [], $limit = 0)
    {
        $keywords = trim((string) ($filters['keywords'] ?? ''));
        $category_id = (int) ($filters['category_id'] ?? 0);
        $conditions = [
            'a.article_enabled = 1',
            '(c.category_enabled = 1 OR c.category_enabled IS NULL)',
        ];

        if ($category_id > 0)
        {
            $conditions[] = 'a.category_id = ' . $category_id;
        }

        if ($keywords !== '')
        {
            $terms = $this->search_terms($keywords);
            $term_conditions = [];

            foreach ($terms as $term)
            {
                $needle = $this->db->get_any_char() . strtolower($this->db->sql_escape($term)) . $this->db->get_any_char();
                $term_conditions[] = '(' .
                    'LOWER(a.article_title) ' . $this->db->sql_like_expression($needle) . ' OR ' .
                    'LOWER(a.article_summary) ' . $this->db->sql_like_expression($needle) . ' OR ' .
                    'LOWER(a.article_keywords) ' . $this->db->sql_like_expression($needle) . ' OR ' .
                    'LOWER(a.article_text) ' . $this->db->sql_like_expression($needle) . ' OR ' .
                    'LOWER(c.category_name) ' . $this->db->sql_like_expression($needle) .
                ')';
            }

            $conditions[] = '(' . implode(' OR ', $term_conditions) . ')';
        }

        $sql = 'SELECT a.*, c.category_name, c.category_desc, c.forum_id AS category_forum_id, c.department_key AS category_department_key, c.sort_order AS category_sort_order
            FROM ' . $this->articles_table() . ' a
            LEFT JOIN ' . $this->categories_table() . ' c
                ON c.category_id = a.category_id
            WHERE ' . implode("
                AND ", $conditions) . '
            ORDER BY c.sort_order ASC, c.category_name ASC, a.sort_order ASC, a.article_views DESC, a.article_title ASC, a.article_id ASC';

        $result = $this->db->sql_query($sql);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            if ($keywords !== '')
            {
                $score = $this->search_score_article($row, $keywords);
                if ($score <= 0)
                {
                    continue;
                }

                $row['search_score'] = $score;
                $row['search_snippet'] = $this->build_search_snippet($row, $keywords, 180);
            }

            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        if ($keywords !== '')
        {
            usort($rows, function ($a, $b) {
                $score_a = (int) ($a['search_score'] ?? 0);
                $score_b = (int) ($b['search_score'] ?? 0);
                if ($score_a !== $score_b)
                {
                    return ($score_a > $score_b) ? -1 : 1;
                }

                $views_a = (int) ($a['article_views'] ?? 0);
                $views_b = (int) ($b['article_views'] ?? 0);
                if ($views_a !== $views_b)
                {
                    return ($views_a > $views_b) ? -1 : 1;
                }

                $updated_a = (int) ($a['updated_time'] ?? 0);
                $updated_b = (int) ($b['updated_time'] ?? 0);
                if ($updated_a !== $updated_b)
                {
                    return ($updated_a > $updated_b) ? -1 : 1;
                }

                return strcasecmp((string) ($a['article_title'] ?? ''), (string) ($b['article_title'] ?? ''));
            });
        }

        if ((int) $limit > 0)
        {
            $rows = array_slice($rows, 0, (int) $limit);
        }

        return $rows;
    }



    public function fetch_popular_articles($limit = 6)
    {
        $sql = 'SELECT a.*, c.category_name, c.sort_order AS category_sort_order
            FROM ' . $this->articles_table() . ' a
            LEFT JOIN ' . $this->categories_table() . ' c
                ON c.category_id = a.category_id
            WHERE a.article_enabled = 1
                AND (c.category_enabled = 1 OR c.category_enabled IS NULL)
            ORDER BY a.article_views DESC, a.article_helpful_yes DESC, a.updated_time DESC, a.article_title ASC, a.article_id ASC';
        $result = $this->db->sql_query_limit($sql, max(1, (int) $limit));

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    public function fetch_recent_articles($limit = 6)
    {
        $sql = 'SELECT a.*, c.category_name, c.sort_order AS category_sort_order
            FROM ' . $this->articles_table() . ' a
            LEFT JOIN ' . $this->categories_table() . ' c
                ON c.category_id = a.category_id
            WHERE a.article_enabled = 1
                AND (c.category_enabled = 1 OR c.category_enabled IS NULL)
            ORDER BY a.updated_time DESC, a.created_time DESC, a.article_title ASC, a.article_id ASC';
        $result = $this->db->sql_query_limit($sql, max(1, (int) $limit));

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }


    public function fetch_helpful_articles($limit = 6)
    {
        $sql = 'SELECT a.*, c.category_name, c.sort_order AS category_sort_order
            FROM ' . $this->articles_table() . ' a
            LEFT JOIN ' . $this->categories_table() . ' c
                ON c.category_id = a.category_id
            WHERE a.article_enabled = 1
                AND (c.category_enabled = 1 OR c.category_enabled IS NULL)
            ORDER BY (a.article_helpful_yes - a.article_helpful_no) DESC, a.article_helpful_yes DESC, a.article_views DESC, a.updated_time DESC, a.article_title ASC, a.article_id ASC';
        $result = $this->db->sql_query_limit($sql, max(1, (int) $limit));

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    public function helpful_score(array $row)
    {
        return (int) ($row['article_helpful_yes'] ?? 0) - (int) ($row['article_helpful_no'] ?? 0);
    }


    public function extract_keywords($value, $limit = 0)
    {
        $value = trim((string) $value);
        if ($value === '')
        {
            return [];
        }

        $raw_parts = preg_split('/[,;

	]+/u', $value);
        $keywords = [];
        $seen = [];

        foreach ((array) $raw_parts as $part)
        {
            $part = trim((string) $part);
            if ($part === '')
            {
                continue;
            }

            $normalized = mb_strtolower($part);
            if (isset($seen[$normalized]))
            {
                continue;
            }

            $seen[$normalized] = true;
            $keywords[] = $part;

            if ((int) $limit > 0 && count($keywords) >= (int) $limit)
            {
                break;
            }
        }

        return $keywords;
    }

    public function collect_keyword_counts(array $articles, $limit = 12)
    {
        $counts = [];

        foreach ($articles as $row)
        {
            $keywords = $this->extract_keywords((string) ($row['article_keywords'] ?? ''));
            if (empty($keywords))
            {
                continue;
            }

            $weight = 1;
            $weight += min(4, max(0, (int) floor(((int) ($row['article_views'] ?? 0)) / 25)));
            $weight += min(3, max(0, $this->helpful_score($row)));

            foreach ($keywords as $keyword)
            {
                $normalized = mb_strtolower($keyword);
                if (!isset($counts[$normalized]))
                {
                    $counts[$normalized] = [
                        'keyword' => $keyword,
                        'count' => 0,
                    ];
                }

                $counts[$normalized]['count'] += $weight;
            }
        }

        $rows = array_values($counts);
        usort($rows, function ($left, $right) {
            if ((int) $left['count'] === (int) $right['count'])
            {
                return strcasecmp((string) $left['keyword'], (string) $right['keyword']);
            }

            return ((int) $left['count'] > (int) $right['count']) ? -1 : 1;
        });

        if ((int) $limit > 0)
        {
            $rows = array_slice($rows, 0, (int) $limit);
        }

        return $rows;
    }

    public function fetch_article_neighbors($article_id, $category_id)
    {
        $article_id = (int) $article_id;
        $category_id = (int) $category_id;
        if ($article_id <= 0 || $category_id <= 0)
        {
            return [
                'previous' => [],
                'next' => [],
            ];
        }

        $articles = $this->fetch_articles_by_category($category_id, true);
        $previous = [];
        $next = [];
        $total = count($articles);
        for ($i = 0; $i < $total; $i++)
        {
            if ((int) ($articles[$i]['article_id'] ?? 0) !== $article_id)
            {
                continue;
            }

            if ($i > 0)
            {
                $previous = $articles[$i - 1];
            }
            if ($i + 1 < $total)
            {
                $next = $articles[$i + 1];
            }
            break;
        }

        return [
            'previous' => $previous,
            'next' => $next,
        ];
    }

    public function fetch_related_articles(array $article, $limit = 4)
    {
        $category_id = (int) ($article['category_id'] ?? 0);
        $article_id = (int) ($article['article_id'] ?? 0);
        if ($category_id <= 0 || $article_id <= 0)
        {
            return [];
        }

        $candidates = $this->fetch_articles_by_category($category_id, true);
        if (empty($candidates))
        {
            return [];
        }

        $needles = $this->article_terms_for_matching($article);
        $scored = [];
        foreach ($candidates as $candidate)
        {
            if ((int) ($candidate['article_id'] ?? 0) === $article_id)
            {
                continue;
            }

            $haystack = implode(' ', [
                (string) ($candidate['article_title'] ?? ''),
                (string) ($candidate['article_summary'] ?? ''),
                (string) ($candidate['article_keywords'] ?? ''),
            ]);
            $haystack = mb_strtolower($haystack);
            $score = 0;
            foreach ($needles as $term)
            {
                if ($term !== '' && mb_strpos($haystack, $term) !== false)
                {
                    $score += 10;
                }
            }

            $score += max(0, 6 - (int) ($candidate['sort_order'] ?? 0) / 10);
            $score += min(5, (int) floor(((int) ($candidate['article_views'] ?? 0)) / 10));
            $scored[] = [
                'score' => $score,
                'row' => $candidate,
            ];
        }

        usort($scored, function ($left, $right) {
            if ($left['score'] === $right['score'])
            {
                $l = (int) ($left['row']['sort_order'] ?? 0);
                $r = (int) ($right['row']['sort_order'] ?? 0);
                if ($l === $r)
                {
                    return strcasecmp((string) ($left['row']['article_title'] ?? ''), (string) ($right['row']['article_title'] ?? ''));
                }
                return $l <=> $r;
            }
            return $right['score'] <=> $left['score'];
        });

        $rows = [];
        foreach ($scored as $item)
        {
            $rows[] = $item['row'];
            if (count($rows) >= max(1, (int) $limit))
            {
                break;
            }
        }

        return $rows;
    }

    protected function article_terms_for_matching(array $article)
    {
        $source = implode(' ', [
            (string) ($article['article_title'] ?? ''),
            (string) ($article['article_summary'] ?? ''),
            (string) ($article['article_keywords'] ?? ''),
            (string) ($article['category_name'] ?? ''),
        ]);
        $source = mb_strtolower($source);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $source);
        $parts = array_values(array_filter(array_unique((array) $parts), function ($term) {
            return mb_strlen($term) >= 4;
        }));

        return array_slice($parts, 0, 12);
    }

    public function fetch_category_admin($category_id)
    {
        $sql = 'SELECT *
            FROM ' . $this->categories_table() . '
            WHERE category_id = ' . (int) $category_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ?: [];
    }

    public function fetch_article_admin($article_id)
    {
        $sql = 'SELECT *
            FROM ' . $this->articles_table() . '
            WHERE article_id = ' . (int) $article_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return $row ?: [];
    }

    public function move_category($category_id, $direction)
    {
        $category_id = (int) $category_id;
        $direction = (string) $direction;
        if ($category_id <= 0 || !in_array($direction, ['up', 'down'], true))
        {
            return false;
        }

        $this->normalize_category_sort_order();
        $categories = $this->fetch_all_categories_admin();
        $index = null;
        foreach ($categories as $position => $row)
        {
            if ((int) $row['category_id'] === $category_id)
            {
                $index = $position;
                break;
            }
        }

        if ($index === null)
        {
            return false;
        }

        $target_index = ($direction === 'up') ? ($index - 1) : ($index + 1);
        if (!isset($categories[$target_index]))
        {
            return false;
        }

        $current = $categories[$index];
        $target = $categories[$target_index];

        $sql = 'UPDATE ' . $this->categories_table() . '
            SET sort_order = ' . (int) $target['sort_order'] . ', updated_time = ' . time() . '
            WHERE category_id = ' . (int) $current['category_id'];
        $this->db->sql_query($sql);

        $sql = 'UPDATE ' . $this->categories_table() . '
            SET sort_order = ' . (int) $current['sort_order'] . ', updated_time = ' . time() . '
            WHERE category_id = ' . (int) $target['category_id'];
        $this->db->sql_query($sql);

        return true;
    }

    public function move_article($article_id, $direction)
    {
        $article_id = (int) $article_id;
        $direction = (string) $direction;
        if ($article_id <= 0 || !in_array($direction, ['up', 'down'], true))
        {
            return false;
        }

        $article = $this->fetch_article_admin($article_id);
        if (empty($article))
        {
            return false;
        }

        $category_id = (int) ($article['category_id'] ?? 0);
        if ($category_id <= 0)
        {
            return false;
        }

        $this->normalize_article_sort_order($category_id);
        $articles = $this->fetch_articles_for_sort_admin($category_id);
        $index = null;
        foreach ($articles as $position => $row)
        {
            if ((int) $row['article_id'] === $article_id)
            {
                $index = $position;
                break;
            }
        }

        if ($index === null)
        {
            return false;
        }

        $target_index = ($direction === 'up') ? ($index - 1) : ($index + 1);
        if (!isset($articles[$target_index]))
        {
            return false;
        }

        $current = $articles[$index];
        $target = $articles[$target_index];

        $sql = 'UPDATE ' . $this->articles_table() . '
            SET sort_order = ' . (int) $target['sort_order'] . ', updated_time = ' . time() . '
            WHERE article_id = ' . (int) $current['article_id'];
        $this->db->sql_query($sql);

        $sql = 'UPDATE ' . $this->articles_table() . '
            SET sort_order = ' . (int) $current['sort_order'] . ', updated_time = ' . time() . '
            WHERE article_id = ' . (int) $target['article_id'];
        $this->db->sql_query($sql);

        return true;
    }

    protected function normalize_category_sort_order()
    {
        $sql = 'SELECT category_id
            FROM ' . $this->categories_table() . '
            ORDER BY sort_order ASC, category_name ASC, category_id ASC';
        $result = $this->db->sql_query($sql);

        $ids = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $ids[] = (int) $row['category_id'];
        }
        $this->db->sql_freeresult($result);

        $sort_order = 10;
        foreach ($ids as $id)
        {
            $sql = 'UPDATE ' . $this->categories_table() . '
                SET sort_order = ' . $sort_order . '
                WHERE category_id = ' . $id;
            $this->db->sql_query($sql);
            $sort_order += 10;
        }
    }

    protected function fetch_articles_for_sort_admin($category_id)
    {
        $sql = 'SELECT article_id, sort_order
            FROM ' . $this->articles_table() . '
            WHERE category_id = ' . (int) $category_id . '
            ORDER BY sort_order ASC, article_title ASC, article_id ASC';
        $result = $this->db->sql_query($sql);

        $rows = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    protected function normalize_article_sort_order($category_id)
    {
        $category_id = (int) $category_id;
        if ($category_id <= 0)
        {
            return;
        }

        $rows = $this->fetch_articles_for_sort_admin($category_id);
        $sort_order = 10;
        foreach ($rows as $row)
        {
            $sql = 'UPDATE ' . $this->articles_table() . '
                SET sort_order = ' . $sort_order . '
                WHERE article_id = ' . (int) $row['article_id'];
            $this->db->sql_query($sql);
            $sort_order += 10;
        }
    }

    protected function next_article_sort_order($category_id)
    {
        $sql = 'SELECT MAX(sort_order) AS max_sort
            FROM ' . $this->articles_table() . '
            WHERE category_id = ' . (int) $category_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return ((int) ($row['max_sort'] ?? 0)) + 10;
    }

    public function save_category(array $data, $category_id = 0)
    {
        $sort_order = (int) ($data['sort_order'] ?? 0);
        if ($category_id <= 0 && $sort_order <= 0)
        {
            $sort_order = $this->next_category_sort_order();
        }

        $sql_data = [
            'category_name'    => (string) ($data['category_name'] ?? ''),
            'category_desc'    => (string) ($data['category_desc'] ?? ''),
            'forum_id'         => (int) ($data['forum_id'] ?? 0),
            'department_key'   => (string) ($data['department_key'] ?? ''),
            'sort_order'       => $sort_order,
            'category_enabled' => !empty($data['category_enabled']) ? 1 : 0,
            'updated_time'     => time(),
        ];

        if ($category_id > 0)
        {
            $sql = 'UPDATE ' . $this->categories_table() . '
                SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
                WHERE category_id = ' . (int) $category_id;
            $this->db->sql_query($sql);
            return $category_id;
        }

        $sql_data['created_time'] = time();
        $sql = 'INSERT INTO ' . $this->categories_table() . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
        $this->db->sql_query($sql);

        return (int) $this->db->sql_nextid();
    }

    public function save_article(array $data, $article_id = 0)
    {
        $base_slug = trim((string) ($data['article_slug'] ?? ''));
        if ($base_slug === '')
        {
            $base_slug = (string) ($data['article_title'] ?? '');
        }

        $summary = trim((string) ($data['article_summary'] ?? ''));
        if ($summary === '')
        {
            $summary = $this->build_article_summary((string) ($data['article_text'] ?? ''));
        }

        $category_id = (int) ($data['category_id'] ?? 0);
        $sort_order = (int) ($data['sort_order'] ?? 0);
        if ($sort_order <= 0 && $category_id > 0)
        {
            $sort_order = $this->next_article_sort_order($category_id);
        }

        $sql_data = [
            'category_id'             => $category_id,
            'article_title'           => (string) ($data['article_title'] ?? ''),
            'article_slug'            => $this->ensure_unique_article_slug($base_slug, (int) $article_id),
            'article_summary'         => $summary,
            'article_text'            => (string) ($data['article_text'] ?? ''),
            'article_bbcode_uid'      => (string) ($data['article_bbcode_uid'] ?? ''),
            'article_bbcode_bitfield' => (string) ($data['article_bbcode_bitfield'] ?? ''),
            'article_bbcode_options'  => (int) ($data['article_bbcode_options'] ?? 7),
            'article_keywords'        => (string) ($data['article_keywords'] ?? ''),
            'forum_id'                => (int) ($data['forum_id'] ?? 0),
            'department_key'          => (string) ($data['department_key'] ?? ''),
            'sort_order'              => $sort_order,
            'article_enabled'         => !empty($data['article_enabled']) ? 1 : 0,
            'updated_time'            => time(),
        ];

        if ($article_id > 0)
        {
            $sql = 'UPDATE ' . $this->articles_table() . '
                SET ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
                WHERE article_id = ' . (int) $article_id;
            $this->db->sql_query($sql);
            return $article_id;
        }

        $sql_data['created_by'] = !empty($this->user->data['user_id']) ? (int) $this->user->data['user_id'] : 0;
        $sql_data['created_time'] = time();
        $sql = 'INSERT INTO ' . $this->articles_table() . ' ' . $this->db->sql_build_array('INSERT', $sql_data);
        $this->db->sql_query($sql);

        return (int) $this->db->sql_nextid();
    }

    public function delete_category($category_id)
    {
        $category_id = (int) $category_id;
        if ($category_id <= 0)
        {
            return;
        }

        $sql = 'DELETE FROM ' . $this->articles_table() . '
            WHERE category_id = ' . $category_id;
        $this->db->sql_query($sql);

        $sql = 'DELETE FROM ' . $this->categories_table() . '
            WHERE category_id = ' . $category_id;
        $this->db->sql_query($sql);
        $this->normalize_category_sort_order();
    }

    protected function next_category_sort_order()
    {
        $sql = 'SELECT MAX(sort_order) AS max_sort
            FROM ' . $this->categories_table();
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return ((int) ($row['max_sort'] ?? 0)) + 10;
    }

    public function delete_article($article_id)
    {
        $article_id = (int) $article_id;
        if ($article_id <= 0)
        {
            return;
        }

        $article = $this->fetch_article_admin($article_id);
        $category_id = (int) ($article['category_id'] ?? 0);

        $sql = 'DELETE FROM ' . $this->articles_table() . '
            WHERE article_id = ' . $article_id;
        $this->db->sql_query($sql);

        if ($category_id > 0)
        {
            $this->normalize_article_sort_order($category_id);
        }
    }

    public function increment_article_view($article_id)
    {
        $sql = 'UPDATE ' . $this->articles_table() . '
            SET article_views = article_views + 1
            WHERE article_id = ' . (int) $article_id;
        $this->db->sql_query($sql);
    }

    public function vote_article($article_id, $vote)
    {
        $field = ($vote === 'yes') ? 'article_helpful_yes' : 'article_helpful_no';
        $sql = 'UPDATE ' . $this->articles_table() . '
            SET ' . $field . ' = ' . $field . ' + 1
            WHERE article_id = ' . (int) $article_id;
        $this->db->sql_query($sql);
    }

    public function parse_for_display(array $article)
    {
        if (!function_exists('generate_text_for_display'))
        {
            include_once __DIR__ . '/../../../../includes/functions_content.php';
        }

        return generate_text_for_display(
            (string) ($article['article_text'] ?? ''),
            (string) ($article['article_bbcode_uid'] ?? ''),
            (string) ($article['article_bbcode_bitfield'] ?? ''),
            (int) ($article['article_bbcode_options'] ?? 7)
        );
    }

    public function build_topic_suggestions($forum_id, $topic_title, $limit = 3, $department_key = "")
    {
        $forum_id = (int) $forum_id;
        $limit = max(1, (int) $limit);
        $topic_title = trim((string) $topic_title);
        $department_key = trim((string) $department_key);
        if ($topic_title === '')
        {
            return [];
        }

        $keywords = preg_split('/\s+/u', mb_strtolower($topic_title));
        $keywords = array_values(array_filter(array_unique($keywords), function ($word) {
            return mb_strlen($word) >= 4;
        }));

        $sql = 'SELECT a.*, c.category_name, c.forum_id AS category_forum_id, c.department_key AS category_department_key, c.category_enabled
            FROM ' . $this->articles_table() . ' a
            LEFT JOIN ' . $this->categories_table() . ' c
                ON c.category_id = a.category_id
            WHERE a.article_enabled = 1
                AND (c.category_enabled = 1 OR c.category_enabled IS NULL)
            ORDER BY a.sort_order ASC, a.article_views DESC, a.article_title ASC';
        $result = $this->db->sql_query($sql);

        $matches = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $article_forum_id = (int) ($row['forum_id'] ?? 0);
            $category_forum_id = (int) ($row['category_forum_id'] ?? 0);
            $article_department_key = trim((string) ($row['department_key'] ?? ''));
            $category_department_key = trim((string) ($row['category_department_key'] ?? ''));
            if ($forum_id > 0)
            {
                if ($article_forum_id > 0 && $article_forum_id !== $forum_id)
                {
                    continue;
                }

                if ($article_forum_id === 0 && $category_forum_id > 0 && $category_forum_id !== $forum_id)
                {
                    continue;
                }
            }

            $title = mb_strtolower((string) ($row['article_title'] ?? ''));
            $summary = mb_strtolower((string) ($row['article_summary'] ?? ''));
            $keywords_text = mb_strtolower((string) ($row['article_keywords'] ?? ''));
            $category_name = mb_strtolower((string) ($row['category_name'] ?? ''));
            $score = 0;

            if ($article_forum_id > 0 && $article_forum_id === $forum_id)
            {
                $score += 20;
            }
            else if ($category_forum_id > 0 && $category_forum_id === $forum_id)
            {
                $score += 12;
            }
            else if ($article_forum_id === 0 && $category_forum_id === 0)
            {
                $score += 2;
            }

            if ($category_name !== '' && mb_strpos(mb_strtolower($topic_title), $category_name) !== false)
            {
                $score += 8;
            }

            if ($department_key !== '')
            {
                if ($article_department_key !== '' && mb_strtolower($article_department_key) === mb_strtolower($department_key))
                {
                    $score += 14;
                }
                else if ($article_department_key === '' && $category_department_key !== '' && mb_strtolower($category_department_key) === mb_strtolower($department_key))
                {
                    $score += 9;
                }
            }

            foreach ($keywords as $word)
            {
                if (mb_strpos($title, $word) !== false)
                {
                    $score += 12;
                }
                else if (mb_strpos($summary, $word) !== false || mb_strpos($keywords_text, $word) !== false)
                {
                    $score += 6;
                }
                else if ($category_name !== '' && mb_strpos($category_name, $word) !== false)
                {
                    $score += 5;
                }
                else if ($department_key !== '' && (($article_department_key !== '' && mb_strpos(mb_strtolower($article_department_key), $word) !== false) || ($category_department_key !== '' && mb_strpos(mb_strtolower($category_department_key), $word) !== false)))
                {
                    $score += 4;
                }
            }

            if ($score <= 0)
            {
                continue;
            }

            $row['match_score'] = $score;
            $matches[] = $row;
        }
        $this->db->sql_freeresult($result);

        usort($matches, function ($a, $b) {
            if ((int) $a['match_score'] === (int) $b['match_score'])
            {
                if ((int) $a['article_views'] === (int) $b['article_views'])
                {
                    return strcasecmp((string) $a['article_title'], (string) $b['article_title']);
                }

                return ((int) $a['article_views'] > (int) $b['article_views']) ? -1 : 1;
            }

            return ((int) $a['match_score'] > (int) $b['match_score']) ? -1 : 1;
        });

        return array_slice($matches, 0, $limit);
    }


    protected function search_terms($keywords)
    {
        $keywords = mb_strtolower(trim((string) $keywords));
        if ($keywords === '')
        {
            return [];
        }

        $terms = preg_split('/\s+/u', $keywords);
        $terms = array_values(array_filter(array_unique($terms), function ($term) {
            return mb_strlen($term) >= 2;
        }));

        if (empty($terms))
        {
            $terms = [$keywords];
        }

        return $terms;
    }

    protected function search_score_article(array $row, $keywords)
    {
        $phrase = mb_strtolower(trim((string) $keywords));
        $terms = $this->search_terms($keywords);
        if ($phrase === '')
        {
            return 0;
        }

        $title = mb_strtolower((string) ($row['article_title'] ?? ''));
        $summary = mb_strtolower((string) ($row['article_summary'] ?? ''));
        $keyword_text = mb_strtolower((string) ($row['article_keywords'] ?? ''));
        $article_text = mb_strtolower($this->search_plain_text((string) ($row['article_text'] ?? '')));
        $category_name = mb_strtolower((string) ($row['category_name'] ?? ''));
        $score = 0;

        if ($title !== '' && mb_strpos($title, $phrase) !== false)
        {
            $score += 140;
        }
        if ($keyword_text !== '' && mb_strpos($keyword_text, $phrase) !== false)
        {
            $score += 80;
        }
        if ($summary !== '' && mb_strpos($summary, $phrase) !== false)
        {
            $score += 55;
        }
        if ($category_name !== '' && mb_strpos($category_name, $phrase) !== false)
        {
            $score += 45;
        }
        if ($article_text !== '' && mb_strpos($article_text, $phrase) !== false)
        {
            $score += 30;
        }

        foreach ($terms as $term)
        {
            if ($title !== '' && mb_strpos($title, $term) !== false)
            {
                $score += 28;
            }
            if ($keyword_text !== '' && mb_strpos($keyword_text, $term) !== false)
            {
                $score += 18;
            }
            if ($summary !== '' && mb_strpos($summary, $term) !== false)
            {
                $score += 12;
            }
            if ($category_name !== '' && mb_strpos($category_name, $term) !== false)
            {
                $score += 9;
            }
            if ($article_text !== '' && mb_strpos($article_text, $term) !== false)
            {
                $score += 4;
            }
        }

        $positive_feedback = max(0, (int) ($row['article_helpful_yes'] ?? 0) - (int) ($row['article_helpful_no'] ?? 0));
        $score += min(20, (int) floor(((int) ($row['article_views'] ?? 0)) / 25));
        $score += min(12, $positive_feedback);

        return $score;
    }

    public function search_plain_text($text)
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

    protected function build_search_snippet(array $row, $keywords, $limit = 180)
    {
        $summary = $this->search_plain_text((string) ($row['article_summary'] ?? ''));
        $article_text = $this->search_plain_text((string) ($row['article_text'] ?? ''));
        $haystack = ($summary !== '') ? $summary : $article_text;
        if ($haystack === '')
        {
            return '';
        }

        $terms = $this->search_terms($keywords);
        $position = false;
        foreach ($terms as $term)
        {
            $found = mb_stripos($haystack, $term);
            if ($found !== false)
            {
                $position = $found;
                break;
            }
        }

        $limit = max(100, (int) $limit);
        if ($position === false)
        {
            return $this->build_article_summary($haystack, $limit);
        }

        $start = max(0, (int) $position - (int) floor($limit / 3));
        $snippet = mb_substr($haystack, $start, $limit);
        if ($start > 0)
        {
            $space = mb_strpos($snippet, ' ');
            if ($space !== false && $space < 25)
            {
                $snippet = mb_substr($snippet, $space + 1);
            }
        }

        $snippet = trim((string) $snippet);
        if ($start > 0)
        {
            $snippet = '…' . ltrim($snippet);
        }
        if (($start + mb_strlen($snippet)) < mb_strlen($haystack))
        {
            $snippet = rtrim($snippet, " 	

 .,;:-") . '…';
        }

        return $snippet;
    }

    public function build_article_summary($text, $limit = 260)
    {
        $text = (string) $text;
        $text = preg_replace('/\[(?:\/?)[^\]]+\]/u', ' ', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', trim($text));
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

        return rtrim($summary, " \t\n\r\0\x0B.,;:-") . '…';
    }

    public function ensure_unique_article_slug($text, $article_id = 0)
    {
        $base_slug = $this->slugify($text, 'article');
        $slug = $base_slug;
        $suffix = 2;

        while ($this->article_slug_exists($slug, (int) $article_id))
        {
            $slug = $base_slug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function article_slug_exists($slug, $article_id = 0)
    {
        $slug = trim((string) $slug);
        if ($slug === '')
        {
            return false;
        }

        $sql = 'SELECT article_id
            FROM ' . $this->articles_table() . "\n            WHERE article_slug = '" . $this->db->sql_escape($slug) . "'";

        if ((int) $article_id > 0)
        {
            $sql .= ' AND article_id <> ' . (int) $article_id;
        }

        $result = $this->db->sql_query_limit($sql, 1);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return !empty($row);
    }


    public function build_category_scope_label(array $category)
    {
        $forum_id = (int) ($category['forum_id'] ?? 0);
        $department_key = trim((string) ($category['department_key'] ?? ''));

        if ($forum_id > 0 && $department_key !== '')
        {
            return sprintf($this->user->lang('HELPDESKKB_SCOPE_FORUM_DEPARTMENT'), $forum_id, $department_key);
        }

        if ($forum_id > 0)
        {
            return sprintf($this->user->lang('HELPDESKKB_SCOPE_FORUM'), $forum_id);
        }

        if ($department_key !== '')
        {
            return sprintf($this->user->lang('HELPDESKKB_SCOPE_DEPARTMENT'), $department_key);
        }

        return $this->user->lang('HELPDESKKB_SCOPE_GLOBAL');
    }

    public function build_category_state_label($article_count)
    {
        return ((int) $article_count > 0)
            ? $this->user->lang('HELPDESKKB_CATEGORY_ACTIVE')
            : $this->user->lang('HELPDESKKB_CATEGORY_EMPTY');
    }

    public function build_category_slug($text)
    {
        return $this->slugify($text, 'categoria');
    }

    public function slugify($text, $fallback = 'article')
    {
        $text = trim((string) $text);
        $fallback = trim((string) $fallback);
        if ($fallback === '')
        {
            $fallback = 'item';
        }

        if (function_exists('iconv'))
        {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false)
            {
                $text = $converted;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim((string) $text, '-');

        return ($text !== '') ? $text : $fallback;
    }
}
