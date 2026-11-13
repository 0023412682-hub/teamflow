<?php

namespace Joomla\Component\Teamflow\Site\Model;

defined('_JEXEC') or die;


use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;
class SubmissionModel extends BaseDatabaseModel
{
    public function getSubmissionsByAssignment(
        int $assignment_id,
        int $user_id
    ): array {
        if ($assignment_id <= 0 || $user_id <= 0) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select([
                's.id',
                's.assignment_id',
                's.attempt_no',
                's.file_path',
                's.link_url',
                's.submitted_at',
                's.review_status',
                's.review_comment',
                's.reviewed_by',
                's.reviewed_at'
            ])
            ->from(
                $db->quoteName('#__teamflow_submissions', 's')
            )
            ->join(
                'INNER',
                $db->quoteName(
                    '#__teamflow_assignments',
                    'a'
                ) . ' ON a.id = s.assignment_id'
            )
            ->where('s.assignment_id = ' . (int) $assignment_id)
            ->where('a.assignee_user_id = ' . (int) $user_id)
            ->order('s.attempt_no DESC');

        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
    }

    public function saveSubmission(
        int $assignment_id,
        ?string $file_path,
        ?string $link_url
    ): int {
         $user_id = (int) (Factory::getApplication()->getIdentity()?->id ?? 0);

        if ($user_id <= 0) {
            throw new \RuntimeException('Bạn chưa đăng nhập.');
        }

        if ($assignment_id <= 0) {
            throw new \InvalidArgumentException('Nhiệm vụ không hợp lệ.');
        }

        $file_path = trim($file_path ?? '');
        $link_url = trim($link_url ?? '');

        if ($file_path === '' && $link_url === '') {
            throw new \InvalidArgumentException(
                'Bạn phải nộp file hoặc liên kết.'
            );
        }

        if ($link_url !== '') {
            $scheme = strtolower(
                (string) parse_url($link_url, PHP_URL_SCHEME)
            );

            if (
                !filter_var($link_url, FILTER_VALIDATE_URL)
                || !in_array($scheme, ['http', 'https'], true)
            ) {
                throw new \InvalidArgumentException(
                    'Liên kết không hợp lệ.'
                );
            }
        }

        $db = $this->getDatabase();
        $db->transactionStart();

        try {
            // Khóa nhiệm vụ để tránh hai lần nộp đồng thời.
            $query = $db->createQuery()
                ->select('id')
                ->from($db->quoteName('#__teamflow_assignments'))
                ->where('id = ' . (int) $assignment_id)
                ->where('assignee_user_id = ' . $user_id);

            $db->setQuery((string) $query . ' FOR UPDATE');
            $assigned = $db->loadResult();

            if (!$assigned) {
                throw new \RuntimeException(
                    'Bạn không được giao nhiệm vụ này.'
                );
            }

           
            // Kiểm tra hạn nộp theo thời gian MySQL.
            $query = $db->getQuery(true)
                ->select(
                    'CASE WHEN t.due_at IS NOT NULL '
                    . 'AND NOW() > t.due_at '
                    . 'THEN 1 ELSE 0 END'
                )
                ->from(
                    $db->quoteName('#__teamflow_assignments', 'a')
                )
                ->join(
                    'INNER',
                    $db->quoteName('#__teamflow_task_groups', 'tg')
                    . ' ON tg.id = a.task_group_id'
                )
                ->join(
                    'INNER',
                    $db->quoteName('#__teamflow_tasks', 't')
                    . ' ON t.id = tg.task_id'
                )
                ->where('a.id = ' . (int) $assignment_id);

            $db->setQuery($query);

            if ((int) $db->loadResult() === 1) {
                throw new \RuntimeException(
                    'Nhiệm vụ đã quá hạn nộp.'
                );
            }


            // Lấy lần nộp mới nhất.
            $query = $db->createQuery()
                ->select(['attempt_no', 'review_status'])
                ->from($db->quoteName('#__teamflow_submissions'))
                ->where('assignment_id = ' . (int) $assignment_id)
                ->order('attempt_no DESC');

            $db->setQuery($query, 0, 1);
            $previous = $db->loadAssoc();

            $attempt_no = 1;

            if ($previous) {
                if ($previous['review_status'] !== 'revision_required') {
                    throw new \RuntimeException(
                        'Bài nộp chưa được yêu cầu chỉnh sửa.'
                    );
                }

                $attempt_no = (int) $previous['attempt_no'] + 1;
            }

            // Tạo bản ghi mới, không ghi đè bài cũ.
            $query = $db->createQuery()
                ->insert($db->quoteName('#__teamflow_submissions'))
                ->columns($db->quoteName([
                    'assignment_id',
                    'attempt_no',
                    'file_path',
                    'link_url',
                    'submitted_at',
                    'review_status'
                ]))
                ->values(implode(', ', [
                    (int) $assignment_id,
                    (int) $attempt_no,
                    $file_path !== '' ? $db->quote($file_path) : 'NULL',
                    $link_url !== '' ? $db->quote($link_url) : 'NULL',
                    $db->quote(\Joomla\CMS\Factory::getDate()->toSql()),
                    $db->quote('pending')
                ]));

            $db->setQuery($query);
            $db->execute();

            $id = (int) $db->insertid();
            $db->transactionCommit();

            return $id;
        } catch (\Throwable $e) {
            $db->transactionRollback();
            throw $e;
        }
    }

