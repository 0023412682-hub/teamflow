<?php

namespace Joomla\Component\Teamflow\Site\View\Assignments;
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public $task_groups = [];
    public $members = [];

    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity();

        if (!$user || $user->guest) {
            throw new \RuntimeException('Bạn chưa đăng nhập.');
        }

        $leader_user_id = (int) $user->id;
        $db = Factory::getContainer()
            ->get(\Joomla\Database\DatabaseInterface::class);

        // Lấy các nhiệm vụ được giao cho nhóm của nhóm trưởng.
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('tg.id'),
                $db->quoteName('t.title')
            ])
            ->from($db->quoteName('#__teamflow_task_groups', 'tg'))
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_tasks', 't')
                . ' ON ' . $db->quoteName('t.id')
                . ' = ' . $db->quoteName('tg.task_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_groups', 'g')
                . ' ON ' . $db->quoteName('g.id')
                . ' = ' . $db->quoteName('tg.group_id')
            )
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            );

        $db->setQuery($query);
        $this->task_groups = $db->loadObjectList();

        // Lấy thành viên thuộc các nhóm đang quản lý.
        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('u.id'))
            ->select($db->quoteName('u.name'))
            ->from($db->quoteName('#__teamflow_group_members', 'gm'))
            ->join(
                'INNER',
                $db->quoteName('#__teamflow_groups', 'g')
                . ' ON ' . $db->quoteName('g.id')
                . ' = ' . $db->quoteName('gm.group_id')
            )
            ->join(
                'INNER',
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id')
                . ' = ' . $db->quoteName('gm.user_id')
            )
            ->where(
                $db->quoteName('g.leader_user_id')
                . ' = ' . $leader_user_id
            )
            ->where(
                $db->quoteName('g.is_active') . ' = 1'
            );

        $db->setQuery($query);
        $this->members = $db->loadObjectList();

        parent::display($tpl);
    }
}
