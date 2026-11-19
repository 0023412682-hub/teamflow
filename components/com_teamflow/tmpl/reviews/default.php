
<?php

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$submissions = $this->submissions ?? [];
$history = $this->reviewHistory ?? [];

$escape = static function ($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
};

$formatDate = static function ($value) use ($escape): string {
    if (empty($value)) {
        return 'Chưa có';
    }

    try {
        return $escape(
            (new \DateTimeImmutable($value))->format('d/m/Y H:i')
        );
    } catch (\Exception $e) {
        return 'Ngày không hợp lệ';
    }
};

?>

<div class="teamflow-reviews"
     style="max-width:1000px;margin:20px auto;font-family:Arial,sans-serif;">

    <div class="tf-reviews-report-head" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <h2 style="font-size:23px;color:#173960;margin:0;">Bài nộp chờ duyệt</h2>
        <a href="<?php echo Route::_('index.php?option=com_teamflow&task=reports.export'); ?>"
           style="display:inline-flex;align-items:center;gap:9px;background:#086eb9;color:#fff;text-decoration:none;border-radius:8px;padding:11px 16px;font-weight:bold;font-size:13px;">
            Xuất báo cáo Excel (.xlsx)
        </a>
    </div>

    <?php if (empty($submissions)) : ?>

        <div class="alert alert-info">
            Hiện chưa có bài nộp đang chờ duyệt.
        </div>

    <?php else : ?>

        <?php foreach ($submissions as $submission) : ?>

            <section style="background:#fff;border:1px solid #cbdcf2;border-radius:13px;padding:20px;margin-bottom:18px;box-shadow:0 5px 16px rgba(17,90,170,.06);">

                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:15px;">

                    <h3 style="margin:0;color:#173960;font-size:19px;">
                        <?php echo $escape($submission->assignment_title); ?>
                    </h3>

                    <span style="background:#fff0dc;color:#a56611;padding:7px 11px;border-radius:20px;font-size:12px;font-weight:bold;">
                        Chờ duyệt
                    </span>

                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr));gap:12px;margin-bottom:16px;font-size:13px;color:#365572;">

                    <div>
                        <strong>Thành viên:</strong>
                        <?php echo $escape($submission->member_name); ?>
                    </div>

                    <div>
                        <strong>Lần nộp:</strong>
                        <?php echo (int) $submission->attempt_no; ?>
                    </div>

                    <div>
                        <strong>Thời gian nộp:</strong>
                        <?php echo $formatDate($submission->submitted_at); ?>
                    </div>

                    <div>
                        <strong>Mã bài nộp:</strong>
                        #<?php echo (int) $submission->id; ?>
                    </div>

                </div>

                <div style="background:#f4f9ff;border:1px solid #deebf7;border-radius:9px;padding:13px;margin-bottom:17px;font-size:13px;">

                    <strong style="color:#173960;display:block;margin-bottom:9px;">
                        Nội dung bài nộp
                    </strong>

                    <?php
                    $link = trim((string) ($submission->link_url ?? ''));
                    $scheme = strtolower(
                        (string) parse_url($link, PHP_URL_SCHEME)
                    );
                    ?>

                    <?php if ($link !== '' && in_array($scheme, ['http', 'https'], true)) : ?>

                        <p style="margin:0 0 9px;">
                            <a href="<?php echo $escape($link); ?>"
                               target="_blank"
                               rel="noopener noreferrer">
                                Xem liên kết bài nộp
                            </a>
                        </p>

                    <?php elseif ($link !== '') : ?>

                        <p style="margin:0 0 9px;color:#8b641b;">
                            Liên kết bài nộp không hợp lệ.
                        </p>

                    <?php endif; ?>

                    <?php if (!empty($submission->file_path)) : ?>

                        <p style="margin:0;overflow-wrap:anywhere;">
                            <strong>Tệp đã nộp:</strong>
                            <?php echo $escape($submission->file_path); ?>
                        </p>

                    <?php endif; ?>

                    <?php if ($link === '' && empty($submission->file_path)) : ?>

                        <p style="margin:0;color:#71849a;">
                            Chưa có thông tin tệp hoặc liên kết.
                        </p>

                    <?php endif; ?>

                </div>

                <form action="<?php echo Route::_(
                    'index.php?option=com_teamflow&task=reviews.review'
                ); ?>" method="post">

                    <input type="hidden"
                           name="id"
                           value="<?php echo (int) $submission->id; ?>">

                    <label for="tf-review-<?php echo (int) $submission->id; ?>"
                           style="display:block;font-size:13px;font-weight:bold;color:#173960;margin-bottom:8px;">
                        Nhận xét của nhóm trưởng
                    </label>

                    <textarea
                        id="tf-review-<?php echo (int) $submission->id; ?>"
                        name="review_comment"
                        rows="4"
                        class="form-control"
                        placeholder="Nhập nhận xét bài nộp..."
                        style="width:100%;box-sizing:border-box;border:1px solid #bdd0e7;border-radius:8px;padding:11px;margin-bottom:13px;"
                    ></textarea>

                    <?php echo HTMLHelper::_('form.token'); ?>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;">

                        <button type="submit"
                                name="review_status"
                                value="approved"
                                style="background:#e3f8ec;border:1px solid #4aad7a;border-radius:8px;color:#187347;padding:11px 16px;font-weight:bold;cursor:pointer;">
                            Đạt
                        </button>

                        <button type="submit"
                                name="review_status"
                                value="revision_required"
                                style="background:#fff0dc;border:1px solid #d9a04a;border-radius:8px;color:#955b09;padding:11px 16px;font-weight:bold;cursor:pointer;">
                            Yêu cầu chỉnh sửa
                        </button>

                    </div>

                    <p style="font-size:12px;color:#71849a;margin:12px 0 0;">
                        Khi yêu cầu chỉnh sửa, bạn cần nhập nhận xét để thành viên biết nội dung cần sửa.
                    </p>

                </form>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>


    <!-- LICH SU DUYET BAI -->
    <section style="margin-top:25px;">

        <h2 style="font-size:22px;color:#173960;margin-bottom:15px;">
            Lịch sử duyệt bài
        </h2>

        <?php if (empty($history)) : ?>

            <div class="alert alert-info">
                Chưa có lịch sử duyệt bài.
            </div>

        <?php else : ?>

            <div style="overflow-x:auto;">

                <table class="table table-bordered table-striped"
                       style="min-width:650px;font-size:13px;">

                    <thead>
                        <tr>
                            <th>Thành viên</th>
                            <th>Nhiệm vụ</th>
                            <th>Lần nộp</th>
                            <th>Kết quả</th>
                            <th>Nhận xét</th>
                            <th>Thời gian duyệt</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($history as $item) : ?>

                            <tr>

                                <td>
                                    <?php echo $escape($item->member_name ?? ''); ?>
                                </td>

                                <td>
                                    <?php echo $escape($item->assignment_title ?? ''); ?>
                                </td>

                                <td>
                                    <?php echo (int) $item->attempt_no; ?>
                                </td>

                                <td>
                                    <?php if ($item->review_status === 'approved') : ?>
                                        <span style="color:#187347;font-weight:bold;">
                                            Đạt
                                        </span>
                                    <?php else : ?>
                                        <span style="color:#955b09;font-weight:bold;">
                                            Cần chỉnh sửa
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php echo nl2br(
                                        $escape($item->review_comment ?? '')
                                    ); ?>
                                </td>

                                <td>
                                    <?php echo $formatDate($item->reviewed_at ?? ''); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </section>

