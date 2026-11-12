<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$user = $app->getIdentity();

$assignmentId = $app->input->getInt('assignment_id', 0);
$submissions = $this->submissions ?? [];

$statusLabels = [
    'pending' => 'Chờ duyệt',
    'approved' => 'Đạt',
    'revision_required' => 'Cần chỉnh sửa'
];

?>

<h2>TeamFlow - Bài nộp</h2>

<?php if ($user->guest) : ?>

    <p>Vui lòng đăng nhập để xem và nộp bài.</p>

<?php else : ?>

    <h3>1. Nộp bài</h3>

    <form
        action="<?php echo Route::_('index.php?option=com_teamflow&task=submissions.submit'); ?>"
        method="post"
    >

        <div>
            <label for="assignment_id">Mã phân công:</label>

            <input
                type="number"
                id="assignment_id"
                name="assignment_id"
                min="1"
                value="<?php echo (int) $assignmentId; ?>"
                required
            >
        </div>

        <div>
            <label for="link_url">Đường dẫn bài làm:</label>

            <input
                type="url"
                id="link_url"
                name="link_url"
                placeholder="https://example.com/bai-lam"
                required
            >
        </div>

        <p>
            <button type="submit">Nộp bài</button>
        </p>

        <?php echo HTMLHelper::_('form.token'); ?>

    </form>

    <hr>

    <h3>2. Lịch sử bài nộp</h3>

    <form method="get" action="index.php">

        <input type="hidden"
               name="option"
               value="com_teamflow">

        <input type="hidden"
               name="view"
               value="submission">

        <label for="history_assignment_id">
            Mã phân công:
        </label>

        <input
            type="number"
            id="history_assignment_id"
            name="assignment_id"
            min="1"
            value="<?php echo (int) $assignmentId; ?>"
            required
        >

        <button type="submit">
            Xem lịch sử
        </button>

    </form>

    <?php if ($assignmentId > 0) : ?>

        <?php if (empty($submissions)) : ?>

            <p>Chưa có bài nộp cho mã phân công này hoặc bạn không có quyền xem.</p>

        <?php else : ?>

            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Lần nộp</th>
                        <th>Thời gian nộp</th>
                        <th>Trạng thái</th>
                        <th>Đường dẫn bài làm</th>
                        <th>Nhận xét nhóm trưởng</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($submissions as $submission) : ?>
                    <?php $submission = (object) $submission; ?>
                    <?php
                    $status = $submission->review_status;

                    $statusText = $statusLabels[$status]
                        ?? $status;

                    $link = (string) ($submission->link_url ?? '');

                    $safeUrl = filter_var(
                        $link,
                        FILTER_VALIDATE_URL
                    ) && in_array(
                        strtolower((string) parse_url($link, PHP_URL_SCHEME)),
                        ['http', 'https'],
                        true
                    );
                    ?>

                    <tr>

                        <td>
                            <?php echo (int) $submission->attempt_no; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars(
                                (string) $submission->submitted_at,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars(
                                $statusText,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </td>

                        <td>
                            <?php if ($safeUrl) : ?>

                                <a
                                    href="<?php echo htmlspecialchars(
                                        $link,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Xem bài làm
                                </a>

                            <?php else : ?>

                                Không có liên kết

                            <?php endif; ?>
                        </td>

                        <td>
                            <?php echo nl2br(htmlspecialchars(
                                (string) ($submission->review_comment ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            )); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>

<?php endif; ?>
