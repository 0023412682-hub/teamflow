<?php
namespace Joomla\Component\Teamflow\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;

/**
 * Tải báo cáo của riêng các nhóm hoạt động do tài khoản hiện tại làm trưởng nhóm.
 * Xuất Excel XLSX trực tiếp; không cần Composer hoặc thư viện bên ngoài.
 */
class ReportsController extends BaseController
{
    public function export(): void
    {
        $user = $this->app->getIdentity();
        if (!$user || $user->guest) {
            throw new \RuntimeException('Vui lòng đăng nhập để xuất báo cáo.', 403);
        }

        $leaderId = (int) $user->id;
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Kiểm tra phân quyền phía máy chủ, kể cả khi người dùng gọi URL trực tiếp.
        $q = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__teamflow_groups'))
            ->where($db->quoteName('leader_user_id') . ' = ' . $leaderId)
            ->where($db->quoteName('is_active') . ' = 1');
        $db->setQuery($q);
        if ((int) $db->loadResult() === 0) {
            throw new \RuntimeException('Bạn không có quyền xuất báo cáo Nhóm trưởng.', 403);
        }

        // Nhiệm vụ thuộc nhóm hiện được quản lý, bao gồm nhiệm vụ chưa từng nộp.
        $q = $db->getQuery(true)
            ->select([
                $db->quoteName('a.id', 'assignment_id'),
                $db->quoteName('a.title', 'assignment_title'),
                $db->quoteName('a.due_at'),
                $db->quoteName('u.id', 'member_id'),
                $db->quoteName('u.name', 'member_name'),
                $db->quoteName('g.name', 'group_name'),
            ])
            ->from($db->quoteName('#__teamflow_assignments', 'a'))
            ->join('INNER', $db->quoteName('#__teamflow_task_groups', 'tg')
                . ' ON tg.id = a.task_group_id')
            ->join('INNER', $db->quoteName('#__teamflow_groups', 'g')
                . ' ON g.id = tg.group_id')
            ->join('INNER', $db->quoteName('#__users', 'u')
                . ' ON u.id = a.assignee_user_id')
            ->where('g.leader_user_id = ' . $leaderId)
            ->where('g.is_active = 1')
            ->order('a.id ASC');
        $db->setQuery($q);
        $assignments = $db->loadObjectList();

        // Mọi lần duyệt đã có kết quả, không chỉ lần nộp mới nhất.
        $q = $db->getQuery(true)
            ->select([
                $db->quoteName('s.id', 'submission_id'),
                $db->quoteName('s.assignment_id'),
                $db->quoteName('s.attempt_no'),
                $db->quoteName('s.review_status'),
                $db->quoteName('s.review_comment'),
                $db->quoteName('s.submitted_at'),
                $db->quoteName('s.reviewed_at'),
                $db->quoteName('a.title', 'assignment_title'),
                $db->quoteName('u.id', 'member_id'),
                $db->quoteName('u.name', 'member_name'),
                $db->quoteName('g.name', 'group_name'),
            ])
            ->from($db->quoteName('#__teamflow_submissions', 's'))
            ->join('INNER', $db->quoteName('#__teamflow_assignments', 'a')
                . ' ON a.id = s.assignment_id')
            ->join('INNER', $db->quoteName('#__teamflow_task_groups', 'tg')
                . ' ON tg.id = a.task_group_id')
            ->join('INNER', $db->quoteName('#__teamflow_groups', 'g')
                . ' ON g.id = tg.group_id')
            ->join('INNER', $db->quoteName('#__users', 'u')
                . ' ON u.id = a.assignee_user_id')
            ->where('g.leader_user_id = ' . $leaderId)
            ->where('g.is_active = 1')
            ->where('s.review_status IN (' . $db->quote('approved') . ', '
                . $db->quote('revision_required') . ')')
            ->order('s.reviewed_at DESC, s.id DESC');
        $db->setQuery($q);
        $reviews = $db->loadObjectList();

        $byMember = [];
        foreach ($assignments as $a) {
            $key = (int) $a->member_id;
            if (!isset($byMember[$key])) {
                $byMember[$key] = ['name' => $a->member_name, 'assignments' => 0, 'approved' => 0, 'revision' => 0];
            }
            $byMember[$key]['assignments']++;
        }
        foreach ($reviews as $r) {
            $key = (int) $r->member_id;
            if (isset($byMember[$key])) {
                $byMember[$key][$r->review_status === 'approved' ? 'approved' : 'revision']++;
            }
        }

        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Máy chủ chưa bật PHP ZipArchive. Hãy bật extension=zip trong php.ini của XAMPP.');
        }

