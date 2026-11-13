<?php

namespace Joomla\Component\Teamflow\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class TasksModel extends ListModel
{
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select([
                $db->quoteName('t.id'),
                $db->quoteName('t.title'),
                $db->quoteName('t.description'),
                $db->quoteName('t.criteria'),
                $db->quoteName('t.due_at'),
                $db->quoteName('t.created_by'),
                $db->quoteName('u.name', 'creator_name')
            ])
            ->from($db->quoteName('#__teamflow_tasks', 't'))
            ->leftJoin(
                $db->quoteName('#__users', 'u')
                . ' ON '
                . $db->quoteName('t.created_by')
                . ' = '
                . $db->quoteName('u.id')
            )
            ->order($db->quoteName('t.id') . ' DESC');

        return $query;
    }

    public function getAssignedGroupNames(): array
    {
        $db = $this->getDatabase();
        $tables = $db->getTableList();

        $groupsTable = $db->replacePrefix('#__teamflow_groups');
        $relationsTable = $db->replacePrefix('#__teamflow_task_groups');

        // Không truy vấn nếu bảng chưa tồn tại.
        if (
            !in_array($groupsTable, $tables, true) ||
            !in_array($relationsTable, $tables, true)
        ) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('tg.task_id'),
                $db->quoteName('g.name')
            ])
            ->from($db->quoteName('#__teamflow_task_groups', 'tg'))
            ->innerJoin(
                $db->quoteName('#__teamflow_groups', 'g')
                . ' ON '
                . $db->quoteName('tg.group_id')
                . ' = '
                . $db->quoteName('g.id')
            )
            ->order($db->quoteName('g.name') . ' ASC');

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $result = [];

        foreach ($rows as $row) {
            $taskId = (int) $row->task_id;
            $result[$taskId][] = $row->name;
        }

        return $result;
    }
    public function getAssignedGroupIdsMap(): array
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('task_id'),
                $db->quoteName('group_id')
            ])
            ->from($db->quoteName('#__teamflow_task_groups'));

        $db->setQuery($query);
        $rows = $db->loadObjectList() ?: [];

        $result = [];

        foreach ($rows as $row) {
            $taskId = (int) $row->task_id;
            $result[$taskId][] = (int) $row->group_id;
        }

        return $result;
    }
}
