<?php

namespace Joomla\Component\Teamflow\Site\View\Reports;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $stats = [];

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        // Chỉ Super User được xem báo cáo thử nghiệm.
        if ($user->guest || !$user->authorise('core.admin')) {
            throw new \Exception(
                'Bạn không có quyền xem báo cáo.',
                403
            );
        }

        $groupId = $app->input->getInt('group_id', 0);

        if ($groupId <= 0) {
            throw new \InvalidArgumentException(
                'Mã nhóm không hợp lệ.'
            );
        }

        $model = $this->getModel('Reports');

        if (!$model) {
            throw new \RuntimeException(
                'Không tìm thấy ReportsModel.'
            );
        }

        $this->stats = $model->getSubmissionStatsByGroup(
            $groupId
        );

        parent::display($tpl);
    }
}
