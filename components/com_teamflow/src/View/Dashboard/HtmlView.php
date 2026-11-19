<?php
namespace Joomla\Component\Teamflow\Site\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;

class HtmlView extends BaseHtmlView
{
    public $groups = [];
    public $task_groups = [];
    public $members = [];
    public $assignments = [];
    public $pendingCount = 0;
    public $pendingSubmissions = [];
    public $totalCount = 0;
    public $doneCount = 0;
    public $activeCount = 0;
    public $progressDays = [];

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

        // Chỉ người đang quản lý ít nhất một nhóm hoạt động mới được mở Dashboard.
        // Kiểm tra trước khi tải nhiệm vụ, bài nộp và các thống kê.
        if (empty($this->groups)) {
            throw new \RuntimeException(
                'Bạn không có quyền truy cập Dashboard Nhóm trưởng. Chức năng này chỉ dành cho người quản lý nhóm.',
                403
            );
        }

        // Danh sách nhiệm vụ nhóm hợp lệ để phân công.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('tg.id'),
                $db->quoteName('t.title')
            ])
            ->from($db->quoteName('#__teamflow_task_groups', 'tg'))
            ->join('INNER', $db->quoteName('#__teamflow_tasks', 't')
                . ' ON t.id = tg.task_id')
            ->join('INNER', $db->quoteName('#__teamflow_groups', 'g')
                . ' ON g.id = tg.group_id')
            ->where('g.leader_user_id = ' . $leader_user_id)
            ->where('g.is_active = 1')
            ->order('tg.id DESC');
        $db->setQuery($query);
        $this->task_groups = $db->loadObjectList();

        // Thành viên trong các nhóm đang quản lý.
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('u.id'))
            ->select($db->quoteName('u.name'))
            ->from($db->quoteName('#__teamflow_group_members', 'gm'))
            ->join('INNER', $db->quoteName('#__teamflow_groups', 'g')
                . ' ON g.id = gm.group_id')
            ->join('INNER', $db->quoteName('#__users', 'u')
                . ' ON u.id = gm.user_id')
            ->where('g.leader_user_id = ' . $leader_user_id)
            ->where('g.is_active = 1')
            ->order('u.name ASC');
        $db->setQuery($query);
        $this->members = $db->loadObjectList();

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

        // Thống kê theo lần nộp mới nhất của mỗi phân công.
        $this->totalCount = count($this->assignments);
        $this->doneCount = 0;
        $this->activeCount = $this->totalCount;
        $this->pendingCount = 0;

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('s.assignment_id'),
                $db->quoteName('s.review_status'),
                $db->quoteName('s.attempt_no'),
                $db->quoteName('s.submitted_at'),
                $db->quoteName('s.reviewed_at')
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




        // TEAMFLOW_PROGRESS_CHART
        // Anh chup trang thai cuoi moi ngay trong 7 ngay gan nhat.
        // Mau so la TONG NHIEM VU HIEN TAI; khong phai so nhiem vu
        // da duoc giao tai tung thoi diem lich su.
        $this->progressDays = [];
        $daysRequested = Factory::getApplication()->input->getInt('tf_days', 7);
        $this->progressPeriod = in_array($daysRequested, [7, 14, 30], true)
            ? $daysRequested
            : 7;

        $today = new \DateTimeImmutable(
            'today',
            new \DateTimeZone('Asia/Ho_Chi_Minh')
        );

        // $submissions da duoc sap theo assignment_id, attempt_no DESC, id DESC.
        for ($offset = $this->progressPeriod - 1; $offset >= 0; $offset--) {
            $day = $today->modify("-{$offset} days");
            $endOfDay = $day->format('Y-m-d') . ' 23:59:59';
            $latest = [];

            foreach ($submissions as $submission) {
                $assignmentId = (int) $submission->assignment_id;

                // Chi lay lan nop da ton tai vao cuoi ngay do.
                if (
                    empty($submission->submitted_at)
                    || $submission->submitted_at > $endOfDay
                    || isset($latest[$assignmentId])
                ) {
                    continue;
                }

                $latest[$assignmentId] = $submission;
            }

            $approved = 0;
            $pending = 0;
            $revision = 0;
            $notSubmitted = 0;

            foreach ($this->assignments as $assignment) {
                $submission = $latest[(int) $assignment->id] ?? null;

                if (!$submission) {
                    $notSubmitted++;
                    continue;
                }

                // Trang thai duyet chi co hieu luc tu thoi diem duyet.
                // Truoc thoi diem do, bai nop duoc tinh la cho duyet.
                $reviewedByDay = !empty($submission->reviewed_at)
                    && $submission->reviewed_at <= $endOfDay;

                if (!$reviewedByDay) {
                    $pending++;
                } elseif ($submission->review_status === 'approved') {
                    $approved++;
                } elseif ($submission->review_status === 'revision_required') {
                    $revision++;
                } else {
                    $pending++;
                }
            }

            $percent = $this->totalCount > 0
                ? (int) round($approved * 100 / $this->totalCount)
                : 0;

            $this->progressDays[] = [
                'label' => $day->format('d/m'),
                'percent' => $percent,
                'approved' => $approved,
                'pending' => $pending,
                'revision_required' => $revision,
                'not_submitted' => $notSubmitted,
                'total' => $this->totalCount
            ];
        }

        // =========================================
        // LAY BAI NOP DANG CHO DUYET
        // =========================================

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
            ->from(
                $db->quoteName('#__teamflow_submissions', 's')
            )
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
                'INNER',
                $db->quoteName('#__users', 'u')
                . ' ON u.id = a.assignee_user_id'
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
                'NOT EXISTS (
                    SELECT 1
                    FROM #__teamflow_submissions AS newer
                    WHERE newer.assignment_id = s.assignment_id
                    AND (
                        newer.attempt_no > s.attempt_no
                        OR (
                            newer.attempt_no = s.attempt_no
                            AND newer.id > s.id
                        )
                    )
                )'
            )
            ->order(
                $db->quoteName('s.submitted_at') . ' ASC'
            );

        $db->setQuery($query);

        $this->pendingSubmissions = $db->loadObjectList();
        parent::display($tpl);
    }
}
