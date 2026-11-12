
<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$submissions = $this->submissions ?? [];
?>

<div class="teamflow-reviews">
    <h2>Bài chờ duyệt</h2>

    <?php if (empty($submissions)) : ?>
        <div class="alert alert-info">
            Không có bài đang chờ duyệt.
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

                        <label for="review_comment_<?php echo (int) $submission->id; ?>">
                            Nhận xét
                        </label>

                        <textarea
                            name="review_comment"
                            id="review_comment_<?php echo (int) $submission->id; ?>"
                            class="form-control mb-3"
                            rows="3"
                            placeholder="Nhập nhận xét bài nộp"
                        ></textarea>

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
                            class="btn btn-outline-primary"
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
