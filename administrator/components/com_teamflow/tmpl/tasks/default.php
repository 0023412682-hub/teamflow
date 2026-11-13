<?php
defined('_JEXEC') or die;

 // Lấy danh sách nhiệm vụ thật từ TasksModel.php.
$tasks = [];

foreach (($this->items ?? []) as $item) {
    $tasks[] = [
        'id' => $item->id,
        'title' => $item->title,
        'description' => $item->description,
        'criteria' => $item->criteria,
        'due_at' => $item->due_at,
        'created_by' => $item->created_by,
        'creator_name' => $item->creator_name ?? null,
        'groups' => !empty($this->assignedGroupNames[(int) $item->id])
            ? implode(', ', $this->assignedGroupNames[(int) $item->id])
            : 'Chưa giao nhóm',
        'group_ids' => $this->assignedGroupIdsMap[(int) $item->id] ?? []
    ];
}

$uniqueGroupIds = [];

foreach ($tasks as $task) {
    foreach ($task['group_ids'] as $groupId) {
        $uniqueGroupIds[(int) $groupId] = true;
    }
}

$assignedGroupCount = count($uniqueGroupIds);

$escape = static fn($value) =>
    htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<style>
.teamflow-admin {
    --tf-blue: #0755aa;
    --tf-dark: #102e60;
    --tf-light: #eaf3ff;
    --tf-border: #d5e1ef;
    font-family: Arial, sans-serif;
    color: var(--tf-dark);
    background: #f4f7fb;
    border-radius: 12px;
    overflow: hidden;
}

.teamflow-admin * {
    box-sizing: border-box;
}

.tf-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    padding: 20px 25px;
    background: white;
    border-bottom: 1px solid var(--tf-border);
}

.tf-header h2 {
    margin: 0;
    color: var(--tf-dark);
    font-weight: 700;
}

.tf-brand {
    font-size: 22px;
    color: var(--tf-blue);
    font-weight: bold;
}

.tf-layout {
    display: grid;
    grid-template-columns: 210px minmax(0, 1fr);
    min-height: 520px;
}

.tf-sidebar {
    padding: 18px 12px;
    background: var(--tf-blue);
}

.tf-sidebar div {
    padding: 14px;
    color: white;
    border-radius: 8px;
    margin-bottom: 8px;
}

.tf-sidebar .active {
    background: white;
    color: var(--tf-dark);
    font-weight: bold;
}

.tf-main {
    padding: 22px;
    min-width: 0;
}

.tf-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.tf-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.tf-tab {
    padding: 11px 14px;
    border-radius: 7px;
    background: white;
    color: #53657e;
    border: 1px solid var(--tf-border);
}

.tf-tab.active {
    color: var(--tf-blue);
    border-color: var(--tf-blue);
    font-weight: bold;
}

.tf-button {
    padding: 12px 17px;
    background: var(--tf-blue);
    color: white;
    border: 0;
    border-radius: 8px;
    font-weight: bold;
}

.tf-card {
    background: white;
    border: 1px solid var(--tf-border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

.tf-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.tf-number {
    font-size: 28px;
    color: var(--tf-blue);
    font-weight: bold;
    margin-top: 10px;
}

.tf-search {
    width: 100%;
    max-width: 380px;
    padding: 12px;
    border: 1px solid var(--tf-border);
    border-radius: 8px;
}

.tf-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.tf-table {
    width: 100%;
    min-width: 660px;
    border-collapse: collapse;
}

.tf-table th,
.tf-table td {
    text-align: left;
    padding: 13px;
    border-bottom: 1px solid var(--tf-border);
}

.tf-table th {
    background: var(--tf-light);
}

.tf-action {
    color: var(--tf-blue);
    font-weight: bold;
}

@media (max-width: 850px) {
    .tf-layout {
        grid-template-columns: 1fr;
    }

    .tf-sidebar {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .tf-sidebar div {
        margin: 0;
    }

    .tf-stats {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .tf-header,
    .tf-main {
        padding: 14px;
    }

    .tf-header h2 {
        font-size: 20px;
    }

    .tf-brand {
        font-size: 18px;
    }

    .tf-card {
        padding: 14px;
    }
}

/* TeamFlow - Form tao nhiem vu */
#tf-task-form {
    width: 100%;
    margin-bottom: 20px;
}

#tf-task-form h3 {
    margin: 0 0 22px;
    color: #102e60;
    font-size: 21px;
}

#tf-create-task .tf-form-group {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    margin-bottom: 18px;
}

#tf-create-task .tf-form-group label {
    display: block;
    font-weight: 600;
    color: #102e60;
}

