
<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();

$taskGroups = $this->task_groups ?? [];
$members = $this->members ?? [];
$assignments = $this->assignments ?? [];

$stats = $this->assignmentStats ?? [
    'total' => 0,
    'approved' => 0,
    'pending' => 0,
    'revision_required' => 0,
    'in_progress' => 0
];

$escape = static function ($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$formatDate = static function ($value): string {
    if (empty($value) || $value === '0000-00-00') {
        return 'Chưa đặt hạn';
    }

    try {
        return (new \DateTimeImmutable($value))->format('d/m/Y');
    } catch (\Exception $e) {
        return 'Ngày không hợp lệ';
    }
};

// Bo loc GET
$filterMember = $app->input->getInt('tf_member', 0);
$filterStatus = $app->input->getCmd('tf_status', '');
$filterDue = $app->input->getCmd('tf_due', '');

if (!in_array($filterStatus, [
    '',
    'approved',
    'pending',
    'revision_required',
    'in_progress'
], true)) {
    $filterStatus = '';
}

if (!in_array($filterDue, [
    '',
    'overdue',
    'upcoming',
    'no_due'
], true)) {
    $filterDue = '';
}

$today = new \DateTimeImmutable('today');
$todayString = $today->format('Y-m-d');
$upcomingString = $today->modify('+7 days')->format('Y-m-d');

// Chi loc tren danh sach da duoc HtmlView kiem tra quyen.
$filteredAssignments = array_filter(
    $assignments,
    static function ($a) use (
        $filterMember,
        $filterStatus,
        $filterDue,
        $todayString,
        $upcomingString
    ): bool {

        if (
            $filterMember > 0
            && (int) $a->assignee_user_id !== $filterMember
        ) {
            return false;
        }

        if (
            $filterStatus !== ''
            && (string) ($a->status ?? '') !== $filterStatus
        ) {
            return false;
        }

        $due = (string) ($a->due_at ?? '');

        if ($filterDue === 'no_due' && $due !== '') {
            return false;
        }

        if ($filterDue === 'overdue') {
            if (
                $due === ''
                || $due >= $todayString
                || ($a->status ?? '') === 'approved'
            ) {
                return false;
            }
        }

        if ($filterDue === 'upcoming') {
            if (
                $due === ''
                || $due < $todayString
                || $due > $upcomingString
                || ($a->status ?? '') === 'approved'
            ) {
                return false;
            }
        }

        return true;
    }
);

$statusColors = [
    'approved' => ['#dcf8ea', '#16734a'],
    'pending' => ['#f0e7ff', '#7248b3'],
    'revision_required' => ['#fff0dc', '#aa690e'],
    'in_progress' => ['#e8f3ff', '#135ba4']
];

?>

<div style="max-width:950px;margin:25px auto;font-family:Arial,sans-serif;">

    <!-- BIEU MAU PHAN CONG -->
    <section style="max-width:650px;margin:0 auto 25px;padding:25px;background:#fff;border:1px solid #cbdcf2;border-radius:15px;box-shadow:0 10px 30px rgba(0,0,0,.08);box-sizing:border-box;">

        <h2 style="color:#12345e;margin:0 0 7px;">
            Phân công nhiệm vụ
        </h2>

        <p style="font-size:13px;color:#607991;margin-bottom:20px;">
            Giao công việc cho thành viên trong nhóm.
        </p>

        <?php if (empty($taskGroups) || empty($members)) : ?>

            <p style="padding:15px;background:#fff3cd;border-radius:8px;">
                Chưa có nhiệm vụ nhóm hoặc thành viên hợp lệ để phân công.
            </p>

        <?php else : ?>

            <form
                action="<?php echo Route::_('index.php?option=com_teamflow&task=assignments.assign'); ?>"
                method="post"
            >

                <?php if (count($taskGroups) === 1) : ?>

                    <input type="hidden"
                           name="task_group_id"
                           value="<?php echo (int) $taskGroups[0]->id; ?>">

                <?php else : ?>

                    <label style="display:block;margin:15px 0 7px;">
                        Nhiệm vụ nhóm *
                    </label>

                    <select name="task_group_id" required
                            style="width:100%;padding:11px;border:1px solid #bfd1e7;border-radius:8px;box-sizing:border-box;">

                        <option value="">Chọn nhiệm vụ nhóm</option>

                        <?php foreach ($taskGroups as $group) : ?>
                            <option value="<?php echo (int) $group->id; ?>">
                                <?php echo $escape($group->title); ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                <?php endif; ?>

                <label style="display:block;margin:18px 0 7px;">
                    Thành viên *
                </label>

                <select name="assignee_user_id" required
                        style="width:100%;padding:11px;border:1px solid #bfd1e7;border-radius:8px;box-sizing:border-box;">

                    <option value="">Chọn thành viên</option>

                    <?php foreach ($members as $member) : ?>
                        <option value="<?php echo (int) $member->id; ?>">
                            <?php echo $escape($member->name); ?>
                        </option>
                    <?php endforeach; ?>

                </select>

                <label style="display:block;margin:18px 0 7px;">
                    Tên nhiệm vụ *
                </label>

                <input name="title"
                       type="text"
                       maxlength="255"
                       required
                       placeholder="Nhập tên nhiệm vụ"
                       style="width:100%;padding:11px;border:1px solid #bfd1e7;border-radius:8px;box-sizing:border-box;">

                <label style="display:block;margin:18px 0 7px;">
                    Hạn hoàn thành *
                </label>

                <input name="due_at"
                       type="date"
                       required
                       style="width:100%;padding:11px;border:1px solid #bfd1e7;border-radius:8px;box-sizing:border-box;">

                <input type="hidden" name="description" value="">

                <?php echo HTMLHelper::_('form.token'); ?>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:23px;flex-wrap:wrap;">

                    <a href="<?php echo Route::_('index.php?option=com_teamflow&view=dashboard'); ?>"
                       style="padding:11px 19px;background:#f5f9ff;border:1px solid #bfd1e7;border-radius:8px;color:#17365e;text-decoration:none;">
                        Hủy
                    </a>

                    <button type="submit"
                            style="padding:11px 19px;background:#086eb9;color:white;border:0;border-radius:8px;font-weight:bold;cursor:pointer;">
                        Phân công nhiệm vụ
                    </button>

                </div>
            </form>

        <?php endif; ?>

    </section>

    <!-- THONG KE NHIEM VU -->
    <section style="margin-bottom:22px;">

        <h2 style="font-size:20px;color:#173960;margin:0 0 14px;">
            Thống kê tiến độ nhóm
        </h2>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;">

            <?php
            $cards = [
                ['Tổng nhiệm vụ', 'total', '#e8f3ff', '#086eb9'],
                ['Đã hoàn thành', 'approved', '#e2f8ec', '#178451'],
                ['Chờ duyệt', 'pending', '#f1e9ff', '#7950ad'],
                ['Cần chỉnh sửa', 'revision_required', '#fff0dc', '#ac7019']
            ];
            ?>

            <?php foreach ($cards as $card) : ?>

                <div style="background:<?php echo $card[2]; ?>;padding:18px;border-radius:13px;border:1px solid #dfebf5;">

                    <div style="font-size:13px;color:#49627d;margin-bottom:9px;">
                        <?php echo $escape($card[0]); ?>
                    </div>

                    <strong style="font-size:30px;color:<?php echo $card[3]; ?>;">
                        <?php echo (int) ($stats[$card[1]] ?? 0); ?>
                    </strong>

                </div>

            <?php endforeach; ?>

        </div>

        <p style="font-size:12px;color:#71849a;margin:10px 0 0;">
            Đang thực hiện (chưa có bài nộp):
            <?php echo (int) ($stats['in_progress'] ?? 0); ?> nhiệm vụ.
        </p>

    </section>

    <!-- DANH SACH VA BO LOC -->
    <section style="background:#fff;padding:20px;border:1px solid #cbdcf2;border-radius:15px;box-sizing:border-box;">

        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:15px;">

            <h2 style="font-size:20px;color:#173960;margin:0;">
                Danh sách nhiệm vụ đã phân công
            </h2>

            <span style="background:#e8f3ff;color:#086eb9;border-radius:20px;padding:7px 12px;font-size:13px;font-weight:bold;">
                Tổng: <?php echo (int) ($stats['total'] ?? 0); ?> nhiệm vụ
            </span>
        </div>

        <!-- BO LOC -->
        <form action="<?php echo Route::_('index.php'); ?>"
              method="get"
              style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;background:#f5f9ff;border:1px solid #e0ebf7;border-radius:10px;padding:13px;margin-bottom:15px;">

            <input type="hidden" name="option" value="com_teamflow">
            <input type="hidden" name="view" value="assignments">

            <div style="flex:1 1 165px;">
                <label style="display:block;font-size:12px;margin-bottom:5px;">
                    Thành viên
                </label>

                <select name="tf_member"
                        style="width:100%;padding:9px;border:1px solid #bfd1e7;border-radius:7px;">

                    <option value="0">Tất cả thành viên</option>

                    <?php foreach ($members as $member) : ?>
                        <option value="<?php echo (int) $member->id; ?>"
                            <?php echo $filterMember === (int) $member->id ? 'selected' : ''; ?>>
                            <?php echo $escape($member->name); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div style="flex:1 1 160px;">
                <label style="display:block;font-size:12px;margin-bottom:5px;">
                    Trạng thái
                </label>

                <select name="tf_status"
                        style="width:100%;padding:9px;border:1px solid #bfd1e7;border-radius:7px;">

                    <?php
                    $statusOptions = [
                        '' => 'Tất cả trạng thái',
                        'approved' => 'Đã hoàn thành',
                        'pending' => 'Chờ duyệt',
                        'revision_required' => 'Cần chỉnh sửa',
                        'in_progress' => 'Đang thực hiện'
                    ];
                    ?>

                    <?php foreach ($statusOptions as $value => $label) : ?>
                        <option value="<?php echo $escape($value); ?>"
                            <?php echo $filterStatus === $value ? 'selected' : ''; ?>>
                            <?php echo $escape($label); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div style="flex:1 1 150px;">
                <label style="display:block;font-size:12px;margin-bottom:5px;">
                    Hạn hoàn thành
                </label>

                <select name="tf_due"
                        style="width:100%;padding:9px;border:1px solid #bfd1e7;border-radius:7px;">

                    <?php
                    $dueOptions = [
                        '' => 'Tất cả hạn',
                        'upcoming' => 'Trong 7 ngày tới',
                        'overdue' => 'Quá hạn',
                        'no_due' => 'Chưa đặt hạn'
                    ];
                    ?>

                    <?php foreach ($dueOptions as $value => $label) : ?>
                        <option value="<?php echo $escape($value); ?>"
                            <?php echo $filterDue === $value ? 'selected' : ''; ?>>
                            <?php echo $escape($label); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <button type="submit"
                    style="padding:10px 16px;background:#086eb9;color:#fff;border:0;border-radius:7px;cursor:pointer;">
                Lọc
            </button>

            <a href="<?php echo Route::_('index.php?option=com_teamflow&view=assignments'); ?>"
               style="padding:9px 13px;border:1px solid #bfd1e7;border-radius:7px;background:white;color:#173960;text-decoration:none;">
                Xóa lọc
            </a>

        </form>

        <p style="font-size:12px;color:#607991;">
            Đang hiển thị <?php echo count($filteredAssignments); ?>
            / <?php echo count($assignments); ?> nhiệm vụ.
        </p>

        <?php if (empty($filteredAssignments)) : ?>

            <p style="padding:16px;text-align:center;background:#f5f9ff;border-radius:8px;color:#607991;">
                Không tìm thấy nhiệm vụ phù hợp.
            </p>

        <?php else : ?>

            <div style="overflow-x:auto;">

                <table style="width:100%;min-width:680px;border-collapse:collapse;font-size:13px;">

                    <thead>
                        <tr style="background:#deebff;color:#244566;">
                            <th style="padding:12px 9px;text-align:left;border:1px solid #d2e1f2;">Thành viên</th>
                            <th style="padding:12px 9px;text-align:left;border:1px solid #d2e1f2;">Nhiệm vụ</th>
                            <th style="padding:12px 9px;text-align:left;border:1px solid #d2e1f2;">Hạn hoàn thành</th>
                            <th style="padding:12px 9px;text-align:left;border:1px solid #d2e1f2;">Trạng thái</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($filteredAssignments as $assignment) : ?>

                            <?php
                            $status = (string) ($assignment->status ?? 'in_progress');
                            $colors = $statusColors[$status]
                                ?? $statusColors['in_progress'];
                            ?>

                            <tr>
                                <td style="padding:12px 9px;border:1px solid #dce7f4;">
                                    <?php echo $escape($assignment->member_name); ?>
                                </td>

                                <td style="padding:12px 9px;border:1px solid #dce7f4;">
                                    <?php echo $escape($assignment->title); ?>
                                </td>

                                <td style="padding:12px 9px;border:1px solid #dce7f4;">
                                    <?php echo $escape($formatDate($assignment->due_at)); ?>
                                </td>

                                <td style="padding:12px 9px;border:1px solid #dce7f4;">
                                    <span style="display:inline-block;padding:7px 10px;border-radius:18px;white-space:nowrap;background:<?php echo $colors[0]; ?>;color:<?php echo $colors[1]; ?>;">
                                        <?php echo $escape($assignment->status_label ?? 'Đang thực hiện'); ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>
                </table>

            </div>

        <?php endif; ?>

    </section>

</div>
