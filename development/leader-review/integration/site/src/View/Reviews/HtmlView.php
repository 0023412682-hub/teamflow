<?php

namespace Joomla\Component\Teamflow\Site\View\Reviews;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $submissions = [];
    public $reviewHistory = [];

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
            ->where(
                'NOT EXISTS (SELECT 1 FROM '
                . $db->quoteName('#__teamflow_submissions', 'newer')
                . ' WHERE newer.assignment_id = s.assignment_id'
                . ' AND (newer.attempt_no > s.attempt_no'
                . ' OR (newer.attempt_no = s.attempt_no'
                . ' AND newer.id > s.id)))'
            )
            ->order($db->quoteName('s.submitted_at') . ' ASC');

        $db->setQuery($query);
        $this->submissions = $db->loadObjectList();

        // Lich su cac lan nop da duoc duyet cua nhom truong.
        $historyQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('s.id'),
                $db->quoteName('s.attempt_no'),
                $db->quoteName('s.review_status'),
                $db->quoteName('s.review_comment'),
                $db->quoteName('s.reviewed_at'),
                $db->quoteName('a.title', 'assignment_title'),
                $db->quoteName('u.name', 'member_name')
            ])
            ->from($db->quoteName('#__teamflow_submissions', 's'))
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_assignments', 'a')
                . ' ON a.id = s.assignment_id'
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_task_groups', 'tg')
                . ' ON tg.id = a.task_group_id'
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_groups', 'g')
                . ' ON g.id = tg.group_id'
            )
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'u')
                . ' ON u.id = a.assignee_user_id'
            )
            ->where('g.leader_user_id = ' . $leader_user_id)
            ->where('g.is_active = 1')
            ->where(
                's.review_status IN ('
                . $db->quote('approved') . ', '
                . $db->quote('revision_required') . ')'
            )
            ->order([
                's.reviewed_at DESC',
                's.id DESC'
            ]);

        $db->setQuery($historyQuery);
        $this->reviewHistory = $db->loadObjectList();
        parent::display($tpl);
    }
}
