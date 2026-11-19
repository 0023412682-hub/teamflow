<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$escape = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$formatDate = static function ($value) use ($escape): string {
    if (empty($value) || $value === '0000-00-00') {
        return 'Chưa đặt hạn';
    }
    try {
        return $escape((new \DateTimeImmutable((string) $value))->format('d/m/Y'));
    } catch (\Exception $e) {
        return 'Ngày không hợp lệ';
    }
};

$root = Uri::root(true);
$cssUrl = $root . '/components/com_teamflow/media/css/teamflow-dashboard.css';
$iconRoot = $root . '/images/teamflow-icons/';
$dashboardUrl = Route::_('index.php?option=com_teamflow&view=dashboard');
$assignUrl = Route::_('index.php?option=com_teamflow&view=assignments');
$reviewsUrl = Route::_('index.php?option=com_teamflow&view=reviews');
$user = Factory::getApplication()->getIdentity();

$groups = $this->groups ?? [];
$taskGroups = $this->task_groups ?? [];
$members = $this->members ?? [];
$assignments = $this->assignments ?? [];
// Bộ lọc riêng cho danh sách phân công trên Dashboard.
// HtmlView hiện cung cấp member_name và status_label, chưa cung cấp ID/trạng thái thô.
// Lọc theo tên hiển thị và nhãn trạng thái để không phải thay đổi truy vấn đang chạy.
$input = Factory::getApplication()->input;
$filterMember = trim($input->getString('tf_dash_member', ''));
$filterStatus = $input->getCmd('tf_dash_status', '');
$filterDue = $input->getCmd('tf_dash_due', '');
$statusMap = [
    'Đã hoàn thành' => 'approved',
    'Chờ duyệt' => 'pending',
    'Cần chỉnh sửa' => 'revision_required',
    'Chưa nộp bài' => 'not_submitted',
];
$allowedStatus = ['', 'approved', 'pending', 'revision_required', 'not_submitted'];
$allowedDue = ['', 'upcoming', 'overdue', 'no_due'];
if (!in_array($filterStatus, $allowedStatus, true)) {
    $filterStatus = '';
}
if (!in_array($filterDue, $allowedDue, true)) {
    $filterDue = '';
}
$memberNames = [];
foreach ($assignments as $task) {
    $name = trim((string) ($task->member_name ?? ''));
    if ($name !== '') {
        $memberNames[$name] = true;
    }
}
$memberNames = array_keys($memberNames);
sort($memberNames, SORT_NATURAL | SORT_FLAG_CASE);
if ($filterMember !== '' && !in_array($filterMember, $memberNames, true)) {
    $filterMember = '';
}
$todayFilter = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');
$upcomingFilter = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Ho_Chi_Minh')))->modify('+7 days')->format('Y-m-d');
$filteredAssignments = array_values(array_filter($assignments, static function ($assignment) use (
    $filterMember, $filterStatus, $filterDue, $statusMap, $todayFilter, $upcomingFilter
): bool {
    if ($filterMember !== '' && (string) ($assignment->member_name ?? '') !== $filterMember) {
        return false;
    }
    $status = $statusMap[(string) ($assignment->status_label ?? 'Chưa nộp bài')] ?? 'not_submitted';
    if ($filterStatus !== '' && $status !== $filterStatus) {
        return false;
    }
    $due = substr((string) ($assignment->due_at ?? ''), 0, 10);
    $hasDue = $due !== '' && $due !== '0000-00-00';
    if ($filterDue === 'no_due' && $hasDue) {
        return false;
    }
    if ($filterDue === 'overdue' && (!$hasDue || $due >= $todayFilter || $status === 'approved')) {
        return false;
    }
    if ($filterDue === 'upcoming' && (!$hasDue || $due < $todayFilter || $due > $upcomingFilter || $status === 'approved')) {
        return false;
    }
    return true;
}));
$totalTasks = (int) ($this->totalCount ?? 0);
$completedTasks = (int) ($this->doneCount ?? 0);
$activeTasks = (int) ($this->activeCount ?? 0);
$pendingTasks = (int) ($this->pendingCount ?? 0);
$percent = $totalTasks > 0 ? round($completedTasks * 100 / $totalTasks) : 0;
// Khung thời gian biểu đồ được kiểm tra ở HtmlView; chỉ cho phép 7, 14, 30.
$chartPeriod = (int) ($this->progressPeriod ?? 7);
if (!in_array($chartPeriod, [7, 14, 30], true)) {
    $chartPeriod = 7;
}
$days = $this->progressDays ?? [];
$chartPointCount = count($days);
$labelStep = $chartPeriod === 30 ? 5 : ($chartPeriod === 14 ? 2 : 1);
$points = [];
foreach ($days as $i => $day) {
    $x = 48 + $i * (572 / max(1, $chartPointCount - 1));
    $y = 180 - max(0, min(100, (float) ($day['percent'] ?? 0))) * 1.4;
    $points[] = [
        'x' => round($x, 1),
        'y' => round($y, 1),
        'label' => (string) ($day['label'] ?? ''),
        'show_label' => $i === 0 || $i === $chartPointCount - 1 || $i % $labelStep === 0
    ];
}
$polyline = implode(' ', array_map(
    static fn ($point) => $point['x'] . ',' . $point['y'],
    $points
));
$areaPoints = $points
    ? '48,180 ' . $polyline . ' 620,180'
    : '';
