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
}