        $now = Factory::getDate()->toSql();
        $summary = [
            ['cells' => [['BÁO CÁO NHÓM TRƯỞNG – TEAMFLOW', 1]], 'height' => 34],
            ['cells' => [['Người xuất', 2], [(string) $user->name, 0]]],
            ['cells' => [['Thời gian xuất', 2], [$now, 0]]],
            ['cells' => [['Tổng nhiệm vụ', 2], [count($assignments), 5]]],
            ['cells' => [['Tổng lượt đã duyệt', 2], [count($reviews), 5]]],
            ['cells' => []],
            ['cells' => [['THỐNG KÊ THEO THÀNH VIÊN', 3]], 'height' => 29],
            ['cells' => [['Thành viên', 4], ['Số nhiệm vụ', 4], ['Lượt đạt', 4], ['Lượt cần sửa', 4]], 'height' => 25],
        ];
        foreach ($byMember as $m) {
            $summary[] = ['cells' => [
                [$m['name'], 9],
                [$m['assignments'], 5],
                [$m['approved'], 6],
                [$m['revision'], 7],
            ], 'height' => 28];
        }
        $summaryLast = count($summary);
        if ($summaryLast === 8) {
            $summary[] = ['cells' => [['Chưa có nhiệm vụ phân công.', 9]]];
        }

        $history = [
            ['cells' => [['LỊCH SỬ DUYỆT BÀI – TEAMFLOW', 1]], 'height' => 34],
            ['cells' => [['Người xuất: ' . (string) $user->name, 2]], 'height' => 23],
            ['cells' => [['Tổng lượt duyệt: ' . count($reviews), 2]], 'height' => 23],
            ['cells' => []],
            ['cells' => [
                ['Nhóm', 4], ['Thành viên', 4], ['Nhiệm vụ', 4], ['Mã bài nộp', 4],
                ['Lần nộp', 4], ['Kết quả', 4], ['Nhận xét', 4],
                ['Thời gian nộp', 4], ['Thời gian duyệt', 4],
            ], 'height' => 32],
        ];
        foreach ($reviews as $r) {
            $history[] = ['cells' => [
                [$r->group_name, 9], [$r->member_name, 9], [$r->assignment_title, 9],
                [(int) $r->submission_id, 5], [(int) $r->attempt_no, 5],
                [$r->review_status === 'approved' ? 'Đạt' : 'Cần chỉnh sửa',
                    $r->review_status === 'approved' ? 6 : 7],
                [$r->review_comment ?? '', 9],
                [$r->submitted_at ?? '', 9], [$r->reviewed_at ?? '', 9],
            ], 'height' => 42];
        }
        if (count($history) === 5) {
            $history[] = ['cells' => [['Chưa có lượt duyệt.', 9]]];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'tf_xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('Không thể tạo tệp Excel tạm.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new \RuntimeException('Không thể đóng gói tệp Excel.');
        }
        try {
            $this->addXlsxFiles($zip, $summary, $history, $summaryLast);
            if (!$zip->close()) {
                throw new \RuntimeException('Không thể hoàn tất tệp Excel.');
            }
        } catch (\Throwable $e) {
            $zip->close();
            @unlink($tmp);
            throw $e;
        }

        // XLSX la tep ZIP nhi phan: khong duoc de HTML, khoang trang hay
        // ky tu xuong dong tu Joomla/PHP xuat ra truoc phan dau PK.
        // Dong va xoa bo dem output truoc khi gui du lieu tep.
        while (ob_get_level() > 0) {
            if (!@ob_end_clean()) {
                break;
            }
        }
        if (headers_sent($outputFile, $outputLine)) {
            @unlink($tmp);
            throw new \RuntimeException(
                'Khong the xuat Excel: da co du lieu duoc gui truoc header tai '
                . $outputFile . ':' . $outputLine
            );
        }