// Thông tin thanh nhóm: sử dụng dữ liệu thật của người quản lý.
$displayGroup = $groups[0] ?? null;
$groupMemberCount = count($members);
$nearestDue = null;
$todayForDue = (new \DateTimeImmutable('today', new \DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');
foreach ($assignments as $groupAssignment) {
    $dateValue = substr(trim((string) ($groupAssignment->due_at ?? '')), 0, 10);
    if ($dateValue === '' || $dateValue === '0000-00-00') {
        continue;
    }
    $dateObject = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $dateValue) {
        continue;
    }
    if ($dateValue >= $todayForDue && ($nearestDue === null || $dateValue < $nearestDue)) {
        $nearestDue = $dateValue;
    }
}
$nearestDueLabel = $nearestDue ? $formatDate($nearestDue) : 'Không có hạn sắp tới';

$completedShare = $totalTasks > 0 ? (int) round($completedTasks * 100 / $totalTasks) : 0;
$activeShare = $totalTasks > 0 ? (int) round($activeTasks * 100 / $totalTasks) : 0;
$pendingShare = $totalTasks > 0 ? (int) round($pendingTasks * 100 / $totalTasks) : 0;
?>
<link rel="stylesheet" href="<?php echo $escape($cssUrl); ?>">
<div class="teamflow-dashboard">
    <header>
        <span class="logo"><b>TF</b>TeamFlow</span>
        <span class="subtitle">Quản lý bài tập nhóm</span>
        <span class="account"><?php echo $escape($user->name ?? 'Tài khoản'); ?> (Nhóm trưởng)</span>
    </header>

    <aside>
        <a href="<?php echo $escape($dashboardUrl); ?>"><img class="tf-nav-icon" src="<?php echo $escape($iconRoot . 'Process_result.png'); ?>" alt="" loading="eager"> Tổng quan nhóm</a>
        <a href="<?php echo $escape($assignUrl); ?>"><img class="tf-nav-icon" src="<?php echo $escape($iconRoot . 'task_list.png'); ?>" alt="" loading="eager"> Nhiệm vụ và phân công</a>
        <a href="<?php echo $escape($dashboardUrl); ?>" class="active">
            <img src="<?php echo $escape($iconRoot . 'group.png'); ?>" alt="" style="width:23px;height:23px;object-fit:contain;vertical-align:middle;"> Nhóm của tôi
        </a>
        <a href="<?php echo $escape($reviewsUrl); ?>"><img class="tf-nav-icon" src="<?php echo $escape($iconRoot . 'duyet.png'); ?>" alt="" loading="eager"> Bài nộp chờ duyệt</a>
    </aside>

    <main>
        <h1>
            <img src="<?php echo $escape($iconRoot . 'group.png'); ?>" alt="" style="width:32px;height:32px;object-fit:contain;vertical-align:middle;">
            NHÓM CỦA TÔI
        </h1>

        <div class="groupbar tf-group-info">
            <?php if ($displayGroup) : ?>
                <div class="tf-group-info-item tf-group-name">
                    <img src="<?php echo $escape($iconRoot . 'group.png'); ?>" alt="" class="tf-group-info-icon">
                    <strong><?php echo $escape($displayGroup->name); ?></strong>
                </div>
                <div class="tf-group-info-item">
                    <img src="<?php echo $escape($iconRoot . 'project.png'); ?>" alt="" class="tf-group-info-icon">
                    <span><?php echo $escape(trim((string) ($displayGroup->description ?? '')) ?: 'Chưa có mô tả nhóm'); ?></span>
                </div>
                <div class="tf-group-info-item">
                    <img src="<?php echo $escape($iconRoot . 'group.png'); ?>" alt="" class="tf-group-info-icon">
                    <span><?php echo $groupMemberCount; ?> thành viên<?php echo count($groups) > 1 ? ' (các nhóm)' : ''; ?></span>
                </div>
                <div class="tf-group-info-item">
                    <img src="<?php echo $escape($iconRoot . 'Creation Time.png'); ?>" alt="" class="tf-group-info-icon">
                    <span>Hạn nhiệm vụ sắp tới: <?php echo $escape($nearestDueLabel); ?></span>
                </div>
                <?php if (count($groups) > 1) : ?>
                    <div class="tf-group-info-multiple">Đang quản lý <?php echo count($groups); ?> nhóm; thông tin mô tả thuộc nhóm đầu tiên.</div>
                <?php endif; ?>
            <?php else : ?>
                <div class="tf-group-info-item"><span><strong>Chưa có nhóm được phân quyền</strong></span></div>
            <?php endif; ?>
        </div>

        <div class="metrics">
            <div class="metric"><img class="tf-metric-icon" src="<?php echo $escape($iconRoot . 'overall_task.png'); ?>" alt="" loading="eager"><div><label>Tổng nhiệm vụ</label><strong><?php echo $totalTasks; ?></strong></div></div>
            <div class="metric"><img class="tf-metric-icon" src="<?php echo $escape($iconRoot . 'comlete.png'); ?>" alt="" loading="eager"><div><label>Đã hoàn thành</label><strong><?php echo $completedTasks; ?></strong></div></div>
            <div class="metric"><img class="tf-metric-icon" src="<?php echo $escape($iconRoot . 'progre.png'); ?>" alt="" loading="eager"><div><label>Đang thực hiện</label><strong><?php echo $activeTasks; ?></strong></div></div>
            <div class="metric"><img class="tf-metric-icon" src="<?php echo $escape($iconRoot . 'wait.png'); ?>" alt="" loading="eager"><div><label>Chờ duyệt</label><strong><?php echo $pendingTasks; ?></strong></div></div>
        </div>

        <div class="columns">
            <section class="panel">
                <div class="panelhead">
                    <h2><img class="tf-section-icon" src="<?php echo $escape($iconRoot . 'group.png'); ?>" alt="" loading="eager"> Thành viên và phân công</h2>
                    <button class="primary" type="button" id="tf-open-assign" aria-haspopup="dialog" aria-controls="tf-assignment-modal">
                        <img src="<?php echo $escape($iconRoot . 'setting.png'); ?>" alt="" style="width:23px;height:23px;object-fit:contain;vertical-align:middle;">
                        Phân công
                    </button>
                </div>
                <!-- Bộ lọc hiển thị trực tiếp, chỉ tác động đến bảng phân công. -->
                <form action="<?php echo Route::_('index.php'); ?>" method="get" class="tf-dash-filter">
                    <span class="tf-filter-caption"><img class="tf-small-icon" src="<?php echo $escape($iconRoot . 'filter.png'); ?>" alt="" loading="eager"> Bộ lọc</span>
                    <input type="hidden" name="option" value="com_teamflow">
                    <input type="hidden" name="view" value="dashboard">
                    <input type="hidden" name="tf_days" value="<?php echo $chartPeriod; ?>">
                    <div class="tf-dash-filter-field">
                        <label for="tf-dash-member">Thành viên</label>
                        <select id="tf-dash-member" name="tf_dash_member">
                            <option value="">Tất cả thành viên</option>
                            <?php foreach ($memberNames as $name) : ?>
                                <option value="<?php echo $escape($name); ?>" <?php echo $filterMember === $name ? 'selected' : ''; ?>><?php echo $escape($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tf-dash-filter-field">
                        <label for="tf-dash-status">Trạng thái</label>
                        <select id="tf-dash-status" name="tf_dash_status">
                            <?php foreach (['' => 'Tất cả trạng thái', 'approved' => 'Đã hoàn thành', 'pending' => 'Chờ duyệt', 'revision_required' => 'Cần chỉnh sửa', 'not_submitted' => 'Chưa nộp bài'] as $value => $label) : ?>
                                <option value="<?php echo $escape($value); ?>" <?php echo $filterStatus === $value ? 'selected' : ''; ?>><?php echo $escape($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tf-dash-filter-field">
                        <label for="tf-dash-due">Hạn hoàn thành</label>
                        <select id="tf-dash-due" name="tf_dash_due">
                            <?php foreach (['' => 'Tất cả hạn', 'upcoming' => 'Trong 7 ngày tới', 'overdue' => 'Quá hạn', 'no_due' => 'Chưa đặt hạn'] as $value => $label) : ?>
                                <option value="<?php echo $escape($value); ?>" <?php echo $filterDue === $value ? 'selected' : ''; ?>><?php echo $escape($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tf-dash-filter-buttons">
                        <button type="submit" class="tf-dash-filter-apply">Lọc</button>
                        <a class="tf-dash-filter-reset" href="<?php echo $escape($dashboardUrl); ?>">Xóa lọc</a>
                    </div>
                </form>
                <p class="tf-dash-filter-count">Đang hiển thị <?php echo count($filteredAssignments); ?> / <?php echo count($assignments); ?> nhiệm vụ.</p>
                <div class="tablewrap">
                    <table>
                        <thead>
                        <tr><th>Thành viên</th><th>Nhiệm vụ</th><th>Hạn hoàn thành</th><th>Trạng thái</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($filteredAssignments as $assignment) : ?>
                            <tr>
                                <td><?php echo $escape($assignment->member_name ?? ''); ?></td>
                                <td><?php echo $escape($assignment->title ?? ''); ?></td>
                                <td><?php echo $formatDate($assignment->due_at ?? null); ?></td>
                                <td><?php echo $escape($assignment->status_label ?? 'Chưa nộp bài'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($filteredAssignments)) : ?>
                            <tr><td colspan="4"><?php echo empty($assignments) ? 'Chưa có nhiệm vụ được phân công.' : 'Không tìm thấy nhiệm vụ phù hợp với bộ lọc.'; ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel tf-dashboard-review-panel" id="reviews">
                <div class="panelhead">
                    <h2><img class="tf-section-icon" src="<?php echo $escape($iconRoot . 'duyet.png'); ?>" alt="" loading="eager"> Bài chờ duyệt <span class="chip pending"><?php echo $pendingTasks; ?></span></h2>
                </div>
                <?php $pendingSubmissions = $this->pendingSubmissions ?? []; ?>
                <?php if (empty($pendingSubmissions)) : ?>
                    <p class="note">Hiện chưa có bài nộp đang chờ duyệt.</p>
                <?php else : ?>
                    <?php foreach ($pendingSubmissions as $submission) : ?>
                        <article class="tf-pending-card">
                            <div class="tf-pending-head">
                                <strong><?php echo $escape($submission->assignment_title ?? 'Nhiệm vụ'); ?></strong>
                                <span class="tf-pending-tag">Chờ duyệt</span>
                            </div>
                            <div class="tf-pending-meta">
                                <span>Thành viên: <b><?php echo $escape($submission->member_name ?? ''); ?></b></span>
                                <span>Lần nộp: <?php echo (int) ($submission->attempt_no ?? 0); ?></span>
                                <span>Thời gian nộp: <?php echo $escape($submission->submitted_at ?? ''); ?></span>
                                <span>Mã bài nộp: #<?php echo (int) $submission->id; ?></span>
                            </div>
                            <div class="tf-pending-content">
                                <strong><img class="tf-small-icon" src="<?php echo $escape($iconRoot . 'function.png'); ?>" alt="" loading="eager"> Nội dung bài nộp</strong>
                                <?php
                                $link = trim((string) ($submission->link_url ?? ''));
                                $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
                                $filePath = trim((string) ($submission->file_path ?? ''));
                                ?>
                                <?php if ($link !== '' && in_array($scheme, ['http','https'], true)) : ?>
                                    <a href="<?php echo $escape($link); ?>" target="_blank" rel="noopener noreferrer">Xem liên kết bài nộp</a>
                                <?php elseif ($link !== '') : ?>
                                    <span>Đường dẫn bài nộp không hợp lệ.</span>
                                <?php endif; ?>
                                <?php if ($filePath !== '') : ?>
                                    <span>Tệp đã nộp: <?php echo $escape($filePath); ?></span>
                                <?php endif; ?>
                                <?php if ($link === '' && $filePath === '') : ?>
                                    <span>Không có tệp hoặc liên kết.</span>
                                <?php endif; ?>
                            </div>
                            <form action="<?php echo Route::_('index.php?option=com_teamflow&task=reviews.review'); ?>" method="post">
                                <input type="hidden" name="id" value="<?php echo (int) $submission->id; ?>">
                                <label class="tf-review-label" for="tf-review-note-<?php echo (int) $submission->id; ?>"><img class="tf-small-icon" src="<?php echo $escape($iconRoot . 'comment.png'); ?>" alt="" loading="eager"> Nhận xét của nhóm trưởng</label>
                                <textarea class="tf-review-note" id="tf-review-note-<?php echo (int) $submission->id; ?>" name="review_comment" rows="4" placeholder="Nhập nhận xét bài nộp..."></textarea>
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <div class="tf-review-buttons">
                                    <button type="submit" name="review_status" value="approved" class="tf-review-approve"><img class="tf-button-icon" src="<?php echo $escape($iconRoot . 'comlete.png'); ?>" alt="" loading="eager"> Đạt</button>
                                    <button type="submit" name="review_status" value="revision_required" class="tf-review-revise"><img class="tf-button-icon" src="<?php echo $escape($iconRoot . 'pen.png'); ?>" alt="" loading="eager"> Yêu cầu chỉnh sửa</button>
                                </div>
                                <p class="tf-review-help">Khi yêu cầu chỉnh sửa, cần nhập nhận xét để thành viên biết nội dung cần sửa.</p>
                            </form>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <section class="panel tf-progress-panel">
            <div class="panelhead tf-progress-heading">
                <h2><img class="tf-section-icon" src="<?php echo $escape($iconRoot . 'Process_result.png'); ?>" alt="" loading="eager"> Tiến độ nhóm</h2>
                <form class="tf-period-form" action="<?php echo $escape($dashboardUrl); ?>" method="get">
                    <input type="hidden" name="option" value="com_teamflow">
                    <input type="hidden" name="view" value="dashboard">
                    <?php if ($filterMember !== '') : ?><input type="hidden" name="tf_dash_member" value="<?php echo $escape($filterMember); ?>"><?php endif; ?>
                    <?php if ($filterStatus !== '') : ?><input type="hidden" name="tf_dash_status" value="<?php echo $escape($filterStatus); ?>"><?php endif; ?>
                    <?php if ($filterDue !== '') : ?><input type="hidden" name="tf_dash_due" value="<?php echo $escape($filterDue); ?>"><?php endif; ?>
                    <label class="tf-period-icon" for="tf-progress-period" aria-label="Chọn khoảng thời gian"><img class="tf-small-icon" src="<?php echo $escape($iconRoot . 'Creation Time.png'); ?>" alt="" loading="eager"></label>
                    <select class="tf-period-select" id="tf-progress-period" name="tf_days" aria-label="Khoảng thời gian biểu đồ" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                        <?php foreach ([7, 14, 30] as $periodOption) : ?>
                            <option value="<?php echo $periodOption; ?>" <?php echo $chartPeriod === $periodOption ? 'selected' : ''; ?>><?php echo $periodOption; ?> ngày gần nhất</option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit">Xem</button></noscript>
                </form>
            </div>
            <div class="tf-progress-chart">
                <div class="tf-chart-graph">
                    <svg class="tf-progress-svg" viewBox="0 0 650 220" role="img" aria-label="Biểu đồ tỷ lệ nhiệm vụ đã duyệt đạt trong <?php echo $chartPeriod; ?> ngày">
                        <defs>
                            <linearGradient id="tf-chart-fill" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#61b7f1" stop-opacity=".31"/>
                                <stop offset="100%" stop-color="#61b7f1" stop-opacity=".045"/>
                            </linearGradient>
                        </defs>
                        <g stroke="#d6e2ed" stroke-width="1"><path d="M48 40H620 M48 75H620 M48 110H620 M48 145H620 M48 180H620"/></g>
                        <g fill="#52677d" font-size="12">
                            <text x="12" y="44">100%</text><text x="19" y="79">75%</text>
                            <text x="19" y="114">50%</text><text x="19" y="149">25%</text>
                            <text x="26" y="184">0%</text>
                        </g>
                        <?php foreach ($points as $point) : ?>
                            <?php if ($point['show_label']) : ?>
                                <text x="<?php echo $point['x']; ?>" y="205" text-anchor="middle" fill="#52677d" font-size="12"><?php echo $escape($point['label']); ?></text>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($points) : ?>
                            <polygon points="<?php echo $escape($areaPoints); ?>" fill="url(#tf-chart-fill)"/>
                            <polyline points="<?php echo $escape($polyline); ?>" fill="none" stroke="#176eb2" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <?php foreach ($points as $i => $point) : ?>
                                <circle cx="<?php echo $point['x']; ?>" cy="<?php echo $point['y']; ?>" r="<?php echo $chartPeriod === 30 ? '2.5' : '4.4'; ?>" fill="white" stroke="#176eb2" stroke-width="<?php echo $chartPeriod === 30 ? '2' : '3'; ?>">
                                    <title><?php echo $escape($days[$i]['label'] ?? ''); ?>: <?php echo (int) ($days[$i]['percent'] ?? 0); ?>%</title>
                                </circle>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </svg>
                    <div class="tf-chart-axis-label">Ngày</div>
                </div>
                <div class="tf-progress-overview">
                    <div class="tf-current-progress">
                        <div class="tf-current-progress-icon" aria-hidden="true"><img class="tf-target-icon" src="<?php echo $escape($iconRoot . 'Target.png'); ?>" alt="" loading="eager"></div>
                        <div>Tiến độ hiện tại</div>
                        <strong><?php echo (int) $percent; ?>%</strong>
                        <small>Theo <?php echo $totalTasks; ?> nhiệm vụ hiện tại</small>
                    </div>
                    <div class="tf-progress-states">
                        <div class="tf-state-card">
                            <span class="tf-state-symbol tf-state-done" aria-hidden="true"><img class="tf-state-icon" src="<?php echo $escape($iconRoot . 'comlete.png'); ?>" alt="" loading="eager"></span>
                            <span>Đã hoàn thành: <b><?php echo $completedTasks; ?></b> (<?php echo $completedShare; ?>%)</span>
                        </div>
                        <div class="tf-state-card">
                            <span class="tf-state-symbol tf-state-active" aria-hidden="true"><img class="tf-state-icon" src="<?php echo $escape($iconRoot . 'progre.png'); ?>" alt="" loading="eager"></span>
                            <span>Đang thực hiện / cần sửa: <b><?php echo $activeTasks; ?></b> (<?php echo $activeShare; ?>%)</span>
                        </div>
                        <div class="tf-state-card">
                            <span class="tf-state-symbol tf-state-pending" aria-hidden="true"><img class="tf-state-icon" src="<?php echo $escape($iconRoot . 'wait.png'); ?>" alt="" loading="eager"></span>
                            <span>Chờ duyệt: <b><?php echo $pendingTasks; ?></b> (<?php echo $pendingShare; ?>%)</span>
                        </div>
                    </div>
                </div>
            </div>
            <p class="note tf-progress-note">Biểu đồ hiển thị <?php echo $chartPeriod; ?> ngày gần nhất. Dữ liệu các ngày trước được tái dựng từ thời điểm nộp và duyệt hiện có; tổng nhiệm vụ hiện tại được dùng làm mẫu số. Đây chưa phải lịch sử đầy đủ nếu nhiệm vụ từng được thêm hoặc xóa.</p>
        </section>
    </main>
</div>

<style>
/* Thanh thông tin nhóm: biểu tượng PNG và vạch chia như ảnh tham khảo. */
.teamflow-dashboard .groupbar.tf-group-info{display:flex!important;flex-wrap:wrap;align-items:center;gap:0 0;background:#fff;border:1px solid #c7ddfa;border-radius:13px;padding:12px 15px;min-height:52px;box-sizing:border-box}
.teamflow-dashboard .tf-group-info-item{display:inline-flex;align-items:center;gap:10px;min-width:0;padding:4px 17px;margin:0!important;border-left:1px solid #cbdcf2;color:#143c72;font-size:13px;line-height:1.4;background:transparent!important;white-space:normal}
.teamflow-dashboard .tf-group-info-item:first-child{border-left:0;padding-left:0}
.teamflow-dashboard .tf-group-info-icon{display:block;width:23px;height:23px;object-fit:contain;flex:0 0 23px}
.teamflow-dashboard .tf-group-name strong{font-weight:700;color:#08356d}
.teamflow-dashboard .tf-group-info-multiple{flex-basis:100%;padding:6px 0 0;color:#607991;font-size:11px}
@media(max-width:720px){.teamflow-dashboard .groupbar.tf-group-info{gap:8px}.teamflow-dashboard .tf-group-info-item{border-left:0;padding:4px 8px 4px 0}}
</style>
<style>
/* Bộ lọc nhiệm vụ trên Dashboard
/* TEAMFLOW PNG ICONS: dung file anh goc, khong thay bang SVG/emoji/font-icon. */
.teamflow-dashboard .tf-nav-icon{width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:7px;flex-shrink:0}
.teamflow-dashboard .tf-metric-icon{width:43px;height:43px;object-fit:contain;flex-shrink:0;margin-right:12px}
.teamflow-dashboard .metric{display:flex;align-items:center;gap:10px;min-width:0}
.teamflow-dashboard .metric>div{min-width:0}
.teamflow-dashboard .tf-section-icon{width:27px;height:27px;object-fit:contain;vertical-align:middle;margin-right:8px}
.teamflow-dashboard .tf-small-icon{width:21px;height:21px;object-fit:contain;vertical-align:middle;margin-right:6px}
.teamflow-dashboard .tf-button-icon{width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:5px}
.teamflow-dashboard .tf-filter-caption{display:inline-flex;align-items:center;gap:2px;flex:0 0 auto;color:#173960;font-size:13px;font-weight:bold;white-space:nowrap}
.teamflow-dashboard .tf-current-progress-icon{width:auto;height:auto;border:0!important;border-radius:0!important;line-height:normal!important;display:flex;justify-content:center;align-items:center;margin-bottom:10px}
.teamflow-dashboard .tf-target-icon{width:46px;height:46px;object-fit:contain}
.teamflow-dashboard .tf-state-symbol{flex:0 0 36px!important;width:36px!important;height:36px!important;border-radius:0!important;background:transparent!important}
.teamflow-dashboard .tf-state-icon{width:33px;height:33px;object-fit:contain}
.teamflow-dashboard .tf-period-icon{display:flex;align-items:center;justify-content:center}
.teamflow-dashboard .tf-review-buttons button{display:inline-flex;align-items:center;justify-content:center;gap:3px}
@media(max-width:640px){.teamflow-dashboard .tf-metric-icon{width:34px;height:34px}.teamflow-dashboard .tf-section-icon{width:23px;height:23px}}

/* Bộ lọc nhiệm vụ trên Dashboard (không thay đổi các thành phần khác). */
.teamflow-dashboard .tf-dash-filter{display:flex;align-items:end;flex-wrap:wrap;gap:10px;background:#f5f9ff;border:1px solid #e0ebf7;border-radius:10px;padding:12px;margin:10px 0 8px}
.teamflow-dashboard .tf-dash-filter-field{flex:1 1 145px;min-width:0}
.teamflow-dashboard .tf-dash-filter-field label{display:block;font-size:12px;font-weight:normal;margin:0 0 5px;color:#173960}
.teamflow-dashboard .tf-dash-filter-field select{display:block;width:100%;height:37px;min-width:0;padding:6px 9px;border:1px solid #bfd1e7;border-radius:7px;background:#fff;color:#173960;font:13px Arial,sans-serif;box-sizing:border-box}
.teamflow-dashboard .tf-dash-filter-buttons{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
.teamflow-dashboard .tf-dash-filter-apply{display:inline-block;background:#086eb9;color:#fff;border:0;border-radius:7px;padding:10px 14px;font:13px Arial,sans-serif;cursor:pointer}
.teamflow-dashboard .tf-dash-filter-reset{display:inline-block;background:#fff;color:#173960;border:1px solid #bfd1e7;border-radius:7px;padding:9px 11px;text-decoration:none;font:13px Arial,sans-serif}
.teamflow-dashboard .tf-dash-filter-count{font-size:12px;color:#607991;margin:7px 0 10px}
.teamflow-dashboard .columns > .panel:first-child{container-type:inline-size}
/* Căn hàng bộ lọc: nhãn, ba trường chọn và hai nút trên cùng một dòng. */
.teamflow-dashboard .tf-dash-filter{
  display:grid!important;
  grid-template-columns: minmax(58px,72px) repeat(3,minmax(0,1fr)) max-content;
  align-items:end;
  column-gap:8px;
  row-gap:8px;
  width:100%;
  box-sizing:border-box;
}
.teamflow-dashboard .tf-dash-filter .tf-filter-caption{
  align-self:end;
  min-height:37px;
  display:flex;
  align-items:center;
  margin:0;
}
.teamflow-dashboard .tf-dash-filter .tf-dash-filter-field{
  min-width:0;
  width:100%;
  margin:0;
}
.teamflow-dashboard .tf-dash-filter .tf-dash-filter-field select{
  width:100%;
  min-width:0;
}
.teamflow-dashboard .tf-dash-filter .tf-dash-filter-buttons{
  display:flex;
  flex-wrap:nowrap;
  align-items:center;
  gap:6px;
  white-space:nowrap;
  min-height:37px;
}
.teamflow-dashboard .tf-dash-filter .tf-dash-filter-apply,
.teamflow-dashboard .tf-dash-filter .tf-dash-filter-reset{
  min-height:37px;
  box-sizing:border-box;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  padding:7px 9px;
}
/* Khi khung quá hẹp, hai nút chuyển xuống một hàng thay vì đè lên ô chọn. */
@container (max-width: 560px){
  .teamflow-dashboard .tf-dash-filter{grid-template-columns:repeat(3,minmax(0,1fr))}
  .teamflow-dashboard .tf-dash-filter .tf-filter-caption{grid-column:1 / -1;min-height:24px}
  .teamflow-dashboard .tf-dash-filter .tf-dash-filter-buttons{grid-column:1 / -1;justify-content:flex-end}
}

/* Tiến độ nhóm: bố cục 3 khối giống mẫu, giữ dữ liệu Joomla thực tế. */
.teamflow-dashboard .tf-progress-panel{min-width:0}
.teamflow-dashboard .tf-progress-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.teamflow-dashboard .tf-progress-heading h2{margin:0}
.teamflow-dashboard .tf-period-form{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:0}
.teamflow-dashboard .tf-period-icon{font-size:18px;color:#176eb2}
.teamflow-dashboard .tf-period-select{appearance:auto;max-width:220px;cursor:pointer;font:13px Arial,sans-serif;min-height:39px}
.teamflow-dashboard .tf-period-select{border:1px solid #b7d3f2;border-radius:9px;color:#173960;background:#fff;font-size:13px;padding:10px 16px;display:inline-flex;align-items:center;white-space:nowrap}
.teamflow-dashboard .tf-progress-chart{display:grid!important;grid-template-columns:minmax(0,1.55fr) minmax(0,1.4fr);gap:20px;align-items:center;width:100%;height:auto!important;min-height:0!important;overflow:visible!important;background:transparent!important;border-radius:0!important;padding:12px 0 0}
.teamflow-dashboard .tf-chart-graph{min-width:0}
.teamflow-dashboard .tf-progress-svg{display:block!important;width:100%!important;height:auto!important;min-height:0!important;max-height:none!important;overflow:visible!important;flex:none!important}
.teamflow-dashboard .tf-chart-axis-label{text-align:center;font-size:11px;color:#52677d;margin-top:-5px}
.teamflow-dashboard .tf-progress-overview{display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1.15fr);align-items:stretch;gap:14px;min-width:0}
.teamflow-dashboard .tf-current-progress{display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:0;padding:20px 10px;border-radius:13px;border:1px solid #c9dfff;background:#e9f4ff;color:#123b73;font-size:14px;text-align:center}
.teamflow-dashboard .tf-current-progress-icon{width:43px;height:43px;border:3px solid #1174c1;color:#1174c1;border-radius:50%;font-weight:bold;font-size:24px;line-height:37px;margin-bottom:10px}
.teamflow-dashboard .tf-current-progress strong{font-size:clamp(31px,3.2vw,43px);line-height:1.35;font-weight:800;color:#0e4b94}
.teamflow-dashboard .tf-current-progress small{font-size:11px;line-height:1.3;color:#6d91bf;margin-top:7px}
.teamflow-dashboard .tf-progress-states{display:flex;flex-direction:column;gap:8px;min-width:0;justify-content:center}
.teamflow-dashboard .tf-state-card{display:flex;align-items:center;gap:10px;min-width:0;min-height:54px;padding:9px 11px;box-sizing:border-box;border-radius:10px;background:#f7faff;border:1px solid #d9e7f9;color:#17406f;font-size:12px;line-height:1.35}
.teamflow-dashboard .tf-state-card b{font-weight:700}
.teamflow-dashboard .tf-state-symbol{display:flex;align-items:center;justify-content:center;flex:0 0 14px;width:14px;height:14px;border-radius:50%}
.teamflow-dashboard .tf-state-done{background:#1475bc}
.teamflow-dashboard .tf-state-active{background:#f19a27}
.teamflow-dashboard .tf-state-pending{background:#7c43dc}
.teamflow-dashboard .tf-progress-note{margin:13px 0 0;line-height:1.5}
@media(max-width:1050px){.teamflow-dashboard .tf-progress-chart{grid-template-columns:1fr;gap:15px}.teamflow-dashboard .tf-progress-overview{grid-template-columns:minmax(0,1fr) minmax(0,1.3fr)}}
@media(max-width:570px){.teamflow-dashboard .tf-progress-overview{grid-template-columns:1fr}.teamflow-dashboard .tf-progress-heading{align-items:flex-start}}
/* Bài chờ duyệt trực tiếp trên Dashboard - không ảnh hưởng popup Phân công */
.teamflow-dashboard .columns{grid-template-columns:minmax(0,1.1fr) minmax(0,1fr)}
.teamflow-dashboard .columns > .panel{min-width:0}
.teamflow-dashboard .tf-pending-card{border:1px solid #c8d8e8;border-radius:10px;padding:14px;margin:10px 0 14px;background:#fff}
.teamflow-dashboard .tf-pending-head{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;color:#173960}
.teamflow-dashboard .tf-pending-tag{font-size:12px;color:#935d0d;background:#fff0d8;padding:6px 9px;border-radius:20px;white-space:nowrap}
.teamflow-dashboard .tf-pending-meta{display:flex;flex-wrap:wrap;gap:8px 13px;font-size:12px;color:#405b78;margin:12px 0}
.teamflow-dashboard .tf-pending-content{background:#f0f6fe;border:1px solid #dae8f8;border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:8px;font-size:13px;overflow-wrap:anywhere;margin-bottom:12px}
.teamflow-dashboard .tf-review-label{display:block;font-weight:bold;font-size:13px;margin:0 0 7px;color:#173960}
.teamflow-dashboard .tf-review-note{display:block;width:100%;max-width:100%;min-height:95px;border:1px solid #c1d4eb;border-radius:8px;padding:10px;color:#173960;background:#fff;resize:vertical}
.teamflow-dashboard .tf-review-buttons{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.teamflow-dashboard .tf-review-buttons button{font-size:13px;font-weight:bold;padding:9px 12px;border-radius:7px}
.teamflow-dashboard .tf-review-approve{background:#e3f8ec;border:1px solid #4aad7a;color:#187347}
.teamflow-dashboard .tf-review-revise{background:#fff0dc;border:1px solid #d9a04a;color:#955b09}
.teamflow-dashboard .tf-review-help{color:#71849a;font-size:12px;margin:8px 0 0}
@media(max-width:1180px){.teamflow-dashboard .columns{grid-template-columns:1fr}}
</style>
<!-- Popup phân công. Chỉ xuất hiện khi bấm nút Phân công. -->
<style>
#tf-assignment-modal[hidden]{display:none!important}
#tf-assignment-modal{position:fixed;inset:0;z-index:99999;background:rgba(15,30,55,.60);display:flex;align-items:center;justify-content:center;padding:18px;overflow-y:auto;box-sizing:border-box}
#tf-assignment-modal .tf-modal-card{background:#fff;border-radius:16px;box-shadow:0 22px 65px rgba(12,31,60,.25);width:min(100%,580px);max-height:calc(100vh - 36px);overflow:auto;padding:24px;box-sizing:border-box;color:#173960;font:14px Arial,sans-serif}
#tf-assignment-modal .tf-modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px}
#tf-assignment-modal h2{margin:0;color:#173960;font-size:21px}
#tf-assignment-modal .tf-modal-close{background:transparent;border:0;color:#687d93;font-size:28px;line-height:1;cursor:pointer}
#tf-assignment-modal .tf-modal-sub{font-size:13px;color:#71849a;margin:11px 0 22px}
#tf-assignment-modal label{display:block;font-size:13px;font-weight:bold;margin:15px 0 7px;color:#294568}
#tf-assignment-modal select,#tf-assignment-modal input[type=text],#tf-assignment-modal input[type=date]{width:100%;height:45px;border:1px solid #c4d6eb;border-radius:8px;background:#fff;color:#294568;padding:0 12px;box-sizing:border-box;font:14px Arial,sans-serif}
#tf-assignment-modal .tf-modal-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:24px}
#tf-assignment-modal .tf-cancel{background:#fff;border:1px solid #c4d6eb;border-radius:8px;padding:11px 19px;color:#425974;cursor:pointer}
#tf-assignment-modal .tf-submit{background:#086eb9;border:0;border-radius:8px;padding:11px 19px;color:#fff;font-weight:bold;cursor:pointer}
#tf-assignment-modal .tf-submit:disabled{opacity:.55;cursor:not-allowed}
</style>
<div id="tf-assignment-modal" role="dialog" aria-modal="true" aria-labelledby="tf-modal-title" hidden>
    <div class="tf-modal-card" role="document">
        <div class="tf-modal-head">
            <h2 id="tf-modal-title">Phân công nhiệm vụ</h2>
            <button type="button" class="tf-modal-close" id="tf-close-assign" aria-label="Đóng cửa sổ">&times;</button>
        </div>
        <p class="tf-modal-sub">Giao nhiệm vụ cho thành viên trong nhóm.</p>
        <?php if (empty($taskGroups) || empty($members)) : ?>
            <p>Chưa có nhiệm vụ nhóm hoặc thành viên hợp lệ để phân công.</p>
        <?php else : ?>
            <form action="<?php echo Route::_('index.php?option=com_teamflow&task=assignments.assign'); ?>" method="post" id="tf-modal-assign-form">
                <?php if (count($taskGroups) === 1) : ?>
                    <input type="hidden" name="task_group_id" value="<?php echo (int) $taskGroups[0]->id; ?>">
                <?php else : ?>
                    <label for="tf-popup-group">Nhiệm vụ nhóm *</label>
                    <select id="tf-popup-group" name="task_group_id" required>
                        <option value="">Chọn nhiệm vụ nhóm</option>
                        <?php foreach ($taskGroups as $taskGroup) : ?>
                        <option value="<?php echo (int) $taskGroup->id; ?>"><?php echo $escape($taskGroup->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <label for="tf-popup-member">Thành viên *</label>
                <select id="tf-popup-member" name="assignee_user_id" required>
                    <option value="">Chọn thành viên</option>
                    <?php foreach ($members as $member) : ?>
                    <option value="<?php echo (int) $member->id; ?>"><?php echo $escape($member->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="tf-popup-title">Tên nhiệm vụ *</label>
                <input id="tf-popup-title" name="title" type="text" maxlength="255" required placeholder="Ví dụ: Thiết kế giao diện">
                <label for="tf-popup-due">Hạn hoàn thành *</label>
                <input id="tf-popup-due" name="due_at" type="date" required>
                <input type="hidden" name="description" value="">
                <?php echo HTMLHelper::_('form.token'); ?>
                <div class="tf-modal-actions">
                    <button class="tf-cancel" type="button" id="tf-cancel-assign">Hủy</button>
                    <button class="tf-submit" type="submit">Xác nhận phân công</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
    const modal=document.getElementById('tf-assignment-modal');
    const open=document.getElementById('tf-open-assign');
    const close=document.getElementById('tf-close-assign');
    const cancel=document.getElementById('tf-cancel-assign');
    if(!modal||!open)return;
    let previousFocus=null;
    const hide=()=>{modal.hidden=true;document.body.style.overflow='';if(previousFocus)previousFocus.focus();};
    open.addEventListener('click',()=>{previousFocus=document.activeElement;modal.hidden=false;document.body.style.overflow='hidden';(close||modal).focus();});
    close?.addEventListener('click',hide);
    cancel?.addEventListener('click',hide);
    modal.addEventListener('click',(event)=>{if(event.target===modal)hide();});
    document.addEventListener('keydown',(event)=>{
        if(modal.hidden)return;
        if(event.key==='Escape')hide();
        if(event.key==='Tab'){
            const candidates=Array.from(modal.querySelectorAll('button:not([disabled]),input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea:not([disabled])'));
            const first=candidates[0],last=candidates[candidates.length-1];
            if(!first)return;
            if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
            else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
        }
    });
})();
</script>

<style>
    /* Giảm khoảng cách giữa các khung */
.teamflow-dashboard .columns {
    margin-bottom: 8px !important;
    gap: 10px !important;
}

.teamflow-dashboard .tf-progress-panel {
    margin-top: 0 !important;
}