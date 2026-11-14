<?php
namespace Joomla\Component\Teamflow\Site\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    public $groups = [];
    public $assignments = [];
    public $pendingCount = 0;
    public $totalCount = 0;
    public $doneCount = 0;
    public $activeCount = 0;

    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException(
                'Vui lòng đăng nhập để xem nhóm của tôi.',
                403
            );
        }

        $leader_user_id = (int) $user->id;

        $db = Factory::getContainer()
            ->get(DatabaseInterface::class);

        // Chỉ lấy nhóm đang hoạt động do người đăng nhập quản lý.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('g.id'),
                $db->quoteName('g.name'),
                $db->quoteName('g.description'),
                $db->quoteName('g.leader_user_id')
            ])
            ->from($db->quoteName('#__teamflow_groups', 'g'))
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            )
            ->order($db->quoteName('g.id') . ' ASC');

        $db->setQuery($query);
        $this->groups = $db->loadObjectList();

        // Lấy danh sách công việc thuộc các nhóm do nhóm trưởng quản lý.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('a.id'),
                $db->quoteName('a.title'),
                $db->quoteName('a.description'),
                $db->quoteName('u.name', 'member_name'),
                $db->quoteName('t.due_at', 'due_at')
            ])
            ->from($db->quoteName('#__teamflow_assignments', 'a'))
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
                $db->quoteName('#__teamflow_tasks', 't')
                . ' ON ' . $db->quoteName('t.id')
                . ' = ' . $db->quoteName('tg.task_id')
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
            ->order($db->quoteName('a.id') . ' DESC');

        $db->setQuery($query);
        $this->assignments = $db->loadObjectList();

        // Đếm số bài nộp đang chờ duyệt thuộc nhóm trưởng.
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
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
            ->where('g.leader_user_id = ' . $leader_user_id)
            ->where('g.is_active = 1')
            ->where(
                's.review_status = ' . $db->quote('pending')
            );

        $db->setQuery($query);
        $this->pendingCount = (int) $db->loadResult();

        // Thống kê theo lần nộp mới nhất của mỗi phân công.
        $this->totalCount = count($this->assignments);
        $this->doneCount = 0;
        $this->activeCount = $this->totalCount;
        $this->pendingCount = 0;

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('s.assignment_id'),
                $db->quoteName('s.review_status')
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
            ->where('g.leader_user_id = ' . $leader_user_id)
            ->where('g.is_active = 1')
            ->order([
                's.assignment_id ASC',
                's.attempt_no DESC',
                's.id DESC'
            ]);

        $db->setQuery($query);
        $submissions = $db->loadObjectList();



        $statusByAssignment = [];

        foreach ($submissions as $submission) {
            $assignmentId = (int) $submission->assignment_id;

            // Kết quả đã được sắp theo lần nộp mới nhất trước.
            if (isset($statusByAssignment[$assignmentId])) {
                continue;
            }

            $statusByAssignment[$assignmentId] =
                (string) $submission->review_status;
        }

        foreach ($this->assignments as $assignment) {
            $assignmentId = (int) $assignment->id;

            $status = $statusByAssignment[$assignmentId]
                ?? 'not_submitted';

            switch ($status) {
                case 'approved':
                    $assignment->status_label = 'Đã hoàn thành';
                    $this->doneCount++;
                    $this->activeCount--;
                    break;

                case 'pending':
                    $assignment->status_label = 'Chờ duyệt';
                    $this->pendingCount++;
                    $this->activeCount--;
                    break;

                case 'revision_required':
                    $assignment->status_label = 'Cần chỉnh sửa';
                    break;

                default:
                    $assignment->status_label = 'Chưa nộp bài';
                    break;
            }
        }




        parent::display($tpl);
    }
}
