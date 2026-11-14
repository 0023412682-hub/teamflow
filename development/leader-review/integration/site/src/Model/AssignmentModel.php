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
        string $description
    ): bool {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException('Bạn chưa đăng nhập.');
        }

        $leader_user_id = (int) $user->id;
        $db = $this->getDatabase();

        // Kiểm tra nhiệm vụ thuộc nhóm do người dùng quản lý.
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
                $db->quoteName('tg.id') . ' = ' . (int) $task_group_id
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

        if (trim($title) === '') {
            throw new \InvalidArgumentException(
                'Tên công việc không được để trống.'
            );
        }

        // Lưu phân công vào bảng dùng chung.
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__teamflow_assignments'))
            ->columns(
                $db->quoteName([
                    'task_group_id',
                    'assignee_user_id',
                    'title',
                    'description'
                ])
            )
            ->values(
                implode(',', [
                    (int) $task_group_id,
                    (int) $assignee_user_id,
                    $db->quote(trim($title)),
                    $db->quote(trim($description))
                ])
            );

        $db->setQuery($query);
        $db->execute();

        return true;
    }
}
