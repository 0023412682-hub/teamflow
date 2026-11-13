<?php
/**
 * TeamFlow — Bài nộp / Submission view
 * Replace only site/tmpl/submission/default.php
 * Real data is provided by Submission HtmlView via $this->submissions.
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
$user = $app->getIdentity();
$assignmentId = $app->input->getInt('assignment_id', 0);
$submissions = array_map(static fn($item) => (object) $item, $this->submissions ?? []);
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$latest = $submissions[0] ?? null;
$countTotal = count($submissions);
$countApproved = count(array_filter($submissions, static fn($s) => ($s->review_status ?? '') === 'approved'));
$countRevision = count(array_filter($submissions, static fn($s) => ($s->review_status ?? '') === 'revision_required'));
$countPending = count(array_filter($submissions, static fn($s) => ($s->review_status ?? '') === 'pending'));

$states = [
    'approved' => ['label' => 'ĐẠT YÊU CẦU', 'class' => 'approved'],
    'revision_required' => ['label' => 'CẦN CHỈNH SỬA', 'class' => 'revision'],
    'pending' => ['label' => 'CHỜ DUYỆT', 'class' => 'pending'],
];

$icons = [
    'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
    'tasks' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 11l2 2 4-4M9 17h6"/>',
    'users' => '<circle cx="9" cy="8" r="3"/><path d="M2 21v-2a6 6 0 0 1 12 0v2M16 6a3 3 0 0 1 0 6M16 16a5 5 0 0 1 6 5"/>',
    'upload' => '<path d="M12 16V4m-4 4 4-4 4 4M4 17a4 4 0 0 0 1 8h14a4 4 0 0 0 1-8" transform="translate(0,-2)"/>',
    'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.5-2.3 1A8 8 0 0 0 15 6l-.3-2.6h-4.1L10.3 6a8 8 0 0 0-1.6 1l-2.3-1-2 3.5 2 1.5a7 7 0 0 0 0 2l-2 1.5 2 3.5 2.3-1a8 8 0 0 0 1.6 1l.3 2.6h4.1L15 18a8 8 0 0 0 1.6-1l2.3 1 2-3.5-2-1.5a7 7 0 0 0 .1-1z"/>',
    'file' => '<path d="M7 2h7l5 5v15H7a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zM14 2v6h5M9 13h6M9 17h6"/>',
    'check' => '<circle cx="12" cy="12" r="10"/><path d="m7 12 3 3 7-7"/>',
    'clock' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 3"/>',
    'edit' => '<path d="M12 20h9M4 16l11-11 4 4-11 11H4zM13 7l4 4"/>',
    'download' => '<path d="M12 3v13m-5-5 5 5 5-5M4 18v3h16v-3"/>',
    'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
    'user' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="9" r="3"/><path d="M5.5 19c.8-3 3-4.5 6.5-4.5s5.7 1.5 6.5 4.5"/>',
    'book' => '<path d="M4 4h7a4 4 0 0 1 4 4v13a4 4 0 0 0-4-3H4z"/><path d="M20 4h-7a4 4 0 0 0-4 4v13a4 4 0 0 1 4-3h7z"/>',
];
$icon = static function (string $name, string $extraClass = '') use ($icons): string {
    return '<svg class="tf-icon ' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icons[$name] ?? $icons['file']) . '</svg>';
};
// Phân loại thời điểm nộp theo đúng hạn của nhiệm vụ (tính bằng MySQL).
// Chỉ truy vấn bài nộp thuộc mã phân công mà thành viên đang đăng nhập sở hữu.
$timingCounts = ['early' => 0, 'near' => 0, 'late' => 0, 'unknown' => 0];
if ($assignmentId > 0 && $user && !$user->guest && $submissions) {
    try {
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $timingQuery = $db->getQuery(true)
            ->select('s.id, t.due_at, TIMESTAMPDIFF(SECOND, s.submitted_at, t.due_at) AS seconds_to_due')
            ->from($db->quoteName('#__teamflow_submissions', 's'))
            ->join('INNER', $db->quoteName('#__teamflow_assignments', 'a') . ' ON a.id = s.assignment_id')
            ->join('INNER', $db->quoteName('#__teamflow_task_groups', 'tg') . ' ON tg.id = a.task_group_id')
            ->join('INNER', $db->quoteName('#__teamflow_tasks', 't') . ' ON t.id = tg.task_id')
            ->where('a.id = ' . (int) $assignmentId)
            ->where('a.assignee_user_id = ' . (int) $user->id);
        $db->setQuery($timingQuery);
        $timingRows = $db->loadObjectList('id');
        foreach ($submissions as $submission) {
            $timing = $timingRows[(int) ($submission->id ?? 0)] ?? null;
            if (!$timing || $timing->due_at === null) {
                $timingCounts['unknown']++;
            } elseif ((int) $timing->seconds_to_due < 0) {
                $timingCounts['late']++;
            } elseif ((int) $timing->seconds_to_due <= 86400) {
                $timingCounts['near']++;
            } else {
                $timingCounts['early']++;
            }
        }
    } catch (\Throwable $error) {
        // Nếu dữ liệu hạn nộp chưa sẵn sàng, giữ các biểu đồ ở trạng thái chưa xác định.
        $timingCounts = ['early' => 0, 'near' => 0, 'late' => 0, 'unknown' => $countTotal];
    }
}
$maxTiming = max(1, $timingCounts['early'], $timingCounts['near'], $timingCounts['late']);
$approvedPct = $countTotal ? round($countApproved * 100 / $countTotal) : 0;
$pendingPct = $countTotal ? round($countPending * 100 / $countTotal) : 0;
$revisionPct = $countTotal ? 100 - $approvedPct - $pendingPct : 0;
$historyAction = Uri::root() . 'index.php';
?>
<style>
/* Chỉ mở toàn chiều rộng khi có trang Bài nộp TeamFlow. */
body:has(.tf-page) .site-grid { grid-template-columns:minmax(0,1fr) !important; gap:0 !important; }
body:has(.tf-page) .container-component { grid-column:1/-1 !important; min-width:0; padding:0 12px !important; }
body:has(.tf-page) .container-sidebar-right,body:has(.tf-page) .container-sidebar-left { display:none !important; }
body:has(.tf-page) .grid-child { max-width:none !important; width:100%; }
body:has(.tf-page) .tf-page { width:100%; max-width:none; border-radius:0; }
/* Scoped to com_teamflow so the Cassiopeia template is not affected elsewhere. */
.tf-page, .tf-page * { box-sizing: border-box; }
.tf-page { color:#14283e; background:#edf4fb; font-family:Arial,Helvetica,sans-serif; font-size:15px; border-radius:12px; overflow:hidden; border:1px solid #dbe6f2; }
.tf-page .tf-top { display:flex; align-items:center; min-height:92px; background:#fff; border-bottom:1px solid #dbe6f2; }
.tf-page .tf-brand { display:flex; align-items:center; gap:18px; width:270px; flex:none; padding:12px 22px; }
.tf-page .tf-logo { display:grid; place-items:center; width:57px; height:57px; background:linear-gradient(135deg,#0879d4,#0752a4); color:#fff; border-radius:13px; font-size:27px; font-weight:900; letter-spacing:-2px; }
.tf-page .tf-brand-title { padding-left:16px; border-left:2px solid #0d4683; white-space:nowrap; }
.tf-page .tf-brand-title strong { display:block; font-size:26px; color:#0757a5; letter-spacing:-1px; }
.tf-page .tf-brand-title small { font-size:13px; color:#4e5864; white-space:nowrap; }
.tf-page .tf-headline { flex:1; text-align:center; padding:16px; }
.tf-page h1 { margin:0; color:#081d36; font-size:clamp(30px,4vw,48px); font-weight:900; line-height:1.15; }
.tf-page .tf-headline p { margin:9px 0 0; font-size:17px; font-weight:700; color:#b82c34; }
.tf-page .tf-body { display:grid; grid-template-columns:240px minmax(0,1fr); min-height:calc(100vh - 110px); }
.tf-page .tf-sidebar { background:linear-gradient(165deg,#095ca9,#064d94 65%,#0659ab); padding:23px 9px; color:white; }
.tf-page .tf-menu-item { display:flex; align-items:center; gap:18px; width:100%; margin:9px 0; padding:16px 15px; border-radius:9px; color:white; text-decoration:none; font-weight:700; font-size:16px; }
.tf-page .tf-menu-item.muted { opacity:.89; cursor:default; }
.tf-page .tf-menu-item.active { background:#fff; color:#0056ad; }
.tf-page .tf-menu-item:hover { text-decoration:none; }
.tf-page .tf-menu-item.active:hover { color:#0056ad; }
.tf-page .tf-icon { width:23px; height:23px; flex:none; vertical-align:middle; }
.tf-page .tf-sidebar .tf-icon { width:26px; height:26px; }
.tf-page .tf-main { padding:16px 17px 24px; min-width:0; }
.tf-page .tf-cards { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:14px; margin-bottom:16px; }
.tf-page .tf-stat { min-height:96px; display:flex; align-items:center; gap:15px; padding:14px 17px; background:white; border:1px solid #a9c8ea; border-radius:12px; color:var(--tf-accent); }
.tf-page .tf-stat .tf-icon { width:42px;height:42px; }
.tf-page .tf-stat strong { display:block; font-size:15px; font-weight:700; color:#15283f; white-space:normal; }
.tf-page .tf-stat b { display:block; font-size:29px; line-height:1.2; }
.tf-page .tf-columns { display:grid; grid-template-columns:minmax(0,2.25fr) minmax(280px,.92fr); gap:16px; align-items:stretch; }
.tf-page .tf-panel { min-width:0; background:#fff; border:1px solid #a8c7e8; border-radius:12px; padding:18px 15px 20px; }
.tf-page .tf-panel h2 { margin:0 0 14px; color:#0754a9; font-size:21px; font-weight:900; }
.tf-page .tf-table-wrap { overflow-x:auto; }
.tf-page table { width:100%; border-collapse:collapse; font-size:13px; }
.tf-page th { background:#d9eafd; color:#064d9b; font-size:13px; white-space:nowrap; font-weight:800; }
.tf-page td,.tf-page th { border:1px solid #d4dfe9; padding:12px 9px; text-align:left; }
.tf-page tbody tr:nth-child(even) { background:#f8fbff; }
.tf-page .tf-status { display:inline-block; padding:7px 10px; font-size:11px; border-radius:24px; font-weight:800; white-space:nowrap; }
.tf-page .tf-status.approved { background:#dcf9df; color:#06752a; }
.tf-page .tf-status.revision { background:#fff0de; color:#dc6000; }
.tf-page .tf-status.pending { background:#e6eafa; color:#394c9c; }
.tf-page .tf-review-box { border:1px solid #d4e2f2; border-radius:9px; padding:15px; margin-top:12px; }
.tf-page .tf-review-heading { padding:15px; border:1px solid #b4d2f0; background:#e3f0ff; color:#153a67; border-radius:8px; margin-bottom:14px; display:flex; align-items:center; gap:11px; }
.tf-page .tf-review-heading strong { display:block; }
.tf-page .tf-review-heading small { display:block; margin-top:4px; }
.tf-page .tf-review-label { font-size:16px; font-weight:800; color:#0754aa; margin:16px 0 10px; }
.tf-page .tf-review-text { border:1px solid #c5d9ef; border-radius:9px; background:#fff; min-height:120px; padding:13px; white-space:pre-wrap; line-height:1.55; }
.tf-page .tf-review-ok { padding:13px; border-radius:8px; background:#ddf9e2; color:#08652a; font-weight:800; }
.tf-page .tf-review-fix { padding:13px; border-radius:8px; background:#fff0df; color:#ae5200; font-weight:800; }
.tf-page .tf-review-wait { padding:13px; border-radius:8px; background:#e8efff; color:#3651a6; font-weight:800; }
.tf-page .tf-toolbar { display:flex; align-items:end; gap:10px; flex-wrap:wrap; margin-bottom:15px; }
.tf-page .tf-field { display:flex; flex-direction:column; gap:5px; min-width:120px; flex:1; }
.tf-page label { font-weight:700; color:#204569; font-size:13px; }
.tf-page input[type=number], .tf-page input[type=url], .tf-page input[type=file] { width:100%; min-width:0; min-height:38px; border:1px solid #b6c9de; border-radius:6px; padding:8px; background:#fff; color:#23384e; }
.tf-page .tf-btn { display:inline-flex; justify-content:center; align-items:center; gap:7px; color:#fff; background:#0864b6; padding:10px 15px; border:0; border-radius:7px; font-weight:700; cursor:pointer; text-decoration:none; white-space:nowrap; }
.tf-page .tf-btn:hover { color:#fff; background:#084a85; text-decoration:none; }
.tf-page .tf-btn-outline { background:#fff;color:#0657a6;border:1px solid #8eb8e4; }
.tf-page .tf-btn-outline:hover { background:#e8f2ff;color:#064f99; }
.tf-page .tf-submission { margin-top:17px; }
.tf-page .tf-submit-grid { display:grid; grid-template-columns:1fr 1.5fr 1.5fr auto; gap:12px; align-items:end; }
.tf-page .tf-help { font-size:12px; color:#60758d; margin:10px 0 0; }
.tf-page .tf-link { color:#075caa; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap; }
.tf-page .tf-link:hover { text-decoration:underline; }
.tf-page .tf-footer { padding:14px 20px; background:#0760b8; color:#fff; font-size:13px; font-weight:600; display:flex;justify-content:space-between;gap:15px; }
.tf-page .tf-empty { padding:16px; color:#526a81; background:#f2f7fd; border-radius:8px; }
/* Biểu đồ thống kê theo đúng bố cục của ảnh mẫu. */
.tf-page .tf-chart-columns { display:grid; grid-template-columns:minmax(0,1.18fr) minmax(0,1fr); gap:15px; margin-bottom:17px; }
.tf-page .tf-chart-panel { padding:14px 18px 16px; min-height:255px; }
.tf-page .tf-chart-panel h2 { display:flex; gap:11px; align-items:center; font-size:20px; margin:0 0 12px; color:#18243a; text-transform:none; }
.tf-page .tf-chart-panel h2 .tf-icon { color:#13466c; width:27px; height:27px; }
.tf-page .tf-plot { display:grid; grid-template-columns:36px minmax(0,1fr); height:175px; }
.tf-page .tf-plot-y { writing-mode:vertical-rl; transform:rotate(180deg); text-align:center; font-size:12px; color:#314355; }
.tf-page .tf-plot-area { display:flex; align-items:end; justify-content:space-evenly; border-left:1px solid #778ca5; border-bottom:1px solid #778ca5; background:repeating-linear-gradient(to top, transparent 0, transparent calc(33.33% - 1px), #d6e0ea calc(33.33% - 1px),#d6e0ea 33.33%); padding:4px 6px 0; }
.tf-page .tf-bar-slot { position:relative; width:28%; height:100%; display:flex; align-items:end; justify-content:center; }
.tf-page .tf-bar { width:min(100%,100px); min-height:0; border-radius:5px 5px 0 0; }
.tf-page .tf-bar-val { position:absolute; bottom:calc(var(--bar-height) + 5px); font-weight:800; color:#101d2b; font-size:16px; }
.tf-page .tf-axis-labels { display:grid; grid-template-columns:repeat(3,1fr); padding-left:36px; text-align:center; gap:5px; margin-top:7px; font-size:12px; font-weight:600; }
.tf-page .tf-chart-note { text-align:center; font-size:12px; color:#586d85; margin-top:7px; }
.tf-page .tf-donut-wrap { display:flex; align-items:center; justify-content:center; gap:23px; min-height:168px; }
.tf-page .tf-donut { width:165px; height:165px; aspect-ratio:1; border-radius:50%; flex:none; display:grid; place-items:center; background:conic-gradient(#61c65d 0 var(--approved), #abc9fb var(--approved) var(--pending), #ffbf68 var(--pending) 100%); }
.tf-page .tf-donut-hole { width:100px; height:100px; border-radius:50%; background:white; display:grid; align-content:center; text-align:center; font-size:14px; box-shadow:0 0 0 1px #e5edf8; }
.tf-page .tf-donut-hole strong { font-size:29px; line-height:1.15; }
.tf-page .tf-donut-legend { display:grid; gap:10px; font-size:14px; }
.tf-page .tf-legend-item { display:flex; align-items:center; gap:9px; white-space:nowrap; }
.tf-page .tf-legend-square { display:inline-block; width:15px; height:15px; border-radius:4px; flex:none; }
.tf-page .tf-chart-panel:has(.tf-plot) { min-width:0; }
.tf-page .tf-review-panel { display:flex; flex-direction:column; }
.tf-page .tf-review-panel .tf-review-text { flex:1; min-height:125px; }
.tf-page .tf-stat-icon { width:60px; height:58px; display:grid; place-items:center; flex:none; background:var(--tf-bg); border-radius:12px; }
.tf-page .tf-stat-icon .tf-icon { width:34px; height:34px; }
.tf-page .tf-stat b { color:#162945; }
.tf-page .tf-panel .tf-panel-title { display:flex; align-items:center; gap:8px; }
.tf-page .tf-panel-title .tf-icon { width:24px;height:24px; }
.tf-page .tf-action-footer { display:flex;justify-content:flex-end; margin-top:auto;padding-top:14px; }
.tf-page .tf-action-footer .tf-btn { min-width:200px; font-size:16px; }
@media(max-width:1020px){.tf-page .tf-chart-columns{grid-template-columns:1fr;} .tf-page .tf-columns{grid-template-columns:1fr;} .tf-page .tf-donut-wrap{justify-content:flex-start;}}
@media(max-width:700px){.tf-page .tf-donut-wrap{flex-wrap:wrap;}.tf-page .tf-cards{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:1200px){ .tf-page .tf-cards{grid-template-columns:repeat(2,minmax(0,1fr));} .tf-page .tf-columns{grid-template-columns:1fr;} .tf-page .tf-submit-grid{grid-template-columns:1fr 1fr;} }
@media(max-width:760px){ .tf-page .tf-top{display:block;} .tf-page .tf-brand{width:auto;padding:14px;} .tf-page .tf-brand-title strong{font-size:24px;} .tf-page .tf-headline{padding:6px 12px 15px;} .tf-page .tf-body{display:block;} .tf-page .tf-sidebar{padding:6px;display:flex;overflow-x:auto;} .tf-page .tf-menu-item{white-space:nowrap;padding:10px;min-width:max-content;} .tf-page .tf-main{padding:14px 10px;} .tf-page .tf-stat{min-height:85px;} .tf-page .tf-footer{display:block;} }
@media(max-width:520px){.tf-page .tf-cards,.tf-page .tf-submit-grid{grid-template-columns:1fr;} .tf-page .tf-stat{min-height:68px;} }

/* Hoan thien header va tieu de — toan bo quy tac nam trong mot the style. */
body:has(.tf-page) .container-header,
body:has(.tf-page) .container-breadcrumbs {display:none !important;}
.tf-page .tf-top { min-height:76px;justify-content:space-between;padding:0 22px;gap:14px; }
.tf-page .tf-top .tf-brand {width:auto;padding:10px 0;}
.tf-page .tf-top-right {display:flex;gap:20px;align-items:center;color:#0d3159;}
.tf-page .tf-project {display:flex;align-items:center;gap:10px;font-size:16px;white-space:nowrap;}
.tf-page .tf-top-icon {display:inline-flex;align-items:center;justify-content:center;}
.tf-page .tf-top-right .tf-icon {width:27px;height:27px;}
.tf-page .tf-top-divider {height:32px;width:1px;background:#cbd5e1;}
.tf-page .tf-page-heading {display:block;visibility:visible;margin:0 0 16px;padding:3px 0 0;background:transparent;}
.tf-page .tf-page-heading h1 {display:flex;align-items:center;gap:15px;margin:0;color:#09244b;font-size:clamp(28px,3.2vw,43px);font-weight:900;line-height:1.18;}
.tf-page .tf-heading-upload {display:block;width:46px !important;height:46px !important;min-width:46px;max-width:46px;flex:none;color:#104b76;}
.tf-page .tf-history-filters {display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin:12px 0 15px;}
.tf-page .tf-history-filters select {display:block;min-width:170px;width:100%;padding:10px;border:1px solid #b6c9de;border-radius:6px;background:white;color:#23384e;}
.tf-page .tf-history-filters .tf-field {max-width:300px;}
@media(max-width:760px){.tf-page .tf-top{padding:9px 13px;}.tf-page .tf-top-right{gap:12px;}.tf-page .tf-project{font-size:13px;}.tf-page .tf-top-right .tf-icon{width:22px;height:22px;}.tf-page .tf-heading-upload{width:36px !important;height:36px !important;min-width:36px;max-width:36px;}}
</style>

<div class="tf-page">
  <header class="tf-top">
    <div class="tf-brand"><div class="tf-logo">TF</div><div class="tf-brand-title"><strong>TeamFlow</strong><small>Quản lý bài tập nhóm</small></div></div>
    <div class="tf-top-right" aria-label="Thông tin dự án và tài khoản">
      <div class="tf-project"><?php echo $icon('book'); ?> <span>Dự án Joomla</span><span aria-hidden="true">⌄</span></div>
      <span class="tf-top-divider" aria-hidden="true"></span>
      <span class="tf-top-icon" title="Thông báo" aria-label="Biểu tượng thông báo"><?php echo $icon('bell'); ?></span>
      <span class="tf-top-icon" title="Tài khoản" aria-label="Biểu tượng tài khoản"><?php echo $icon('user'); ?></span>
    </div>
  </header>
  <div class="tf-body">
    <aside class="tf-sidebar" aria-label="Điều hướng TeamFlow">
      <a class="tf-menu-item" href="<?php echo $escape(Uri::root()); ?>"><?php echo $icon('home'); ?> Tổng quan</a>
      <span class="tf-menu-item muted"><?php echo $icon('tasks'); ?> Nhiệm vụ của tôi</span>
      <span class="tf-menu-item muted"><?php echo $icon('users'); ?> Nhóm của tôi</span>
      <a class="tf-menu-item active" aria-current="page" href="<?php echo $escape(Route::_('index.php?option=com_teamflow&view=submission')); ?>"><?php echo $icon('upload'); ?> Bài nộp</a>
      <span class="tf-menu-item muted"><?php echo $icon('settings'); ?> Quản trị</span>
    </aside>
    <main class="tf-main">
      <div class="tf-page-heading">
        <h1><svg class="tf-heading-upload" width="46" height="46" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V3"/><path d="m6 9 6-6 6 6"/><path d="M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/></svg><span>BÀI NỘP</span></h1>
      </div>
      <div class="tf-cards" aria-label="Thống kê thời điểm nộp của mã phân công đã chọn">
        <div class="tf-stat" style="--tf-accent:#0c5ca9;--tf-bg:#e6f3fc"><span class="tf-stat-icon"><?php echo $icon('upload'); ?></span><div><strong>Tổng số lần nộp</strong><b><?php echo $countTotal; ?></b></div></div>
        <div class="tf-stat" style="--tf-accent:#238446;--tf-bg:#e6fae9"><span class="tf-stat-icon"><?php echo $icon('check'); ?></span><div><strong>Nộp trước hạn</strong><b><?php echo $timingCounts['early']; ?></b></div></div>
        <div class="tf-stat" style="--tf-accent:#b77716;--tf-bg:#fff4e0"><span class="tf-stat-icon"><?php echo $icon('clock'); ?></span><div><strong>Nộp sát hạn</strong><b><?php echo $timingCounts['near']; ?></b></div></div>
        <div class="tf-stat" style="--tf-accent:#762ab6;--tf-bg:#f2e7ff"><span class="tf-stat-icon"><?php echo $icon('edit'); ?></span><div><strong>Nộp trễ</strong><b><?php echo $timingCounts['late']; ?></b></div></div>
      </div>
      <div class="tf-chart-columns">
        <section class="tf-panel tf-chart-panel" aria-label="Biểu đồ cột thống kê thời điểm nộp">
          <h2><?php echo $icon('tasks'); ?> Thống kê thời điểm nộp</h2>
          <div class="tf-plot"><div class="tf-plot-y">Số lần nộp</div>
            <div class="tf-plot-area">
              <?php foreach ([['early','#72cd79'],['near','#ffcb5e'],['late','#b98af7']] as $bar):
                  $height = $timingCounts[$bar[0]] * 90 / $maxTiming;
              ?>
              <div class="tf-bar-slot" style="--bar-height:<?php echo $height; ?>%"><span class="tf-bar-val"><?php echo $timingCounts[$bar[0]]; ?></span><div class="tf-bar" style="height:var(--bar-height);background:<?php echo $bar[1]; ?>"></div></div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="tf-axis-labels"><span>Nộp trước hạn</span><span>Nộp sát hạn</span><span>Nộp trễ</span></div>
          <?php if ($timingCounts['unknown']): ?><div class="tf-chart-note">Có <?php echo $timingCounts['unknown']; ?> lần nộp chưa có dữ liệu hạn nộp để phân loại.</div><?php endif; ?>
        </section>
        <section class="tf-panel tf-chart-panel" aria-label="Biểu đồ vòng kết quả duyệt">
          <h2><?php echo $icon('check'); ?> Kết quả duyệt</h2>
          <div class="tf-donut-wrap">
            <div class="tf-donut" style="--approved:<?php echo $approvedPct; ?>%;--pending:<?php echo $approvedPct + $pendingPct; ?>%"><div class="tf-donut-hole"><strong><?php echo $countTotal; ?></strong>lần nộp</div></div>
            <div class="tf-donut-legend">
              <div class="tf-legend-item"><i class="tf-legend-square" style="background:#61c65d"></i> Đạt: <?php echo $countApproved; ?> (<?php echo $approvedPct; ?>%)</div>
              <div class="tf-legend-item"><i class="tf-legend-square" style="background:#abc9fb"></i> Chờ duyệt: <?php echo $countPending; ?> (<?php echo $pendingPct; ?>%)</div>
              <div class="tf-legend-item"><i class="tf-legend-square" style="background:#ffbf68"></i> Cần sửa: <?php echo $countRevision; ?> (<?php echo $revisionPct; ?>%)</div>
            </div>
          </div>
        </section>
      </div>
      <div class="tf-columns">
        <section class="tf-panel">
          <h2 class="tf-panel-title"><?php echo $icon('users'); ?> Lịch sử nộp bài của thành viên</h2>
          <?php if ($user && !$user->guest): ?>
          <form class="tf-toolbar" method="get" action="<?php echo $escape($historyAction); ?>">
            <input type="hidden" name="option" value="com_teamflow">
            <input type="hidden" name="view" value="submission">
            <div class="tf-field" style="flex:0 1 190px"><label for="tf-history-id">Mã phân công</label><input type="number" name="assignment_id" id="tf-history-id" min="1" required value="<?php echo (int) $assignmentId; ?>"></div>
            <button class="tf-btn" type="submit">Xem lịch sử</button>
          </form>
          <?php if ($assignmentId < 1): ?>
            <div class="tf-empty">Nhập mã phân công để xem lịch sử bài nộp.</div>
          <?php elseif (!$submissions): ?>
            <div class="tf-empty">Chưa có bài nộp hoặc bạn không có quyền xem phân công này.</div>
          <?php else: ?>
          <div class="tf-history-filters">
            <div class="tf-field"><label for="tf-filter-status">Kết quả duyệt</label><select id="tf-filter-status"><option value="all">Tất cả kết quả</option><option value="approved">Đạt yêu cầu</option><option value="pending">Chờ duyệt</option><option value="revision_required">Cần chỉnh sửa</option></select></div>
            <div class="tf-field"><label for="tf-filter-time">Thời gian nộp</label><select id="tf-filter-time"><option value="all">Tất cả thời gian</option><option value="today">Hôm nay</option><option value="7">7 ngày gần đây</option><option value="30">30 ngày gần đây</option></select></div>
            <button type="button" class="tf-btn" id="tf-reset-filters">Đặt lại bộ lọc</button>
          </div>
          <div class="tf-table-wrap"><table id="tf-history-table"><thead><tr><th>NHIỆM VỤ</th><th>LẦN NỘP</th><th>THỜI ĐIỂM</th><th>KẾT QUẢ</th><th>BÀI LÀM</th></tr></thead><tbody>
            <?php foreach ($submissions as $submission):
                $state = $states[(string) ($submission->review_status ?? '')] ?? ['label'=>'CHƯA XÁC ĐỊNH','class'=>'pending'];
                $link = (string) ($submission->link_url ?? '');
                $scheme = strtolower((string) parse_url($link, PHP_URL_SCHEME));
                $validLink = (bool) filter_var($link, FILTER_VALIDATE_URL) && in_array($scheme, ['http','https'], true);
                $hasFile = !empty($submission->file_path);
                $downloadUrl = Route::_('index.php?option=com_teamflow&task=submissions.download&submission_id=' . (int) ($submission->id ?? 0) . '&' . Session::getFormToken() . '=1', false);
            ?>
            <tr data-status="<?php echo $escape((string) ($submission->review_status ?? '')); ?>" data-submitted-at="<?php echo $escape((string) ($submission->submitted_at ?? '')); ?>">
              <td>Phân công #<?php echo (int) $submission->assignment_id; ?></td>
              <td>Lần <?php echo (int) ($submission->attempt_no ?? 0); ?></td>
              <td><?php echo $escape($submission->submitted_at ?? ''); ?></td>
              <td><span class="tf-status <?php echo $escape($state['class']); ?>"><?php echo $escape($state['label']); ?></span></td>
              <td>
                <?php if ($validLink): ?><a class="tf-link" target="_blank" rel="noopener noreferrer" href="<?php echo $escape($link); ?>">Xem liên kết</a><?php endif; ?>
                <?php if ($validLink && $hasFile): ?> · <?php endif; ?>
                <?php if ($hasFile): ?><a class="tf-link" href="<?php echo $escape($downloadUrl); ?>"><?php echo $icon('download'); ?> Tải bài</a><?php endif; ?>
                <?php if (!$validLink && !$hasFile): ?>—<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody></table></div>
          <?php endif; ?>
          <?php else: ?><div class="tf-empty">Vui lòng đăng nhập để xem lịch sử.</div><?php endif; ?>
        </section>
        <section class="tf-panel tf-review-panel">
          <h2 class="tf-panel-title"><?php echo $icon('edit'); ?> Nhận xét của nhóm trưởng</h2>
          <?php if ($latest):
              $latestState = $states[(string) ($latest->review_status ?? '')] ?? ['label'=>'CHƯA XÁC ĐỊNH','class'=>'pending'];
              $noticeClass = $latestState['class']==='approved' ? 'tf-review-ok' : ($latestState['class']==='revision' ? 'tf-review-fix' : 'tf-review-wait');
          ?>
          <div class="tf-review-heading"><?php echo $icon('file'); ?><div><strong>Nhiệm vụ: Phân công #<?php echo (int) ($latest->assignment_id ?? 0); ?></strong><small>Lần nộp: <?php echo (int) ($latest->attempt_no ?? 0); ?> &nbsp; | &nbsp; <?php echo $escape($latest->submitted_at ?? ''); ?></small></div></div>
          <div class="<?php echo $noticeClass; ?>">Kết quả: <?php echo $escape($latestState['label']); ?></div>
          <div class="tf-review-label">Nhận xét của nhóm trưởng:</div>
          <div class="tf-review-text"><?php echo $escape(trim((string) ($latest->review_comment ?? '')) ?: 'Chưa có nhận xét.'); ?></div>
          <?php if (($latest->review_status ?? '') === 'revision_required'): ?><p class="tf-help">Bài làm cần chỉnh sửa. Bạn có thể nộp lại bằng biểu mẫu bên dưới.</p><?php endif; ?>
          <?php else: ?><div class="tf-empty">Chọn một mã phân công có bài nộp để xem nhận xét mới nhất.</div><?php endif; ?>
          <?php if ($latest && ($latest->review_status ?? '') === 'revision_required'): ?>
          <div class="tf-action-footer"><a class="tf-btn" href="#tf-submit-form"><?php echo $icon('upload'); ?> Nộp lại</a></div>
          <?php endif; ?>
        </section>
      </div>
      <?php if ($user && !$user->guest): ?>
      <section class="tf-panel tf-submission" id="tf-submit-form">
        <?php
          $latestStatus = $latest->review_status ?? null;
          $canSubmit = !$latest || $latestStatus === 'revision_required';
        ?>
        <?php if ($canSubmit): ?>
          <h2><?php echo $latest ? 'Chỉnh sửa và nộp lại' : 'Nộp bài làm lần đầu'; ?></h2>
          <form method="post" enctype="multipart/form-data" action="<?php echo $escape(Route::_('index.php?option=com_teamflow&task=submissions.submit', false)); ?>">
            <div class="tf-submit-grid">
              <div class="tf-field"><label for="tf-assignment">Mã phân công</label><input type="number" id="tf-assignment" name="assignment_id" min="1" required value="<?php echo (int) $assignmentId; ?>"></div>
              <div class="tf-field"><label for="tf-file">Tệp bài làm (PDF, DOCX, ZIP)</label><input type="file" id="tf-file" name="submission_file" accept=".pdf,.docx,.zip"></div>
              <div class="tf-field"><label for="tf-url">Đường dẫn bài làm</label><input type="url" id="tf-url" name="link_url" placeholder="https://example.com/bai-lam"></div>
              <button class="tf-btn" type="submit"><?php echo $icon('upload'); ?> <?php echo $latest ? 'Nộp lại' : 'Nộp bài'; ?></button>
            </div>
            <p class="tf-help">Chọn tệp tối đa 10 MB hoặc nhập liên kết bài làm. Chỉ nộp lại khi nhóm trưởng yêu cầu chỉnh sửa.</p>
            <?php echo HTMLHelper::_('form.token'); ?>
          </form>
        <?php elseif ($latestStatus === 'approved'): ?>
          <h2>Trạng thái bài làm</h2><div class="tf-review-ok">✓ Bài làm đã đạt yêu cầu. Bạn không cần nộp lại.</div>
        <?php elseif ($latestStatus === 'pending'): ?>
          <h2>Trạng thái bài làm</h2><div class="tf-review-wait">Bài làm đã được gửi thành công. Vui lòng chờ nhóm trưởng duyệt.</div>
        <?php else: ?>
          <h2>Trạng thái bài làm</h2><div class="tf-empty">Chưa xác định được trạng thái bài nộp.</div>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </main>
  </div>
  <footer class="tf-footer"><span>TeamFlow · Quản lý bài tập nhóm</span><span>Trang bài nộp · Dữ liệu từ hệ thống</span></footer>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const status = document.getElementById('tf-filter-status');
  const period = document.getElementById('tf-filter-time');
  const reset = document.getElementById('tf-reset-filters');
  const table = document.getElementById('tf-history-table');
  if (!status || !period || !reset || !table) return;
  const rows = Array.from(table.querySelectorAll('tbody tr'));
  function applyFilter() {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    for (const row of rows) {
      const allowedStatus = status.value === 'all' || row.dataset.status === status.value;
      let allowedTime = period.value === 'all';
      if (!allowedTime) {
        const date = new Date((row.dataset.submittedAt || '').replace(' ', 'T'));
        if (!Number.isNaN(date.getTime())) {
          if (period.value === 'today') allowedTime = date >= today && date <= now;
          else allowedTime = date >= new Date(now.getTime() - Number(period.value) * 86400000) && date <= now;
        }
      }
      row.hidden = !(allowedStatus && allowedTime);
    }
  }
  status.addEventListener('change', applyFilter);
  period.addEventListener('change', applyFilter);
  reset.addEventListener('click', function () { status.value = 'all'; period.value = 'all'; applyFilter(); });
});
</script>
