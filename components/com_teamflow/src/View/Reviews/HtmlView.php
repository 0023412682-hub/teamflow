<?php

namespace Joomla\Component\Teamflow\Site\View\Reviews;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $submissions = [];
    public $reviewHistory = [];
    public $memberStats = [];

    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException('Bạn chưa đăng nhập.');
        }

        $leader_user_id = (int) $user->id;

        $db = Factory::getContainer()
            ->get(\Joomla\Database\DatabaseInterface::class);

        // TEAMFLOW_REVIEWS_ACCESS_GUARD
        // Trang Reviews chi danh cho tai khoan dang quan ly it nhat mot nhom hoat dong.
        // Kiem tra truoc khi truy van bai nop, lich su va thong ke.
        $accessQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__teamflow_groups', 'g'))
            ->where($db->quoteName('g.leader_user_id') . ' = ' . $leader_user_id)
            ->where($db->quoteName('g.is_active') . ' = 1');

        $db->setQuery($accessQuery);

        if ((int) $db->loadResult() === 0) {
            throw new \RuntimeException(
                'Bạn không có quyền truy cập chức năng duyệt bài. Chức năng này chỉ dành cho Nhóm trưởng.',
                403
            );
        }

        // 1. Danh sách bài nộp mới nhất đang chờ duyệt.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('s.id'),
                $db->quoteName('s.assignment_id'),
                $db->quoteName('s.attempt_no'),
                $db->quoteName('s.file_path'),
                $db->quoteName('s.link_url'),
                $db->quoteName('s.submitted_at'),
                $db->quoteName('s.review_status'),
                $db->quoteName('a.title', 'assignment_title'),
                $db->quoteName('u.name', 'member_name')
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
            ->join(
                'INNER',
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id')
                . ' = ' . $db->quoteName('a.assignee_user_id')
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
            ->order(
                $db->quoteName('s.submitted_at') . ' ASC'
            );

        $db->setQuery($query);
        $this->submissions = $db->loadObjectList();

        // 2. Lịch sử duyệt bài.
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
            ->join(
                'LEFT',
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id')
                . ' = ' . $db->quoteName('a.assignee_user_id')
            )
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            )
            ->where(
                $db->quoteName('s.review_status') . ' IN ('
                . $db->quote('approved') . ','
                . $db->quote('revision_required') . ')'
            )
            ->order([
                $db->quoteName('s.reviewed_at') . ' DESC',
                $db->quoteName('s.id') . ' DESC'
            ]);

        $db->setQuery($historyQuery);
        $this->reviewHistory = $db->loadObjectList();

        // 3. Thong ke theo thanh vien: dem nhiem vu rieng va luot duyet rieng.
        // Chi lay cac nhom dang hoat dong do tai khoan hien tai lam nhom truong.
        $statsQuery = $db->getQuery(true)
            ->select([
                $db->quoteName('u.id', 'member_id'),
                $db->quoteName('u.name', 'member_name'),
                'COUNT(DISTINCT ' . $db->quoteName('a.id') . ') AS ' . $db->quoteName('assignment_count'),
                'SUM(CASE WHEN ' . $db->quoteName('s.review_status')
                    . ' = ' . $db->quote('approved') . ' THEN 1 ELSE 0 END) AS '
                    . $db->quoteName('approved_count'),
                'SUM(CASE WHEN ' . $db->quoteName('s.review_status')
                    . ' = ' . $db->quote('revision_required') . ' THEN 1 ELSE 0 END) AS '
                    . $db->quoteName('revision_count')
            ])
            ->from($db->quoteName('#__teamflow_assignments', 'a'))
            ->join('INNER', $db->quoteName('#__teamflow_task_groups', 'tg')
                . ' ON ' . $db->quoteName('tg.id') . ' = ' . $db->quoteName('a.task_group_id'))
            ->join('INNER', $db->quoteName('#__teamflow_groups', 'g')
                . ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('tg.group_id'))
            ->join('INNER', $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.assignee_user_id'))
            ->join('LEFT', $db->quoteName('#__teamflow_submissions', 's')
                . ' ON ' . $db->quoteName('s.assignment_id') . ' = ' . $db->quoteName('a.id')
                . ' AND ' . $db->quoteName('s.review_status') . ' IN ('
                . $db->quote('approved') . ', ' . $db->quote('revision_required') . ')')
            ->where($db->quoteName('g.leader_user_id') . ' = ' . $leader_user_id)
            ->where($db->quoteName('g.is_active') . ' = 1')
            ->group([$db->quoteName('u.id'), $db->quoteName('u.name')])
            ->order($db->quoteName('u.name') . ' ASC');

        $db->setQuery($statsQuery);
        $this->memberStats = $db->loadObjectList();

        parent::display($tpl);
    }
}