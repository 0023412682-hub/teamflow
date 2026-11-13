<?php

namespace Joomla\Component\Teamflow\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class TasksController extends AdminController
{
    public function getModel(
        $name = 'Task',
        $prefix = 'Administrator',
        $config = ['ignore_request' => true]
    ): BaseDatabaseModel|bool {
        return parent::getModel($name, $prefix, $config);
    }

public function create(): void
{
    // Kiểm tra CSRF
    $this->checkToken('post');

    $app = \Joomla\CMS\Factory::getApplication();
    $user = $app->getIdentity();

    // Giai đoạn thử nghiệm: chỉ Super User được tạo.
    if (
        $user->guest ||
        !$user->authorise('core.admin')
    ) {
        throw new \RuntimeException(
            'Bạn không có quyền tạo nhiệm vụ.',
            403
        );
    }

    $input = $app->getInput();

    $title = trim($input->post->getString('title', ''));
    $description = trim($input->post->getString('description', ''));
    $criteria = trim($input->post->getString('criteria', ''));
    $due_at = trim($input->post->getString('due_at', ''));

    // Chuyển định dạng từ datetime-local sang MySQL
    $due_at = str_replace('T', ' ', $due_at);

    if (strlen($due_at) === 16) {
        $due_at .= ':00';
    }

    try {
        if (
            $title === '' ||
            $description === '' ||
            $criteria === '' ||
            $due_at === ''
        ) {
            throw new \InvalidArgumentException(
                'Vui lòng nhập đầy đủ thông tin nhiệm vụ.'
            );
        }

        $model = $this->getModel('Task');

        if (!$model instanceof
            \Joomla\Component\Teamflow\Administrator\Model\TaskModel
        ) {
            throw new \RuntimeException(
                'Không tìm thấy TaskModel.'
            );
        }
        $rawGroupIds = $input->post->get('group_ids', [], 'array');

        $group_ids = [];

        foreach ($rawGroupIds as $groupId) {
            if (
                !is_scalar($groupId) ||
                !ctype_digit((string) $groupId) ||
                (int) $groupId <= 0
            ) {
                throw new \InvalidArgumentException(
                    'ID nhóm không hợp lệ.'
                );
            }

            $group_ids[] = (int) $groupId;
        }

        $group_ids = array_values(array_unique($group_ids));

        $taskId = $model->createTaskWithGroups(
            $title,
            $description,
            $criteria,
            $due_at,
            (int) $user->id,
            $group_ids
        );


        $this->setRedirect(
            'index.php?option=com_teamflow&view=tasks',
            'Đã tạo nhiệm vụ thành công. ID: ' . $taskId,
            'message'
        );
        } catch (\Throwable $e) {
            \Joomla\CMS\Log\Log::add(
                'TeamFlow createTask: ' . $e->getMessage(),
                \Joomla\CMS\Log\Log::ERROR,
                'com_teamflow'
            );

            $this->setRedirect(
                'index.php?option=com_teamflow&view=tasks',
                'Không thể tạo nhiệm vụ. Vui lòng kiểm tra lại dữ liệu hoặc liên hệ quản trị viên.',
                'error'
            );
        }

}

public function update(): void
{
    // Kiểm tra token chống CSRF.
    $this->checkToken('post');

    $app = \Joomla\CMS\Factory::getApplication();
    $user = $app->getIdentity();

    // Chỉ Super User được sửa ở giai đoạn kiểm thử.
    if (
        $user->guest ||
        !$user->authorise('core.admin')
    ) {
        throw new \RuntimeException(
            'Bạn không có quyền sửa nhiệm vụ.',
            403
        );
    }

    $input = $app->getInput();

    // Lấy ID nhiệm vụ cần cập nhật.
    $id = $input->post->getInt('id', 0);

    $title = trim(
        $input->post->getString('title', '')
    );

    $description = trim(
        $input->post->getString('description', '')
    );

    $criteria = trim(
        $input->post->getString('criteria', '')
    );

    $due_at = trim(
        $input->post->getString('due_at', '')
    );

    // Đổi datetime-local sang định dạng MySQL.
    $due_at = str_replace('T', ' ', $due_at);

    if (strlen($due_at) === 16) {
        $due_at .= ':00';
    }

    $url = 'index.php?option=com_teamflow&view=tasks';

    try {
        if ($id <= 0) {
            throw new \InvalidArgumentException(
                'ID nhiệm vụ không hợp lệ.'
            );
        }

        $model = $this->getModel('Task');

        if (!$model instanceof
            \Joomla\Component\Teamflow\Administrator\Model\TaskModel
        ) {
            throw new \RuntimeException(
                'Không tìm thấy TaskModel.'
            );
        }

        // Đọc các nhóm được chọn trong biểu mẫu Sửa.
        $rawGroupIds = $input->post->get('group_ids', [], 'array');

        $group_ids = [];

        foreach ($rawGroupIds as $groupId) {
            if (
                !is_scalar($groupId) ||
                !ctype_digit((string) $groupId) ||
                (int) $groupId <= 0
            ) {
                throw new \InvalidArgumentException(
                    'ID nhóm không hợp lệ.'
                );
            }

            $group_ids[] = (int) $groupId;
        }

        $group_ids = array_values(array_unique($group_ids));

        $model->updateTaskWithGroups(
            $id,
            $title,
            $description,
            $criteria,
            $due_at,
            $group_ids
        );

        $this->setRedirect(
            $url,
            'Cập nhật nhiệm vụ thành công. ID: ' . $id,
            'message'
        );

    } catch (\InvalidArgumentException $e) {
        $app->enqueueMessage(
            $e->getMessage(),
            'error'
        );

        $this->setRedirect($url);

    } catch (\Throwable $e) {
        \Joomla\CMS\Log\Log::add(
            'TeamFlow updateTask: ' . $e->getMessage(),
            \Joomla\CMS\Log\Log::ERROR,
            'com_teamflow'
        );

        $this->setRedirect(
            $url,
            'Lỗi cập nhật: ' . $e->getMessage(),
            'error'
        );

    }
}
}
