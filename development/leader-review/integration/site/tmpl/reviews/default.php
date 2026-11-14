
<?php
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$submissions = $this->submissions ?? [];
?>

<div class="teamflow-reviews">
    <h2>Bài nộp chờ duyệt</h2>

    <?php if (empty($submissions)) : ?>
        <div class="alert alert-info">
            Hiện chưa có bài nộp đang chờ duyệt.
        </div>
    <?php else : ?>

        <?php foreach ($submissions as $submission) : ?>
            <div class="card mb-3">
                <div class="card-body">

                    <h3 class="h5">
                        Bài nộp #<?php echo (int) $submission->id; ?>
                    </h3>

                    <p>
                        Lần nộp:
                        <?php echo (int) $submission->attempt_no; ?>
                    </p>

                    <p>
                        Thời gian nộp:
                        <?php echo htmlspecialchars(
                            (string) $submission->submitted_at,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </p>

                    <?php if (!empty($submission->link_url)) : ?>
                        <p>
                            <a
                                href="<?php echo htmlspecialchars(
                                    (string) $submission->link_url,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Xem liên kết bài nộp
                            </a>
                        </p>
                    <?php endif; ?>

                    <form
                        action="<?php echo Route::_(
                            'index.php?option=com_teamflow&task=reviews.review'
                        ); ?>"
                        method="post"
                    >
                        <input
                            type="hidden"
                            name="id"
                            value="<?php echo (int) $submission->id; ?>"
                        >

                        <div class="mb-3">
                            <label
                                for="review_comment_<?php echo (int) $submission->id; ?>"
                            >
                                Nhận xét của nhóm trưởng
                            </label>

                            <textarea
                                name="review_comment"
                                id="review_comment_<?php echo (int) $submission->id; ?>"
                                class="form-control"
                                rows="3"
                                placeholder="Nhập nhận xét bài nộp"
                            ></textarea>
                        </div>

                        <button
                            type="submit"
                            name="review_status"
                            value="approved"
                            class="btn btn-success"
                        >
                            Đạt
                        </button>

                        <button
                            type="submit"
                            name="review_status"
                            value="revision_required"
                            class="btn btn-warning"
                        >
                            Yêu cầu chỉnh sửa
                        </button>

                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>

                </div>
            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>


<section id="teamflow-review-history" class="mt-4">
    <h2>Lịch sử duyệt bài</h2>

    <?php $history = $this->reviewHistory ?? []; ?>

    <?php if (empty($history)) : ?>
        <div class="alert alert-info">
            Chưa có lịch sử duyệt bài.
        </div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
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
                                <?php echo htmlspecialchars(
                                    (string) ($item->member_name ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(
                                    (string) ($item->assignment_title ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>
                            <td>
                                <?php echo (int) $item->attempt_no; ?>
                            </td>
                            <td>
                                <?php
                                $status = (string) $item->review_status;

                                echo $status === 'approved'
                                    ? 'Đạt'
                                    : 'Cần chỉnh sửa';
                                ?>
                            </td>
                            <td>
                                <?php echo nl2br(htmlspecialchars(
                                    (string) ($item->review_comment ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                )); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars(
                                    (string) ($item->reviewed_at ?? ''),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>