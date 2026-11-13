<?php

namespace Joomla\Component\Teamflow\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class TaskModel extends BaseDatabaseModel
{
    public function getTask(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('description'),
                $db->quoteName('criteria'),
                $db->quoteName('due_at'),
                $db->quoteName('created_by')
            ])
            ->from($db->quoteName('#__teamflow_tasks'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);

        $db->setQuery($query);

        return $db->loadObject();
    }

    public function getAssignedGroupIds(int $taskId): array
    {
        if ($taskId <= 0) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName('group_id'))
            ->from($db->quoteName('#__teamflow_task_groups'))
            ->where(
                $db->quoteName('task_id') . ' = ' . (int) $taskId
            );

        $db->setQuery($query);

        return array_map('intval', $db->loadColumn());
    }

public function createTask(
    string $title,
    string $description,
    string $criteria,
    string $due_at,
    int $created_by
): int
{
    $title = trim($title);
    $description = trim($description);
    $criteria = trim($criteria);

    if ($title === '') {
        throw new \InvalidArgumentException(
            'Tên nhiệm vụ không được để trống.'
        );
    }

    if ($description === '' || $criteria === '') {
        throw new \InvalidArgumentException(
            'Nội dung và tiêu chí không được để trống.'
        );
    }

    if ($created_by <= 0) {
        throw new \InvalidArgumentException(
            'Người tạo nhiệm vụ không hợp lệ.'
        );
    }
    $timezoneName = \Joomla\CMS\Factory::getApplication()
        ->getConfig()
        ->get('offset', 'UTC');

    $timezone = new \DateTimeZone($timezoneName);

    $date = \DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $due_at,
        $timezone
    );

    if (!$date || $date->format('Y-m-d H:i:s') !== $due_at) {
        throw new \InvalidArgumentException(
            'Hạn nộp không hợp lệ.'
        );
    }

    $now = new \DateTimeImmutable('now', $timezone);

    if ($date <= $now) {
        throw new \InvalidArgumentException(
            'Hạn nộp phải lớn hơn thời điểm hiện tại.'
        );
    }


    $db = $this->getDatabase();
    $query = $db->getQuery(true);

    $query
        ->insert($db->quoteName('#__teamflow_tasks'))
        ->columns($db->quoteName([
            'title',
            'description',
            'criteria',
            'due_at',
            'created_by'
        ]))
        ->values(implode(',', [
            $db->quote($title),
            $db->quote($description),
            $db->quote($criteria),
            $db->quote($due_at),
            (int) $created_by
        ]));

    $db->setQuery($query);
    $db->execute();

    return (int) $db->insertid();
}

public function assignTaskToGroups(
    int $task_id,
    array $group_ids
): void
{
    if ($task_id <= 0 || $this->getTask($task_id) === null) {
        throw new \InvalidArgumentException(
            'Nhiệm vụ không tồn tại.'
        );
    }

    if (empty($group_ids)) {
        throw new \InvalidArgumentException(
            'Vui lòng chọn ít nhất một nhóm.'
        );
    }

    foreach ($group_ids as $group_id) {
        if (filter_var(
            $group_id,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) === false) {
            throw new \InvalidArgumentException(
                'ID nhóm không hợp lệ.'
            );
        }
    }

    $group_ids = array_values(
        array_unique(array_map('intval', $group_ids))
    );

    $db = $this->getDatabase();

    // Kiểm tra các nhóm có tồn tại hay không
    $query = $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__teamflow_groups'))
        ->where(
            $db->quoteName('id') . ' IN (' .
            implode(',', $group_ids) . ')'
        );

    $db->setQuery($query);
    $existing_group_ids = array_map(
        'intval',
        $db->loadColumn()
    );

    if (count($existing_group_ids) !== count($group_ids)) {
        throw new \InvalidArgumentException(
            'Có nhóm được chọn không tồn tại.'
        );
    }

    // Lấy danh sách nhóm đã được giao trước đó
    $assigned_group_ids = $this->getAssignedGroupIds(
        $task_id
    );

    $new_group_ids = array_diff(
        $group_ids,
        $assigned_group_ids
    );

    // Giao nhiệm vụ cho những nhóm chưa nhận
    $db->transactionStart(true);

    try {
        foreach ($new_group_ids as $group_id) {
            $query = $db->getQuery(true)
                ->insert(
                    $db->quoteName('#__teamflow_task_groups')
                )
                ->columns(
                    $db->quoteName([
                        'task_id',
                        'group_id'
                    ])
                )
                ->values(
                    (int) $task_id . ',' . (int) $group_id
                );

            $db->setQuery($query);
            $db->execute();
        }

        $db->transactionCommit(true);
    } catch (\Throwable $e) {
        $db->transactionRollback(true);
        throw $e;
    }
}

