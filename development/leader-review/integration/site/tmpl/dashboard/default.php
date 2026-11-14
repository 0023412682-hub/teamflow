<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

$cssUrl = Uri::root(true)
    . '/components/com_teamflow/media/css/teamflow-dashboard.css';
?>

<link rel="stylesheet"
      href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">

<div class="teamflow-dashboard">

<header><span class="logo"><b>TF</b>TeamFlow</span><span class="subtitle">Quản lý bài tập nhóm</span><span class="account">🔔 3　│　🎓 Joomla　│　👤 Nhóm trưởng ▾</span></header>
<aside><a href="#">⌂　Tổng quan</a><a href="#">▤　Nhiệm vụ của tôi</a><a href="#" class="active">♧　Nhóm của tôi</a><a href="#reviews">⇩　Bài nộp</a><a href="#">⚙　Quản trị</a></aside>
<main>
<h1>♧ NHÓM CỦA TÔI</h1>
<div class="groupbar"><span><b><?php echo !empty($this->groups)
    ? htmlspecialchars($this->groups[0]->name, ENT_QUOTES, 'UTF-8')
    : 'Chưa có nhóm được phân quyền'; ?></b></span><span>⚑ Đồ án môn Joomla</span><span>♧ 4 thành viên</span><span>▢ Hạn nhóm: 20/01/2025</span><button class="secondary" style="margin-left:auto" type="button" onclick="toast('Cài đặt nhóm thuộc Phần 1 và chưa kết nối trong bản demo.')">⚙ Cài đặt nhóm</button></div>
<div class="metrics"><div class="metric"><span class="ico">▤</span><div><label>Tổng nhiệm vụ</label><strong id="total"><?php echo (int) $this->totalCount; ?></strong></div></div><div class="metric"><span class="ico">ⓥ</span><div><label>Đã hoàn thành</label><strong id="done"><?php echo (int) $this->doneCount; ?></strong></div></div><div class="metric"><span class="ico">◷</span><div><label>Đang thực hiện</label><strong id="active"><?php echo (int) $this->activeCount; ?></strong></div></div><div class="metric"><span class="ico">⌛</span><div><label>Chờ duyệt</label><strong id="waiting"><?php echo (int) $this->pendingCount; ?></strong></div></div></div>
<div class="columns">
<section class="panel">
    <div class="panelhead">
        <h2>♧ Thành viên và phân công</h2>
        <a class="primary" href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_teamflow&view=assignments&tmpl=component'); ?>">
            ♧ Phân công
        </a>
    </div>

    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Thành viên</th>
                    <th>Nhiệm vụ</th>
                    <th>Hạn hoàn thành</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody id="taskRows">
                <?php foreach (($this->assignments ?? []) as $assignment) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) $assignment->member_name, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $assignment->title, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo !empty($assignment->due_at) ? htmlspecialchars((string) $assignment->due_at, ENT_QUOTES, 'UTF-8') : 'Chưa có hạn'; ?></td>
                        <td><?php echo htmlspecialchars((string) ($assignment->status_label ?? "Chưa nộp bài"), ENT_QUOTES, "UTF-8"); ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($this->assignments)) : ?>
                    <tr>
                        <td colspan="4">Chưa có công việc được phân công.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel" id="reviews">
    <div class="panelhead">
        <h2>▤ Bài chờ duyệt
            <span class="chip pending" id="reviewCount">
                <?php echo (int) ($this->pendingCount ?? 0); ?>
            </span>
        </h2>

        <a class="secondary"
           href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_teamflow&view=reviews&tmpl=component'); ?>">
            Xem bài chờ duyệt
        </a>
    </div>

    <div id="reviewArea">
        <?php if (empty($this->pendingCount)) : ?>
            <p>Hiện chưa có bài nộp đang chờ duyệt.</p>
        <?php else : ?>
            <p>Có <?php echo (int) $this->pendingCount; ?> bài đang chờ đánh giá.</p>
        <?php endif; ?>
    </div>
</section></div>
<?php
$totalTasks = (int) ($this->totalCount ?? 0);
$completedTasks = (int) ($this->doneCount ?? 0);
$activeTasks = (int) ($this->activeCount ?? 0);
$pendingTasks = (int) ($this->pendingCount ?? 0);