</div>

<section class="tf-member-statistics"
    style="margin:25px 0;padding:20px;background:#fff;border:1px solid #cbdcf2;border-radius:12px;">

    <h2 style="color:#173960;font-size:21px;margin:0 0 8px;">
        Thống kê kết quả theo thành viên
    </h2>

    <p style="color:#607991;font-size:13px;margin-bottom:16px;">
        Tổng hợp nhiệm vụ được giao và số lượt duyệt của từng thành viên.
    </p>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#deebff;color:#173960;">
                    <th style="padding:12px;border:1px solid #d2e1f2;text-align:left;">
                        Thành viên
                    </th>
                    <th style="padding:12px;border:1px solid #d2e1f2;">
                        Nhiệm vụ
                    </th>
                    <th style="padding:12px;border:1px solid #d2e1f2;">
                        Lượt đạt
                    </th>
                    <th style="padding:12px;border:1px solid #d2e1f2;">
                        Lượt cần sửa
                    </th>
                </tr>
            </thead>

            <tbody>
                <?php foreach (($this->memberStats ?? []) as $stat) : ?>
                    <tr>
                        <td style="padding:12px;border:1px solid #dce7f4;">
                            <?php echo htmlspecialchars(
                                (string) $stat->member_name,
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </td>

                        <td style="padding:12px;border:1px solid #dce7f4;text-align:center;">
                            <?php echo (int) $stat->assignment_count; ?>
                        </td>

                        <td style="padding:12px;border:1px solid #dce7f4;text-align:center;color:#087d43;font-weight:bold;">
                            <?php echo (int) $stat->approved_count; ?>
                        </td>

                        <td style="padding:12px;border:1px solid #dce7f4;text-align:center;color:#aa690e;font-weight:bold;">
                            <?php echo (int) $stat->revision_count; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($this->memberStats)) : ?>
                    <tr>
                        <td colspan="4" style="padding:16px;text-align:center;">
                            Chưa có dữ liệu phân công.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
