<?php

namespace Joomla\Component\Teamflow\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

class ReviewsController extends BaseController
{
    public function review(): void
    {
        $url = Route::_(
            'index.php?option=com_teamflow&view=reviews',
            false
        );

        // Chỉ chấp nhận phương thức POST.
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            $this->setRedirect(
                $url,
                'Yêu cầu không hợp lệ.',
                'error'
            );
            return;
        }

        // Kiểm tra CSRF token.
        if (!Session::checkToken('post')) {
            $this->setRedirect(
                $url,
                'Phiên làm việc không hợp lệ.',
                'error'
            );
            return;
        }

        // Kiểm tra đăng nhập.
        $user = $this->app->getIdentity();

        if (!$user || $user->guest) {
            $this->setRedirect(
                $url,
                'Bạn chưa đăng nhập.',
                'error'
            );
            return;
        }

        // Nhận dữ liệu bài nộp.
        $id = $this->input->post->getInt('id', 0);

        $review_status = $this->input->post->getCmd(
            'review_status',
            ''
        );

        $review_comment = trim(
            $this->input->post->getString(
                'review_comment',
                ''
            )
        );

        // Kiểm tra ID bài nộp.
        if ($id <= 0) {
            $this->setRedirect(
                $url,
                'Bài nộp không hợp lệ.',
                'error'
            );
            return;
        }

        // Chỉ cho phép hai trạng thái đánh giá.
        if (!in_array(
            $review_status,
            ['approved', 'revision_required'],
            true
        )) {
            $this->setRedirect(
                $url,
                'Trạng thái đánh giá không hợp lệ.',
                'error'
            );
            return;
        }

        // Bắt buộc nhận xét nếu yêu cầu chỉnh sửa.
        if (
            $review_status === 'revision_required'
            && $review_comment === ''
        ) {
            $this->setRedirect(
                $url,
                'Vui lòng nhập nhận xét khi yêu cầu chỉnh sửa.',
                'error'
            );
            return;
        }

        try {
            // Gọi Model xử lý đánh giá.
            $model = $this->getModel('Review');

            if (
                !$model ||
                !method_exists($model, 'reviewSubmission')
            ) {
                throw new \RuntimeException(
                    'Không tìm thấy ReviewModel.'
                );
            }

            $model->reviewSubmission(
                $id,
                $review_status,
                $review_comment
            );

            $this->setRedirect(
                $url,
                'Đánh giá bài nộp thành công.',
                'message'
            );
        } catch (\Throwable $e) {
            $this->setRedirect(
                $url,
                'Không thể hoàn thành đánh giá.',
                'error'
            );
        }
    }
}
