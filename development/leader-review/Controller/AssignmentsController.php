<?php

namespace Joomla\Component\Teamflow\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

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
                $description
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
