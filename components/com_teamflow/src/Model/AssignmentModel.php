<?php

namespace Joomla\Component\Teamflow\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class AssignmentModel extends BaseDatabaseModel
{
    public function assignTask(
        int $task_group_id,
        int $assignee_user_id,
        string $title,
        string $description,
        string $due_at
    ): bool {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException('Bạn chưa đăng nhập.');
        }

        $leader_user_id = (int) $user->id;
        $db = $this->getDatabase();

        // Kiểm tra tên nhiệm vụ
        $title = trim($title);

        if ($title === '') {
            throw new \InvalidArgumentException(
                'Tên công việc không được để trống.'
            );
        }

        // Kiểm tra hạn hoàn thành
        $due_at = trim($due_at);

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $due_at
        );

        if (
            !$date ||
            $date->format('Y-m-d') !== $due_at
        ) {
            throw new \InvalidArgumentException(
                'Hạn hoàn thành không hợp lệ.'
            );
        }

        // Kiểm tra quyền phân công và thành viên thuộc nhóm
        $query = $db->getQuery(true)
            ->select($db->quoteName('g.id'))
            ->from(
                $db->quoteName('#__teamflow_task_groups', 'tg')
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_groups', 'g')
                . ' ON ' . $db->quoteName('g.id')
                . ' = ' . $db->quoteName('tg.group_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_group_members', 'gm')
                . ' ON ' . $db->quoteName('gm.group_id')
                . ' = ' . $db->quoteName('g.id')
            )
            ->where(
                $db->quoteName('tg.id')
                . ' = ' . (int) $task_group_id
            )
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('gm.user_id')
                . ' = ' . (int) $assignee_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            );

        $db->setQuery($query);

        $group_id = $db->loadResult();

        if (!$group_id) {
            throw new \RuntimeException(
                'Bạn không có quyền phân công nhiệm vụ này.'
            );
        }

        // Lưu nhiệm vụ và hạn hoàn thành vào MySQL
        $query = $db->getQuery(true)
            ->insert(
                $db->quoteName('#__teamflow_assignments')
            )
            ->columns(
                $db->quoteName([
                    'task_group_id',
                    'assignee_user_id',
                    'title',
                    'description',
                    'due_at'
                ])
            )
            ->values(
                implode(',', [
                    (int) $task_group_id,
                    (int) $assignee_user_id,
                    $db->quote($title),
                    $db->quote(trim($description)),
                    $db->quote($due_at)
                ])
            );

        $db->setQuery($query);
        $db->execute();

        return true;
    }
}
