<?php
/**
 * @copyright (c) 2026 MundoPHPBB
 * @license GNU General Public License, version 2 (GPL-2.0)
 */

namespace mundophpbb\helpdeskkb\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    protected $config;
    protected $helper;
    protected $template;
    protected $user;
    protected $manager;

    public function __construct(
        \phpbb\config\config $config,
        \phpbb\controller\helper $helper,
        \phpbb\template\template $template,
        \phpbb\user $user,
        \mundophpbb\helpdeskkb\service\kb_manager $manager
    ) {
        $this->config = $config;
        $this->helper = $helper;
        $this->template = $template;
        $this->user = $user;
        $this->manager = $manager;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.user_setup' => 'load_language',
            'core.page_header' => 'assign_nav_link',
            'core.viewtopic_assign_template_vars_before' => 'assign_topic_suggestions',
            'core.posting_modify_template_vars' => 'assign_posting_suggestions',
        ];
    }

    public function load_language($event)
    {
        $lang_set_ext = $event['lang_set_ext'];
        $lang_set_ext[] = [
            'ext_name' => 'mundophpbb/helpdeskkb',
            'lang_set' => 'common',
        ];
        $event['lang_set_ext'] = $lang_set_ext;
    }

    public function assign_nav_link($event)
    {
        $this->template->assign_vars([
            'S_HELPDESKKB_SHOW_NAV' => !empty($this->config['helpdeskkb_navbar']) && $this->manager->extension_enabled(),
            'U_HELPDESKKB_INDEX' => $this->helper->route('mundophpbb_helpdeskkb_index_controller'),
        ]);
    }

    public function assign_topic_suggestions($event)
    {
        if (!$this->manager->helpdesk_integration_enabled() || empty($this->config['helpdeskkb_suggestions_enabled']))
        {
            return;
        }

        $forum_id = (int) $event['forum_id'];
        $topic_data = $event['topic_data'];
        $topic_title = isset($topic_data['topic_title']) ? (string) $topic_data['topic_title'] : '';
        if ($topic_title === '')
        {
            return;
        }

        $limit = max(1, (int) $this->config['helpdeskkb_suggestions_limit']);
        $suggestions = $this->manager->build_topic_suggestions($forum_id, $topic_title, $limit);
        if (empty($suggestions))
        {
            return;
        }

        foreach ($suggestions as $suggestion)
        {
            $this->template->assign_block_vars('helpdeskkb_topic_suggestions', [
                'ARTICLE_ID' => (int) $suggestion['article_id'],
                'ARTICLE_TITLE' => (string) $suggestion['article_title'],
                'ARTICLE_SUMMARY' => (string) $suggestion['article_summary'],
                'U_ARTICLE' => $this->helper->route('mundophpbb_helpdeskkb_article_slug_controller', [
                    'article_id' => (int) $suggestion['article_id'],
                    'slug' => (string) ($suggestion['article_slug'] ?? ''),
                ]),
            ]);
        }

        $this->template->assign_var('S_HELPDESKKB_TOPIC_SUGGESTIONS', true);
    }


    public function assign_posting_suggestions($event)
    {
        if (!$this->manager->helpdesk_integration_enabled() || empty($this->config['helpdeskkb_suggestions_enabled']))
        {
            return;
        }

        $mode = isset($event['mode']) ? (string) $event['mode'] : '';
        if (!in_array($mode, ['post', 'edit'], true))
        {
            return;
        }

        $forum_id = isset($event['forum_id']) ? (int) $event['forum_id'] : 0;
        if ($forum_id <= 0)
        {
            return;
        }

        $this->template->assign_vars([
            'S_HELPDESKKB_POSTING_SUGGESTIONS' => true,
            'HELPDESKKB_POSTING_MIN_CHARS' => 4,
            'HELPDESKKB_POSTING_SUGGEST_URL' => $this->helper->route('mundophpbb_helpdeskkb_posting_suggestions_controller', [
                'forum_id' => $forum_id,
            ]),
        ]);
    }

}
