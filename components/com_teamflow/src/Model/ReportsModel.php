<?php

namespace Joomla\Component\Teamflow\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

class ReportsModel extends BaseDatabaseModel
{
    public function getSubmissionStatsByGroup(
        int $group_id
    ): array {
        if ($group_id <= 0) {
            throw new \InvalidArgumentException(
                'Nhóm không hợp lệ.'
            );
        }

        $db = $this->getDatabase();

        // Chỉ lấy bài nộp mới nhất của mỗi nhiệm vụ.
        $query = $db->getQuery(true)
            ->select([
                'COUNT(DISTINCT a.id) AS total_assignments',
                'COUNT(DISTINCT CASE WHEN s.id IS NOT NULL THEN a.id END) AS submitted',
                "COUNT(DISTINCT CASE WHEN s.review_status = 'pending' THEN a.id END) AS pending",
                "COUNT(DISTINCT CASE WHEN s.review_status = 'approved' THEN a.id END) AS approved",
                "COUNT(DISTINCT CASE WHEN s.review_status = 'revision_required' THEN a.id END) AS revision_required"
            ])
            ->from(
                $db->quoteName(
                    '#__teamflow_task_groups',
                    'tg'
                )
            )
            ->join(
                'INNER',
                $db->quoteName(
                    '#__teamflow_assignments',
                    'a'
                ) . ' ON a.task_group_id = tg.id'
            )
            ->join(
                'LEFT',
                $db->quoteName(
                    '#__teamflow_submissions',
                    's'
                ) . ' ON s.id = (
                    SELECT s2.id
                    FROM ' . $db->quoteName(
                        '#__teamflow_submissions'
                    ) . ' AS s2
                    WHERE s2.assignment_id = a.id
                    ORDER BY s2.attempt_no DESC, s2.id DESC
                    LIMIT 1
                )'
            )
            ->where(
                'tg.group_id = ' . (int) $group_id
            );

        $db->setQuery($query);

        $result = $db->loadAssoc() ?: [];

        return [
            'total_assignments' =>
                (int) ($result['total_assignments'] ?? 0),
            'submitted' =>
                (int) ($result['submitted'] ?? 0),
            'pending' =>
                (int) ($result['pending'] ?? 0),
            'approved' =>
                (int) ($result['approved'] ?? 0),
            'revision_required' =>
                (int) ($result['revision_required'] ?? 0)
        ];
    }
}
