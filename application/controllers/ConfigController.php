<?php

namespace Icinga\Module\Rrdtool\Controllers;

use Icinga\Module\Rrdtool\Forms\Config\SettingsForm;
use Icinga\Web\Controller;

class ConfigController extends Controller {

	public function init() {
		$this->assertPermission("config/modules");
		parent::init();
	}

	public function settingsAction() {
		$this->view->form = $form = new SettingsForm();
		$form->setIniConfig($this->Config())->handleRequest();
		$this->view->tabs = $this->Module()->getConfigTabs()->activate("settings");
	}

}
