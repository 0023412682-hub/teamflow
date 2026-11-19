<?php

namespace Joomla\Component\Teamflow\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class AssignmentsController extends BaseController
{
    public function assign(): void
    {
        $url = Route::_(
            'index.php?option=com_teamflow&view=assignments',
            false
        );

        // Chỉ chấp nhận yêu cầu POST.
        if (
            strtoupper($this->input->getMethod()) !== 'POST'
        ) {
            $this->setRedirect(
                $url,
                'Yêu cầu không hợp lệ.',
                'error'
            );
            return;
        }

        // Kiểm tra token bảo vệ biểu mẫu.
        if (!$this->checkToken('post', false)) {
            $this->setRedirect(
                $url,
                'Phiên làm việc không hợp lệ.',
                'error'
            );
            return;
        }

        // Kiểm tra người dùng đã đăng nhập.
        $user = $this->app->getIdentity();

        if (!$user || $user->guest) {
            $this->setRedirect(
                $url,
                'Bạn chưa đăng nhập.',
                'error'
            );
            return;
        }

        // Nhận dữ liệu từ biểu mẫu.
        $task_group_id = $this->input->post->getInt(
            'task_group_id',
            0
        );

        $assignee_user_id = $this->input->post->getInt(
            'assignee_user_id',
            0
        );

        $title = trim(
            $this->input->post->getString('title', '')
        );

        $description = trim(
            $this->input->post->getString(
                'description',
                ''
            )
        );
        
$due_at = trim(
    $this->input->post->getString('due_at', '')
);

$date = \DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $due_at
);

if (!$date || $date->format('Y-m-d') !== $due_at) {
    $this->setRedirect(
        $url,
        'Hạn hoàn thành không hợp lệ.',
        'error'
    );
    return;
}
        // Kiểm tra dữ liệu nhập.
        if (
            $task_group_id <= 0 ||
            $assignee_user_id <= 0 ||
            $title === ''
        ) {
            $this->setRedirect(
                $url,
                'Vui lòng nhập đầy đủ thông tin.',
                'error'
            );
            return;
        }

        // Kiem tra quyen tren server truoc khi ghi du lieu.
        // Nhiem vu nhom phai thuoc nhom dang hoat dong do nguoi dung quan ly;
        // thanh vien duoc chon phai thuoc CHINH nhom do.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $permissionQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__teamflow_task_groups', 'tg'))
            ->join('INNER', $db->quoteName('#__teamflow_groups', 'g')
                . ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('tg.group_id'))
            ->join('INNER', $db->quoteName('#__teamflow_group_members', 'gm')
                . ' ON ' . $db->quoteName('gm.group_id') . ' = ' . $db->quoteName('g.id'))
            ->where($db->quoteName('tg.id') . ' = ' . (int) $task_group_id)
            ->where($db->quoteName('gm.user_id') . ' = ' . (int) $assignee_user_id)
            ->where($db->quoteName('g.leader_user_id') . ' = ' . (int) $user->id)
            ->where($db->quoteName('g.is_active') . ' = 1');
        $db->setQuery($permissionQuery);

        if ((int) $db->loadResult() === 0) {
            throw new \RuntimeException(
                'Bạn không có quyền phân công nhiệm vụ này hoặc thành viên không thuộc nhóm.',
                403
            );
        }

        try {
            // Gọi AssignmentModel để lưu phân công.
            $model = $this->getModel('Assignment');

            if (
                !$model ||
                !method_exists($model, 'assignTask')
            ) {
                throw new \RuntimeException(
                    'Không tìm thấy AssignmentModel.'
                );
            }

           
            $model->assignTask(
             $task_group_id,
             $assignee_user_id,
             $title,
             $description,
            $due_at
            );

            $this->setRedirect(
                $url,
                'Phân công nhiệm vụ thành công.',
                'message'
            );
        } catch (\Throwable $e) {
            $this->setRedirect(
                $url,
                'Không thể phân công nhiệm vụ: '
                . $e->getMessage(),
                'error'
            );
        }
    }
}