#tf-create-task .tf-form-group input,
#tf-create-task .tf-form-group textarea,
#tf-create-task .tf-form-group select {
    display: block;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding: 12px 14px;
    font: inherit;
    color: #102e60;
    background: white;
    border: 1px solid #d5e1ef;
    border-radius: 8px;
    box-sizing: border-box;
}

#tf-create-task .tf-form-group textarea {
    resize: vertical;
}

#tf-create-task .tf-form-group input:focus,
#tf-create-task .tf-form-group textarea:focus,
#tf-create-task .tf-form-group select:focus {
    outline: 2px solid #0755aa;
    outline-offset: 1px;
}

#tf-create-task .tf-form-group select:disabled {
    background: #f1f5f9;
    color: #64748b;
}

#tf-create-task .tf-form-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

#tf-create-task .tf-cancel-button {
    padding: 12px 20px;
    border: 1px solid #d5e1ef;
    border-radius: 8px;
    background: #f1f5f9;
    color: #102e60;
    font-weight: 600;
    cursor: pointer;
}

#tf-task-form[hidden] {
    display: none !important;
}

/* TeamFlow - Bieu mau sua nhiem vu */
#tf-edit-section[hidden] {
    display: none !important;
}

#tf-edit-section .tf-form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 18px;
}

#tf-edit-section .tf-form-group label {
    font-weight: 600;
    color: #102e60;
}

#tf-edit-section .tf-form-group input,
#tf-edit-section .tf-form-group textarea {
    display: block;
    width: 100%;
    min-width: 0;
    padding: 12px 14px;
    border: 1px solid #d5e1ef;
    border-radius: 8px;
    font: inherit;
    box-sizing: border-box;
}

#tf-edit-section .tf-form-actions {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 12px;
}

.tf-edit-task,
.tf-view-task {
    background: transparent;
    border: 0;
    padding: 0;
    cursor: pointer;
    color: #0755aa;
    font: inherit;
    font-weight: 600;
}

.tf-group-picker {
    border: 1px solid #d5e1ef;
    border-radius: 10px;
    padding: 12px;
    background: #fff;
    max-height: 260px;
    overflow-y: auto;
}

.tf-group-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    margin: 0;
    border-radius: 8px;
    cursor: pointer;
    color: #102e60;
}

.tf-group-option:hover {
    background: #f0f6ff;
}

.tf-group-option:has(input:checked) {
    background: #e8f1ff;
}

.tf-group-option input[type="checkbox"] {
    width: 18px !important;
    height: 18px;
    flex: 0 0 18px;
    margin: 0;
    accent-color: #0755aa;
}

.tf-select-all {
    font-weight: 700;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 8px;
}

.tf-group-count,
.tf-group-empty {
    margin: 10px 12px 2px;
    font-size: 13px;
    color: #64748b;
}
</style>

