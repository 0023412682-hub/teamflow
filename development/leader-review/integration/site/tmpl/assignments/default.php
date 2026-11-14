
<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$taskGroups = $this->task_groups ?? [];
$members = $this->members ?? [];
?>

<div class="teamflow-assignments">
    <h2>Phân công nhiệm vụ</h2>

    <?php if (empty($taskGroups) || empty($members)) : ?>
        <div class="alert alert-warning">
            Chưa có nhiệm vụ hoặc thành viên thuộc nhóm
            do bạn quản lý.
        </div>
    <?php else : ?>

        <form
            action="<?php echo Route::_(
                'index.php?option=com_teamflow&task=assignments.assign'
            ); ?>"
            method="post"
        >
            <div class="mb-3">
                <label for="task_group_id">
                    Nhiệm vụ của nhóm
                </label>

                <select
                    name="task_group_id"
                    id="task_group_id"
                    class="form-select"
                    required
                >
                    <option value="">-- Chọn nhiệm vụ --</option>

                    <?php foreach ($taskGroups as $taskGroup) : ?>
                        <option value="<?php echo (int) $taskGroup->id; ?>">
                            <?php echo htmlspecialchars(
                                (string) $taskGroup->title,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="assignee_user_id">
                    Thành viên được giao
                </label>

                <select
                    name="assignee_user_id"
                    id="assignee_user_id"
                    class="form-select"
                    required
                >
                    <option value="">-- Chọn thành viên --</option>

                    <?php foreach ($members as $member) : ?>
                        <option value="<?php echo (int) $member->id; ?>">
                            <?php echo htmlspecialchars(
                                (string) $member->name,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="title">
                    Tên công việc
                </label>

                <input
                    type="text"
                    name="title"
                    id="title"
                    class="form-control"
                    maxlength="255"
                    required
                >
            </div>

            <div class="mb-3">
                <label for="description">
                    Mô tả công việc
                </label>

                <textarea
                    name="description"
                    id="description"
                    class="form-control"
                    rows="4"
                ></textarea>
            </div>

            <?php echo HTMLHelper::_('form.token'); ?>

            <button type="submit" class="btn btn-primary">
                Giao nhiệm vụ
            </button>
        </form>

    <?php endif; ?>
</div>
