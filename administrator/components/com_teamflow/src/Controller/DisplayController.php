<?php

namespace Joomla\Component\Teamflow\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'tasks';

    public function display(
        $cachable = false,
        $urlparams = []
    ) {
        return parent::display($cachable, $urlparams);
    }
}
