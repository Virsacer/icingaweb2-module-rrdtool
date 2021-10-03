<?php

namespace Icinga\Module\Rrdtool\Forms\Config;

use Icinga\Forms\ConfigForm;

class SettingsForm extends ConfigForm {

	public function init() {
		$this->setSubmitLabel($this->translate("Save Changes"));
	}

	public function createElements(array $formData) {
		$this->addElement("text", "rrdtool_rrdtool", array(
			"label" => $this->translate("Path to rrdtool binary"),
			"description" => $this->translate("The path where rrdtool executable is located."),
			"placeholder" => "rrdtool"
		));
		$this->addElement("text", "rrdtool_rrdpath", array(
			"label" => $this->translate("Path to rrd files"),
			"description" => $this->translate("The path where the rrd databases are located."),
			"placeholder" => "/var/lib/pnp4nagios"
		));
	}

}