        $filename = 'teamflow-bao-cao-nhom-truong-' . date('Y-m-d') . '.xlsx';
        $this->app->setHeader('Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $this->app->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate', true);
        $this->app->setHeader('Content-Length', (string) filesize($tmp), true);
        $this->app->sendHeaders();
        readfile($tmp);
        @unlink($tmp);
        $this->app->close();
    }

    private function addXlsxFiles(\ZipArchive $zip, array $summary, array $history, int $summaryLast): void
    {
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView/></bookViews><sheets>'
            . '<sheet name="Thống kê" sheetId="1" r:id="rId1"/>'
            . '<sheet name="Lịch sử duyệt" sheetId="2" r:id="rId2"/>'
            . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->xlsxStyles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->xlsxSheet($summary,
            [29, 22, 18, 18], ['A1:D1', 'A7:D7'], $summaryLast >= 8 ? 'A8:D' . $summaryLast : null, 7));
        $zip->addFromString('xl/worksheets/sheet2.xml', $this->xlsxSheet($history,
            [24, 23, 32, 16, 14, 18, 64, 23, 23], ['A1:I1', 'A2:I2', 'A3:I3'],
            count($history) > 5 ? 'A5:I' . count($history) : null, 4));
    }

    private function xlsxStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="5">'
            . '<font><sz val="11"/><name val="Calibri"/><color rgb="FF243B53"/></font>'
            . '<font><b/><sz val="18"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF12345E"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/><color rgb="FF0A6845"/></font>'
            . '</fonts><fills count="6">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0B5CA8"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFDDEBFF"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE4F7ED"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF0DB"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills><borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FFD0DDEB"/></left>'
            . '<right style="thin"><color rgb="FFD0DDEB"/></right>'
            . '<top style="thin"><color rgb="FFD0DDEB"/></top>'
            . '<bottom style="thin"><color rgb="FFD0DDEB"/></bottom><diagonal/></border>'
            . '</borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="10">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="5" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function xlsxSheet(array $rows, array $widths, array $merges, ?string $filter, int $freezeRows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $freezeRows
            . '" topLeftCell="A' . ($freezeRows + 1) . '" activePane="bottomLeft" state="frozen"/>'
            . '<selection pane="bottomLeft" activeCell="A' . ($freezeRows + 1) . '" sqref="A' . ($freezeRows + 1) . '"/>'
            . '</sheetView></sheetViews><sheetFormatPr defaultRowHeight="19"/><cols>';
        foreach ($widths as $i => $width) {
            $col = $i + 1;
            $xml .= '<col min="' . $col . '" max="' . $col . '" width="' . $width . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';
        foreach ($rows as $r => $row) {
            $n = $r + 1;
            $height = isset($row['height']) ? ' ht="' . (int) $row['height'] . '" customHeight="1"' : '';
            $xml .= '<row r="' . $n . '"' . $height . '>';
            foreach ($row['cells'] as $c => $cell) {
                $col = $c + 1;
                $letters = '';
                while ($col > 0) {
                    $col--;
                    $letters = chr(65 + $col % 26) . $letters;
                    $col = intdiv($col, 26);
                }
                $ref = $letters . $n;
                [$value, $style] = $cell;
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $ref . '" s="' . (int) $style . '"><v>' . $value . '</v></c>';
                } else {
                    // Inline strings are never interpreted as Excel formulas.
                    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) ($value ?? ''));
                    $value = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $xml .= '<c r="' . $ref . '" s="' . (int) $style . '" t="inlineStr"><is><t xml:space="preserve">' . $value . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';
        if ($filter !== null) {
            $xml .= '<autoFilter ref="' . $filter . '"/>';
        }
        $xml .= '<mergeCells count="' . count($merges) . '">';
        foreach ($merges as $merge) {
            $xml .= '<mergeCell ref="' . $merge . '"/>';
        }
        $xml .= '</mergeCells><pageMargins left="0.3" right="0.3" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            . '</worksheet>';
        return $xml;
    }
}
