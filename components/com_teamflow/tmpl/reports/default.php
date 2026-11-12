<?php

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;

$stats = $this->stats ?? [];

$labels = [
    'total_assignments' => 'Tổng phân công',
    'submitted' => 'Đã nộp',
    'pending' => 'Chờ duyệt',
    'approved' => 'Đạt yêu cầu',
    'revision_required' => 'Cần chỉnh sửa'
];

?>

<h2>TeamFlow - Báo cáo nhóm</h2>

<p>Thống kê theo lần nộp mới nhất của mỗi phân công.</p>

<table class="table table-striped">
    <thead>
        <tr>
            <th>Chỉ số</th>
            <th>Số lượng</th>
        </tr>
    </thead>

    <tbody>
        <?php foreach ($labels as $key => $label) : ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars(
                        $label,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </td>
                <td>
                    <?php echo (int) ($stats[$key] ?? 0); ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
