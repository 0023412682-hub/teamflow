<?php

namespace Joomla\Component\Teamflow\Site\View\Assignments;
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $task_groups = [];
    public $members = [];
    public $assignments = [];

    public $assignmentStats = [
        'total' => 0,
        'approved' => 0,
        'pending' => 0,
        'revision_required' => 0,
        'in_progress' => 0
    ];

    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException(
                'Bạn chưa đăng nhập.',
                403
            );
        }

        $leader_user_id = (int) $user->id;

        $db = Factory::getContainer()
            ->get(\Joomla\Database\DatabaseInterface::class);

        // Phan quyen: chi nguoi quan ly it nhat mot nhom dang hoat dong
        // moi duoc xem trang Phan cong.
        $permissionQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__teamflow_groups'))
            ->where($db->quoteName('leader_user_id') . ' = ' . $leader_user_id)
            ->where($db->quoteName('is_active') . ' = 1');
        $db->setQuery($permissionQuery);

        if ((int) $db->loadResult() === 0) {
            throw new \RuntimeException(
                'Bạn không có quyền truy cập chức năng Phân công. Chức năng này chỉ dành cho Nhóm trưởng.',
                403
            );
        }

        // =========================================
        // 1. LAY NHIEM VU NHOM
        // =========================================

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('tg.id'),
                $db->quoteName('t.title')
            ])
            ->from(
                $db->quoteName('#__teamflow_task_groups', 'tg')
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_tasks', 't')
                . ' ON ' . $db->quoteName('t.id')
                . ' = ' . $db->quoteName('tg.task_id')
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
            );

        $db->setQuery($query);

        $this->task_groups = $db->loadObjectList();

        // =========================================
        // 2. LAY THANH VIEN CUA NHOM TRUONG
        // =========================================

        $query = $db->getQuery(true)
            ->select(
                'DISTINCT ' . $db->quoteName('u.id')
            )
            ->select(
                $db->quoteName('u.name')
            )
            ->from(
                $db->quoteName('#__teamflow_group_members', 'gm')
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_groups', 'g')
                . ' ON ' . $db->quoteName('g.id')
                . ' = ' . $db->quoteName('gm.group_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id')
                . ' = ' . $db->quoteName('gm.user_id')
            )
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            )
            ->order(
                $db->quoteName('u.name') . ' ASC'
            );

        $db->setQuery($query);

        $this->members = $db->loadObjectList();

        // =========================================
        // 3. LAY NHIEM VU VA LAN NOP MOI NHAT
        // =========================================

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('a.id', 'id'),
                $db->quoteName('a.task_group_id', 'task_group_id'),
                $db->quoteName('a.assignee_user_id', 'assignee_user_id'),
                $db->quoteName('u.name', 'member_name'),
                $db->quoteName('a.title', 'title'),
                $db->quoteName('a.description', 'description'),
                $db->quoteName('a.due_at', 'due_at'),
                $db->quoteName('s.id', 'submission_id'),
                $db->quoteName('s.attempt_no', 'attempt_no'),
                $db->quoteName('s.review_status', 'review_status')
            ])
            ->from(
                $db->quoteName('#__teamflow_assignments', 'a')
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
            ->join(
                'LEFT',
                $db->quoteName('#__teamflow_submissions', 's')
                . ' ON ' . $db->quoteName('s.id')
                . ' = (SELECT '
                . $db->quoteName('s2.id')
                . ' FROM '
                . $db->quoteName('#__teamflow_submissions', 's2')
                . ' WHERE '
                . $db->quoteName('s2.assignment_id')
                . ' = ' . $db->quoteName('a.id')
                . ' ORDER BY '
                . $db->quoteName('s2.attempt_no') . ' DESC, '
                . $db->quoteName('s2.id') . ' DESC'
                . ' LIMIT 1)'
            )
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            )
            ->order(
                $db->quoteName('a.id') . ' DESC'
            );

        $db->setQuery($query);

        $this->assignments = $db->loadObjectList();

        // =========================================
        // 4. XAC DINH TRANG THAI VA THONG KE
        // =========================================

        $this->assignmentStats = [
            'total' => count($this->assignments),
            'approved' => 0,
            'pending' => 0,
            'revision_required' => 0,
            'in_progress' => 0
        ];

        foreach ($this->assignments as $assignment) {

            $reviewStatus = (string) (
                $assignment->review_status ?? ''
            );

            switch ($reviewStatus) {
                case 'approved':
                    $assignment->status = 'approved';
                    $assignment->status_label = 'Đã hoàn thành';

                    $this->assignmentStats['approved']++;
                    break;

                case 'pending':
                    $assignment->status = 'pending';
                    $assignment->status_label = 'Chờ duyệt';

                    $this->assignmentStats['pending']++;
                    break;

                case 'revision_required':
                    $assignment->status = 'revision_required';
                    $assignment->status_label = 'Cần chỉnh sửa';

                    $this->assignmentStats['revision_required']++;
                    break;

                default:
                    $assignment->status = 'in_progress';
                    $assignment->status_label = 'Đang thực hiện';

                    $this->assignmentStats['in_progress']++;
                    break;
            }
        }

        // =========================================
        // 5. HIEN THI GIAO DIEN
        // =========================================

        parent::display($tpl);
    }
}