<div class="teamflow-admin">

    <header class="tf-header">
        <div class="tf-brand">▣ TeamFlow</div>
        <h2>QUẢN TRỊ</h2>
        <div aria-label="Tài khoản">♙</div>
    </header>

    <div class="tf-layout">

        <aside class="tf-sidebar">
            <div>⌂ Tổng quan</div>
            <div>☑ Nhiệm vụ của tôi</div>
            <div>♧ Nhóm của tôi</div>
            <div>▤ Bài nộp</div>
            <div class="active">⚙ Quản trị</div>
        </aside>

        <main class="tf-main">

            <div class="tf-toolbar">
                <div class="tf-tabs">
                    <div class="tf-tab">Quản lý nhóm</div>
                    <div class="tf-tab active">Giao nhiệm vụ</div>
                    <div class="tf-tab">Báo cáo</div>
                </div>


                <button
                    class="tf-button"
                    type="button"
                    id="tf-open-form"
                    aria-controls="tf-task-form"
                    aria-expanded="false"
                >
                    + Giao nhiệm vụ
                </button>

            </div>

            <section class="tf-card" id="tf-task-form" hidden>
                <h3>Tạo nhiệm vụ mới</h3>


                <form
                    id="tf-create-task"
                    action="index.php?option=com_teamflow&task=tasks.create"
                    method="post"
                >
                    <div class="tf-form-group">
                        <label for="tf-title">Tiêu đề nhiệm vụ *</label>
                        <input
                            type="text"
                            id="tf-title"
                            name="title"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="tf-form-group">
                        <label for="tf-description">Mô tả nhiệm vụ *</label>
                        <textarea
                            id="tf-description"
                            name="description"
                            rows="4"
                            required
                        ></textarea>
                    </div>

                    <div class="tf-form-group">
                        <label for="tf-criteria">Tiêu chí đánh giá *</label>
                        <textarea
                            id="tf-criteria"
                            name="criteria"
                            rows="3"
                            required
                        ></textarea>
                    </div>

                    <div class="tf-form-group">
                        <label for="tf-due-at">Hạn nộp *</label>
                        <input
                            type="datetime-local"
                            id="tf-due-at"
                            name="due_at"
                            required
                        >
                    </div>

                    <div class="tf-form-group">
                        <label for="tf-groups">Nhóm được giao</label>


                        <div class="tf-group-picker">
                            <?php if (empty($this->groups)) : ?>
                                <p class="tf-group-empty">
                                    Chưa có nhóm để giao nhiệm vụ.
                                </p>
                            <?php else : ?>

                                <label class="tf-group-option tf-select-all">
                                    <input type="checkbox" id="tf-select-all-groups">
                                    <span>Chọn tất cả nhóm</span>
                                </label>

                                <?php foreach ($this->groups as $group) : ?>
                                    <label class="tf-group-option">
                                        <input
                                            type="checkbox"
                                            class="tf-group-checkbox"
                                            name="group_ids[]"
                                            value="<?php echo (int) $group->id; ?>"
                                        >
                                        <span>
                                            <?php echo $escape($group->name); ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>

                                <p class="tf-group-count">
                                    Đã chọn <strong id="tf-selected-count">0</strong> nhóm
                                </p>

                            <?php endif; ?>
                        </div>


                    </div>

                    <div class="tf-form-actions">
                        <button
                            type="button"
                            class="tf-cancel-button"
                            id="tf-close-form"
                        >
                            Hủy
                        </button>

                        <button type="submit" class="tf-button">
                            Lưu nhiệm vụ
                        </button>
                    </div>

                    <p id="tf-form-message" role="status"></p>

                    <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
                </form>
            </section>

            <div class="tf-stats">

                <section class="tf-card">
                    <strong>Tổng nhiệm vụ</strong>
                    <div class="tf-number">
                        <?php echo count($tasks); ?>
                    </div>
                </section>

                <section class="tf-card">
                    <strong>Nhóm được giao</strong>
                    <div class="tf-number">
                        <?php echo (int) $assignedGroupCount; ?>
                    </div>
                </section>

                <section class="tf-card">
                    <strong>Nhiệm vụ gần đây</strong>
                    <div class="tf-number">
                        <?php echo count($tasks); ?>
                    </div>
                </section>

            </div>

            <section class="tf-card">

                <h3>Danh sách nhiệm vụ đã giao</h3>

                <p>

                    <input
                        class="tf-search"
                        type="search"
                        id="tf-search-task"
                        placeholder="Tìm kiếm nhiệm vụ..."
                        aria-label="Tìm kiếm nhiệm vụ"
                    >

                </p>

                <div class="tf-table-wrap">
                    <table class="tf-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tiêu đề</th>
                                <th>Nhóm</th>
                                <th>Hạn nộp</th>
                                <th>Người tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>

                        <tbody id="tf-task-list">
                            <?php foreach ($tasks as $task) : ?>
                                <tr>
                                    <td>
                                        <?php echo (int) $task['id']; ?>
                                    </td>

                                    <td>
                                        <?php echo $escape($task['title']); ?>
                                    </td>

                                    <td>
                                        <?php echo $escape($task['groups']); ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo $escape(
                                            date(
                                                'd/m/Y',
                                                strtotime($task['due_at'])
                                            )
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo $escape(
                                            $task['creator_name']
                                            ?: 'Tài khoản #' . (int) $task['created_by']
                                        );
                                        ?>
                                    </td>

                                    <td>

                                        <button
                                            type="button"
                                            class="tf-action tf-view-task"
                                            data-task-id="<?php echo (int) $task['id']; ?>"
                                        >
                                            Xem
                                        </button>

                                        <span> · </span>


                                        <button
                                            type="button"
                                            class="tf-action tf-edit-task"
                                            data-task-id="<?php echo (int) $task['id']; ?>"
                                        >
                                            Sửa
                                        </button>


                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </section>

            <section class="tf-card" id="tf-task-detail" hidden>
                <h3>Chi tiết nhiệm vụ</h3>

                <div id="tf-task-detail-content"></div>

                <button
                    type="button"
                    class="tf-cancel-button"
                    id="tf-close-detail"
                >
                    Đóng
                </button>
            </section>

            <section class="tf-card" id="tf-edit-section" hidden>
                <h3>Sửa nhiệm vụ</h3>

                <form
                    id="tf-edit-task-form"
                    action="index.php?option=com_teamflow&task=tasks.update"
                    method="post"
                >
                    <input type="hidden" id="tf-edit-id" name="id">

                    <div class="tf-form-group">
                        <label for="tf-edit-title">Tiêu đề nhiệm vụ *</label>
                        <input
                            type="text"
                            id="tf-edit-title"
                            name="title"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="tf-form-group">
                        <label for="tf-edit-description">Mô tả nhiệm vụ *</label>
                        <textarea
                            id="tf-edit-description"
                            name="description"
                            rows="4"
                            required
                        ></textarea>
                    </div>

                    <div class="tf-form-group">
                        <label for="tf-edit-criteria">Tiêu chí đánh giá *</label>
                        <textarea
                            id="tf-edit-criteria"
                            name="criteria"
                            rows="3"
                            required
                        ></textarea>
                    </div>

                    <div class="tf-form-group">
                        <label for="tf-edit-due-at">Hạn nộp *</label>
                        <input
                            type="datetime-local"
                            id="tf-edit-due-at"
                            name="due_at"
                            required
                        >
                    </div>

                    <div class="tf-form-group">
                        <label>Nhóm được giao</label>

                        <div class="tf-group-picker">
                            <?php if (empty($this->groups)) : ?>
                                <p class="tf-group-empty">
                                    Chưa có nhóm để giao nhiệm vụ.
                                </p>
                            <?php else : ?>

                                <label class="tf-group-option tf-select-all">
                                    <input
                                        type="checkbox"
                                        id="tf-edit-select-all-groups"
                                    >
                                    <span>Chọn tất cả nhóm</span>
                                </label>

                                <?php foreach ($this->groups as $group) : ?>
                                    <label class="tf-group-option">
                                        <input
                                            type="checkbox"
                                            class="tf-edit-group-checkbox"
                                            name="group_ids[]"
                                            value="<?php echo (int) $group->id; ?>"
                                        >
                                        <span>
                                            <?php echo $escape($group->name); ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>

                                <p class="tf-group-count">
                                    Đã chọn <strong id="tf-edit-selected-count">0</strong> nhóm
                                </p>

                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tf-form-actions">
                        <button
                            type="button"
                            class="tf-cancel-button"
                            id="tf-cancel-edit"
                        >
                            Hủy
                        </button>

                        <button type="submit" class="tf-button">
                            Lưu thay đổi
                        </button>
                    </div>

                    <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
                </form>
            </section>


            <script type="application/json" id="tf-task-data">
            <?php
            echo json_encode(
                $tasks,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
            );
            ?>
            </script>

        </main>
    </div>
</div>


<script>
(function () {
    function setupTaskForm() {
        const openButton = document.getElementById('tf-open-form');
        const closeButton = document.getElementById('tf-close-form');
        const formSection = document.getElementById('tf-task-form');
        const form = document.getElementById('tf-create-task');

        if (!openButton || !formSection) {
            console.error('TeamFlow: Không tìm thấy nút hoặc biểu mẫu');
            return;
        }

        openButton.addEventListener('click', function () {
            formSection.hidden = false;
            openButton.setAttribute('aria-expanded', 'true');

            formSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });


        if (closeButton) {
            closeButton.addEventListener('click', function () {
                formSection.hidden = true;
                openButton.setAttribute('aria-expanded', 'false');

                if (form) {
                    form.reset();
                }

                updateGroupSelection();
            });
        }


        // Tim kiem nhiem vu theo tieu de
        const searchInput = document.getElementById('tf-search-task');
        const taskRows = document.querySelectorAll('#tf-task-list tr');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const keyword = this.value.trim().toLowerCase();

                taskRows.forEach(function (row) {
                    const titleCell = row.cells[1];

                    if (!titleCell) {
                        return;
                    }

                    const title = titleCell.textContent.toLowerCase();
                    row.hidden = !title.includes(keyword);
                });
            });
        }

        // Xem chi tiết nhiệm vụ
        const taskDataElement = document.getElementById('tf-task-data');
        const detailSection = document.getElementById('tf-task-detail');
        const detailContent = document.getElementById('tf-task-detail-content');
        const closeDetail = document.getElementById('tf-close-detail');

        if (taskDataElement && detailSection && detailContent) {
            const tasksData = JSON.parse(taskDataElement.textContent);

            document.querySelectorAll('.tf-view-task').forEach(function (button) {
                button.addEventListener('click', function () {
                    const taskId = Number(this.dataset.taskId);
                    const task = tasksData.find(function (item) {
                        return Number(item.id) === taskId;
                    });

                    if (!task) {
                        return;
                    }

                    // Tạo nội dung bằng textContent để an toàn
                    detailContent.replaceChildren();

                    const fields = [
                        ['Tiêu đề', task.title],
                        ['Mô tả', task.description],
                        ['Tiêu chí đánh giá', task.criteria],
                        ['Hạn nộp', task.due_at],
                        ['Người tạo', task.creator_name || 'Tài khoản #' + task.created_by],
                        ['Nhóm được giao', task.groups]
                    ];

                    fields.forEach(function (field) {
                        const p = document.createElement('p');
                        const strong = document.createElement('strong');

                        strong.textContent = field[0] + ': ';
                        p.appendChild(strong);
                        p.appendChild(document.createTextNode(field[1] || ''));
                        detailContent.appendChild(p);
                    });

                    detailSection.hidden = false;
                    detailSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            });

            if (closeDetail) {
                closeDetail.addEventListener('click', function () {
                    detailSection.hidden = true;
                });
            }
        }

        // Mo bieu mau Sua nhiem vu
        const editSection = document.getElementById('tf-edit-section');
        const cancelEdit = document.getElementById('tf-cancel-edit');
        const editDataElement = document.getElementById('tf-task-data');

        if (editSection && editDataElement) {
            const editTasksData = JSON.parse(editDataElement.textContent);

            document.querySelectorAll('.tf-edit-task').forEach(function (button) {
                button.addEventListener('click', function () {
                    const taskId = Number(this.dataset.taskId);

                    const task = editTasksData.find(function (item) {
                        return Number(item.id) === taskId;
                    });

                    if (!task) {
                        alert('Không tìm thấy nhiệm vụ.');
                        return;
                    }

                    document.getElementById('tf-edit-id').value = task.id;
                    document.getElementById('tf-edit-title').value = task.title;
                    document.getElementById('tf-edit-description').value = task.description;
                    document.getElementById('tf-edit-criteria').value = task.criteria;

                    document.getElementById('tf-edit-due-at').value =
                        String(task.due_at || '').replace(' ', 'T').slice(0, 16);

                    const assignedIds = (task.group_ids || []).map(Number);

                    document.querySelectorAll('.tf-edit-group-checkbox')
                        .forEach(function (checkbox) {
                            checkbox.checked = assignedIds.includes(
                                Number(checkbox.value)
                            );
                        });

                    document.dispatchEvent(
                        new Event('tf-edit-groups-updated')
                    );


                    editSection.hidden = false;
                    editSection.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                });
            });

            if (cancelEdit) {
                cancelEdit.addEventListener('click', function () {
                    editSection.hidden = true;
                });
            }
        }

        // Chon nhom bang checkbox
        const groupCheckboxes = Array.from(
            document.querySelectorAll('.tf-group-checkbox')
        );

        const selectAllGroups = document.getElementById(
            'tf-select-all-groups'
        );

        const selectedCount = document.getElementById(
            'tf-selected-count'
        );

        function updateGroupSelection() {
            const checkedCount = groupCheckboxes.filter(
                checkbox => checkbox.checked
            ).length;

            if (selectedCount) {
                selectedCount.textContent = checkedCount;
            }

            if (selectAllGroups) {
                selectAllGroups.checked =
                    groupCheckboxes.length > 0 &&
                    checkedCount === groupCheckboxes.length;

                selectAllGroups.indeterminate =
                    checkedCount > 0 &&
                    checkedCount < groupCheckboxes.length;
            }
        }

        groupCheckboxes.forEach(function (checkbox) {
            checkbox.addEventListener(
                'change',
                updateGroupSelection
            );
        });

        if (selectAllGroups) {
            selectAllGroups.addEventListener('change', function () {
                groupCheckboxes.forEach(function (checkbox) {
                    checkbox.checked = selectAllGroups.checked;
                });

                updateGroupSelection();
            });
        }

        updateGroupSelection();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupTaskForm);
    } else {
        setupTaskForm();
    }
})();

(function () {
    function setupEditGroupSelection() {
        const checkboxes = Array.from(
            document.querySelectorAll('.tf-edit-group-checkbox')
        );

        const selectAll = document.getElementById(
            'tf-edit-select-all-groups'
        );

        const selectedCount = document.getElementById(
            'tf-edit-selected-count'
        );

        function updateSelection() {
            const count = checkboxes.filter(
                checkbox => checkbox.checked
            ).length;

            if (selectedCount) {
                selectedCount.textContent = count;
            }

            if (selectAll) {
                selectAll.checked =
                    checkboxes.length > 0 &&
                    count === checkboxes.length;

                selectAll.indeterminate =
                    count > 0 &&
                    count < checkboxes.length;
            }
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener(
                'change',
                updateSelection
            );
        });

        if (selectAll) {
            selectAll.addEventListener(
                'change',
                function () {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = selectAll.checked;
                    });

                    updateSelection();
                }
            );
        }

        document.addEventListener(
            'tf-edit-groups-updated',
            updateSelection
        );

        updateSelection();
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            setupEditGroupSelection
        );
    } else {
        setupEditGroupSelection();
    }
})();
</script>
