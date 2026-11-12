<?php

namespace Joomla\Component\Teamflow\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Router\Route;

class SubmissionsController extends BaseController
{
    public function submit(): void
    {
        $app = Factory::getApplication();

        // Chỉ chấp nhận yêu cầu POST.
        if ($this->input->getMethod() !== 'POST') {
            throw new \RuntimeException(
                'Phương thức gửi không hợp lệ.',
                405
            );
        }

        // Kiểm tra CSRF token.
        if (!Session::checkToken('post')) {
            throw new \RuntimeException(
                'Phiên gửi bài không hợp lệ.',
                403
            );
        }

        // Kiểm tra đăng nhập.
        $user = $app->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException(
                'Bạn cần đăng nhập trước khi nộp bài.',
                403
            );
        }

        // Nhận thông tin bài nộp.
        $assignment_id = $this->input->post->getInt(
            'assignment_id',
            0
        );

        $link_url = trim(
            $this->input->post->getString('link_url', '')
        );

        if ($assignment_id <= 0) {
            throw new \RuntimeException(
                'Nhiệm vụ không hợp lệ.'
            );
        }

        // Giai đoạn đầu: nộp bằng liên kết.
        if ($link_url === '') {
            throw new \RuntimeException(
                'Vui lòng nhập liên kết bài làm.'
            );
        }

        try {
            $model = $this->getModel('Submission');

            if (!$model) {
                throw new \RuntimeException(
                    'Không tìm thấy SubmissionModel.'
                );
            }

            $id = $model->saveSubmission(
                $assignment_id,
                null,
                $link_url
            );

            $app->enqueueMessage(
                'Nộp bài thành công. Mã bài nộp: ' . $id,
                'success'
            );
        } catch (\Throwable $e) {
            $app->enqueueMessage(
                $e->getMessage(),
                'error'
            );
        }

        $this->setRedirect(
            Route::_('index.php?option=com_teamflow', false)
        );
    }
}
