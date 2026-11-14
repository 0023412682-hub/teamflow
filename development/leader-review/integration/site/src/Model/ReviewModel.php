<?php

namespace Joomla\Component\Teamflow\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class ReviewModel extends BaseDatabaseModel
{
    public function reviewSubmission(
        int $id,
        string $review_status,
        string $review_comment
    ): bool {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException('Bạn chưa đăng nhập.');
        }

        if (!in_array(
            $review_status,
            ['approved', 'revision_required'],
            true
        )) {
            throw new \InvalidArgumentException(
                'Trạng thái đánh giá không hợp lệ.'
            );
        }

        if (
            $review_status === 'revision_required'
            && trim($review_comment) === ''
        ) {
            throw new \InvalidArgumentException(
                'Cần nhập nhận xét khi yêu cầu chỉnh sửa.'
            );
        }

        $db = $this->getDatabase();
        $leader_user_id = (int) $user->id;

        // Kiểm tra bài nộp thuộc nhóm do người dùng quản lý.
        $query = $db->getQuery(true)
            ->select($db->quoteName('s.id'))
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
                $db->quoteName('s.id') . ' = ' . (int) $id
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
            );

        // Chi cho phep duyet lan nop moi nhat.
        $query->where(
            'NOT EXISTS (SELECT 1 FROM '
            . $db->quoteName('#__teamflow_submissions', 'newer')
            . ' WHERE newer.assignment_id = s.assignment_id'
            . ' AND (newer.attempt_no > s.attempt_no'
            . ' OR (newer.attempt_no = s.attempt_no'
            . ' AND newer.id > s.id)))'
        );
        $db->setQuery($query);

        if (!$db->loadResult()) {
            throw new \RuntimeException(
                'Không tìm thấy bài nộp chờ duyệt hoặc bạn không có quyền đánh giá.'
            );
        }

        // Cập nhật đánh giá.
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__teamflow_submissions'))
            ->set(
                $db->quoteName('review_status')
                . ' = ' . $db->quote($review_status)
            )
            ->set(
                $db->quoteName('review_comment')
                . ' = ' . $db->quote(trim($review_comment))
            )
            ->set(
                $db->quoteName('reviewed_by')
                . ' = ' . $leader_user_id
            )
            ->set(
                $db->quoteName('reviewed_at')
                . ' = ' . $db->quote(
                    Factory::getDate()->toSql()
                )
            )
            ->where(
                $db->quoteName('id') . ' = ' . (int) $id
            )
            ->where(
                $db->quoteName('review_status')
                . ' = ' . $db->quote('pending')
            );

        $db->setQuery($query);
        $db->execute();

        if ($db->getAffectedRows() !== 1) {
            throw new \RuntimeException(
                'Bài nộp đã được xử lý hoặc không thể cập nhật.'
            );
        }

        return true;
    }
}
