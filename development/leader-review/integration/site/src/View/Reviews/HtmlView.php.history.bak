<?php

namespace Joomla\Component\Teamflow\Site\View\Reviews;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $submissions = [];

    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException('Bạn chưa đăng nhập.');
        }

        $leader_user_id = (int) $user->id;
        $db = Factory::getContainer()
        ->get(\Joomla\Database\DatabaseInterface::class);

        // Lấy bài nộp đang chờ duyệt thuộc nhóm trưởng.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('s.id'),
                $db->quoteName('s.assignment_id'),
                $db->quoteName('s.attempt_no'),
                $db->quoteName('s.file_path'),
                $db->quoteName('s.link_url'),
                $db->quoteName('s.submitted_at'),
                $db->quoteName('s.review_status')
            ])
            ->from($db->quoteName('#__teamflow_submissions', 's'))
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_assignments', 'a')
                . ' ON ' . $db->quoteName('a.id')
                . ' = ' . $db->quoteName('s.assignment_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_task_groups', 'tg')
                . ' ON ' . $db->quoteName('tg.id')
                . ' = ' . $db->quoteName('a.task_group_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_groups', 'g')
                . ' ON ' . $db->quoteName('g.id')
                . ' = ' . $db->quoteName('tg.group_id')
            )
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            )
            ->where(
                $db->quoteName('s.review_status')
                . ' = ' . $db->quote('pending')
            )
            ->order($db->quoteName('s.submitted_at') . ' ASC');

        $db->setQuery($query);
        $this->submissions = $db->loadObjectList();

        parent::display($tpl);
    }
}
