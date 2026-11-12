<?php

namespace Joomla\Component\Teamflow\Site\View\Submission;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
    public array $submissions = [];

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $user = $app->getIdentity();

        $assignmentId = $app->input->getInt('assignment_id', 0);

        if ($user && !$user->guest && $assignmentId > 0) {
            $model = $this->getModel('Submission');

            if ($model) {
                $this->submissions = $model->getSubmissionsByAssignment(
                    $assignmentId,
                    (int) $user->id
                );
            }
        }

        parent::display($tpl);
    }
}
