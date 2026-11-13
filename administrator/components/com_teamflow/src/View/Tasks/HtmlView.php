<?php

namespace Joomla\Component\Teamflow\Administrator\View\Tasks;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Component\Teamflow\Administrator\Model\TaskModel;

class HtmlView extends BaseHtmlView
{
    public array $items = [];
    public array $groups = [];
    public array $assignedGroupNames = [];
    public array $assignedGroupIdsMap = [];

    public function display($tpl = null)
    {
        // Danh sách nhiệm vụ thật
        $tasksModel = $this->getModel();

        if (!$tasksModel) {
            throw new \RuntimeException(
                'Không tìm thấy TasksModel.'
            );
        }

        $this->items = $tasksModel->getItems();
        $this->assignedGroupNames = $tasksModel->getAssignedGroupNames();
        $this->assignedGroupIdsMap = $tasksModel->getAssignedGroupIdsMap();

        // Lấy thêm TaskModel để đọc danh sách nhóm.
        $component = \Joomla\CMS\Factory::getApplication()
            ->bootComponent('com_teamflow');

        $taskModel = $component->getMVCFactory()
            ->createModel(
                'Task',
                'Administrator',
                ['ignore_request' => true]
            );


        if (!$taskModel instanceof TaskModel) {
            throw new \RuntimeException(
                'Không tìm thấy TaskModel.'
            );
        }

        $this->groups = $taskModel->getAvailableGroups();

        parent::display($tpl);
    }
}