$percent = $totalTasks > 0
    ? round($completedTasks * 100 / $totalTasks)
    : 0;

$days = $this->progressDays ?? [];
$points = [];

foreach ($days as $i => $day) {
    $x = 48 + $i * (572 / 6);
    $y = 180 - max(0, min(100, (float) $day['percent'])) * 1.4;

    $points[] = [
        'x' => round($x, 1),
        'y' => round($y, 1),
        'label' => (string) $day['label']
    ];
}

$polyline = implode(' ', array_map(
    static function ($point) {
        return $point['x'] . ',' . $point['y'];
    },
    $points
));
?>

<section class="panel">
    <div class="panelhead">
        <h2>▥ Tiến độ nhóm</h2>
        <span class="hint">7 ngày gần nhất</span>
    </div>

    <div class="progress">
        <svg viewBox="0 0 650 220"
             role="img"
             aria-label="Biểu đồ tiến độ nhóm 7 ngày">

            <g stroke="#d6e2ed" stroke-width="1">
                <path d="M48 40H620 M48 75H620 M48 110H620 M48 145H620 M48 180H620"/>
            </g>

            <g fill="#52677d" font-size="12">
                <text x="12" y="44">100%</text>
                <text x="19" y="79">75%</text>
                <text x="19" y="114">50%</text>
                <text x="19" y="149">25%</text>
                <text x="26" y="184">0%</text>
            </g>

            <?php foreach ($points as $point) : ?>
                <text x="<?php echo $point['x']; ?>"
                      y="205"
                      text-anchor="middle"
                      fill="#52677d"
                      font-size="12">
                    <?php echo htmlspecialchars(
                        $point['label'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </text>
            <?php endforeach; ?>

            <polyline
                points="<?php echo htmlspecialchars($polyline, ENT_QUOTES, 'UTF-8'); ?>"
                fill="none"
                stroke="#176eb2"
                stroke-width="3"
                stroke-linejoin="round"
            />

            <?php foreach ($points as $point) : ?>
                <circle
                    cx="<?php echo $point['x']; ?>"
                    cy="<?php echo $point['y']; ?>"
                    r="4"
                    fill="white"
                    stroke="#176eb2"
                    stroke-width="3"
                />
            <?php endforeach; ?>
        </svg>

        <div>
            <div class="progress-side">
                ◎ Tiến độ hiện tại
                <strong><?php echo $percent; ?>%</strong>
            </div>

            <div class="legend">
                <span>✓ Đã hoàn thành: <?php echo $completedTasks; ?></span>
                <span>◷ Đang thực hiện: <?php echo $activeTasks; ?></span>
                <span>⌛ Chờ duyệt: <?php echo $pendingTasks; ?></span>
            </div>
        </div>
    </div>

    <p class="note">
        Tiến độ dựa trên lịch sử bài nộp và kết quả duyệt trong MySQL.
    </p>
</section>
<p class="note">Bản thử nghiệm giao diện Phần 3: dữ liệu mẫu lưu trong trình duyệt trên máy này, chưa kết nối PHP, MySQL hoặc tài khoản Joomla. Có thể thay đổi trạng thái và tạo phân công để thử giao diện.</p>
</main>
<div class="modal" id="modal"><div class="dialog"><h2>Phân công công việc</h2><form id="assignForm"><label for="member">Thành viên thực hiện</label><select id="member" required><option value="">-- Chọn thành viên --</option><option>Nguyễn Thanh</option><option>Trần Long</option><option>Phạm Trang</option><option>Lê Quang</option></select><label for="task_group_id">Nhiệm vụ của nhóm</label><select id="task_group_id" required><option value="1">Bài tập nhóm số 1</option><option value="2">Bài tập nhóm số 2</option></select><label for="title">Tên công việc</label><input id="title" required maxlength="255" placeholder="Ví dụ: Thiết kế giao diện"><label for="description">Mô tả</label><textarea id="description"></textarea><label for="due_at">Hạn hoàn thành (demo)</label><input type="date" id="due_at" required><footer><button type="button" class="secondary" onclick="closeDialog()">Hủy</button><button class="primary" type="submit">Giao nhiệm vụ</button></footer></form></div></div><div class="toast" id="toast" role="status"></div>

</div>