public function updateTask(
    int $id,
    string $title,
    string $description,
    string $criteria,
    string $due_at
): bool
{
    $title = trim($title);
    $description = trim($description);
    $criteria = trim($criteria);

    if ($id <= 0 || !$this->getTask($id)) {
        throw new \InvalidArgumentException(
            'Nhiệm vụ không tồn tại.'
        );
    }

    if (
        $title === '' ||
        $description === '' ||
        $criteria === ''
    ) {
        throw new \InvalidArgumentException(
            'Vui lòng nhập đầy đủ thông tin nhiệm vụ.'
        );
    }

    $timezoneName = \Joomla\CMS\Factory::getApplication()
        ->getConfig()
        ->get('offset', 'UTC');

    $timezone = new \DateTimeZone($timezoneName);

    $date = \DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $due_at,
        $timezone
    );

    if (!$date || $date->format('Y-m-d H:i:s') !== $due_at) {
        throw new \InvalidArgumentException(
            'Hạn nộp không hợp lệ.'
        );
    }

    $now = new \DateTimeImmutable('now', $timezone);

    if ($date <= $now) {
        throw new \InvalidArgumentException(
            'Hạn nộp phải lớn hơn thời điểm hiện tại.'
        );
    }


    $db = $this->getDatabase();
    $query = $db->getQuery(true);

    $fields = [
        $db->quoteName('title') . ' = ' . $db->quote($title),
        $db->quoteName('description') . ' = ' . $db->quote($description),
        $db->quoteName('criteria') . ' = ' . $db->quote($criteria),
        $db->quoteName('due_at') . ' = ' . $db->quote($due_at)
    ];

    $query
        ->update($db->quoteName('#__teamflow_tasks'))
        ->set($fields)
        ->where($db->quoteName('id') . ' = ' . $id);

    $db->setQuery($query);
    $db->execute();

    return true;
}

public function getAvailableGroups(): array
{
    $db = $this->getDatabase();

    // Trong lúc chờ tích hợp bảng của Bạn 1,
    // chưa truy vấn nếu bảng chưa tồn tại.
    $table = $db->replacePrefix('#__teamflow_groups');

    $tables = $db->getTableList();

    if (!in_array($table, $tables, true)) {
        return [];
    }

    $query = $db->getQuery(true)
        ->select([
            $db->quoteName('id'),
            $db->quoteName('name')
        ])
        ->from($db->quoteName('#__teamflow_groups'))
        ->where($db->quoteName('is_active') . ' = 1')
        ->order($db->quoteName('name') . ' ASC');

    $db->setQuery($query);

    return $db->loadObjectList() ?: [];
}

public function createTaskWithGroups(
    string $title,
    string $description,
    string $criteria,
    string $due_at,
    int $created_by,
    array $group_ids
): int
{
    $db = $this->getDatabase();

    $db->transactionStart();

    try {
        // Tạo nhiệm vụ mới.
        $taskId = $this->createTask(
            $title,
            $description,
            $criteria,
            $due_at,
            $created_by
        );

        // Chỉ giao nhóm khi có nhóm được chọn.
        if (!empty($group_ids)) {
            $this->assignTaskToGroups(
                $taskId,
                $group_ids
            );
        }

        $db->transactionCommit();

        return $taskId;

    } catch (\Throwable $e) {
        $db->transactionRollback();

        throw $e;
    }
}

public function updateTaskWithGroups(
    int $id,
    string $title,
    string $description,
    string $criteria,
    string $due_at,
    array $group_ids
): bool
{
    if ($id <= 0 || $this->getTask($id) === null) {
        throw new \InvalidArgumentException(
            'Nhiệm vụ không tồn tại.'
        );
    }

    // Kiểm tra ID nhóm.
    $validatedIds = [];

    foreach ($group_ids as $group_id) {
        if (
            filter_var(
                $group_id,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) === false
        ) {
            throw new \InvalidArgumentException(
                'ID nhóm không hợp lệ.'
            );
        }

        $validatedIds[] = (int) $group_id;
    }

    $group_ids = array_values(array_unique($validatedIds));

    // Kiểm tra các nhóm được chọn có tồn tại và hoạt động.
    $availableIds = array_map(
        static fn($group) => (int) $group->id,
        $this->getAvailableGroups()
    );

    foreach ($group_ids as $group_id) {
        if (!in_array($group_id, $availableIds, true)) {
            throw new \InvalidArgumentException(
                'Có nhóm không tồn tại hoặc đã ngừng hoạt động.'
            );
        }
    }

    $db = $this->getDatabase();
    $db->transactionStart();

    try {
        // Cập nhật thông tin nhiệm vụ.
        $this->updateTask(
            $id,
            $title,
            $description,
            $criteria,
            $due_at
        );

        // Xóa các liên kết nhóm cũ của nhiệm vụ này.
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__teamflow_task_groups'))
            ->where(
                $db->quoteName('task_id') . ' = ' . (int) $id
            );

        $db->setQuery($query);
        $db->execute();

        // Lưu các liên kết nhóm mới.
        foreach ($group_ids as $group_id) {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__teamflow_task_groups'))
                ->columns(
                    $db->quoteName(['task_id', 'group_id'])
                )
                ->values(
                    (int) $id . ',' . (int) $group_id
                );

            $db->setQuery($query);
            $db->execute();
        }

        $db->transactionCommit();

        return true;
    } catch (\Throwable $e) {
        $db->transactionRollback();
        throw $e;
    }
}
}