    public function getSubmissionForDownload(
        int $submission_id,
        int $user_id
    ): ?array {
        if ($submission_id <= 0 || $user_id <= 0) {
            return null;
        }

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                's.id',
                's.assignment_id',
                's.file_path',
                's.attempt_no'
            ])
            ->from(
                $db->quoteName('#__teamflow_submissions', 's')
            )
            ->join(
                'INNER',
                $db->quoteName(
                    '#__teamflow_assignments',
                    'a'
                ) . ' ON a.id = s.assignment_id'
            )
            ->where('s.id = ' . (int) $submission_id)
            ->where('a.assignee_user_id = ' . (int) $user_id);

        $db->setQuery($query);

        $result = $db->loadAssoc();

        return $result ?: null;
    }

public function download(): void
{
    $app = Factory::getApplication();

    // Chỉ cho phép yêu cầu GET.
    if ($this->input->getMethod() !== 'GET') {
        throw new \RuntimeException(
            'Phương thức tải tệp không hợp lệ.',
            405
        );
    }

    // Kiểm tra token trong đường dẫn tải.
    if (!Session::checkToken('get')) {
        throw new \RuntimeException(
            'Liên kết tải tệp không hợp lệ.',
            403
        );
    }

    // Kiểm tra đăng nhập.
    $user = $app->getIdentity();

    if (!$user || $user->guest) {
        throw new \RuntimeException(
            'Vui lòng đăng nhập để tải bài làm.',
            403
        );
    }

    $submissionId = $this->input->getInt(
        'submission_id',
        0
    );

    if ($submissionId <= 0) {
        throw new \RuntimeException(
            'Mã bài nộp không hợp lệ.',
            400
        );
    }

    // Lấy thông tin tệp có kiểm tra quyền sở hữu.
    $model = $this->getModel('Submission');

    if (!$model) {
        throw new \RuntimeException(
            'Không tìm thấy SubmissionModel.'
        );
    }

    $submission = $model->getSubmissionForDownload(
        $submissionId,
        (int) $user->id
    );

    if (!$submission) {
        throw new \RuntimeException(
            'Bạn không có quyền tải bài nộp này.',
            403
        );
    }

    $fileName = (string) (
        $submission['file_path'] ?? ''
    );

    // Không chấp nhận đường dẫn hoặc tên tệp tùy ý.
    if (
        !preg_match(
            '/^[a-f0-9]{32}\.(pdf|docx|zip)$/',
            $fileName
        )
    ) {
        throw new \RuntimeException(
            'Thông tin tệp không hợp lệ.',
            404
        );
    }

    // Cùng vị trí lưu tệp trong submit().
    $storageDir = dirname(
        JPATH_ROOT,
        2
    ) . '/teamflow-private-uploads';

    $basePath = realpath($storageDir);
    $filePath = realpath(
        $storageDir . '/' . $fileName
    );

    if (
        $basePath === false
        || $filePath === false
        || dirname($filePath) !== $basePath
        || !is_file($filePath)
        || !is_readable($filePath)
    ) {
        throw new \RuntimeException(
            'Không tìm thấy tệp bài làm.',
            404
        );
    }

    $extension = strtolower(
        pathinfo($fileName, PATHINFO_EXTENSION)
    );

    $mimeTypes = [
        'pdf' => 'application/pdf',
        'docx' =>
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'zip' => 'application/zip'
    ];

    // Xóa dữ liệu đầu ra trước khi gửi tệp.
    while (ob_get_level() > 0) {
        if (!ob_end_clean()) {
            break;
        }
    }

    header('Content-Type: ' . $mimeTypes[$extension]);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');

    readfile($filePath);
    exit;
}
}