<?php

namespace Icinga\Module\Rrdtool\Forms\Config;

use Icinga\Forms\ConfigForm;
use Zend_Validate_Regex;

class SettingsForm extends ConfigForm {

	public function init() {
		$this->setSubmitLabel($this->translate("Save Changes"));
	}

	public function createElements(array $formData) {
		$this->addElement("text", "rrdtool_rrdtool", array(
			"label" => $this->translate("Path to rrdtool binary"),
			"description" => $this->translate("The path where rrdtool executable is located."),
			"placeholder" => "rrdtool",
		));
		$this->addElement("text", "rrdtool_rrdpath", array(
			"label" => $this->translate("Path to rrd files"),
			"description" => $this->translate("The path where the rrd databases are located."),
			"placeholder" => "/var/lib/pnp4nagios",
		));
		$this->addElement("text", "rrdtool_thumbnails", array(
			"label" => $this->translate("Override max thumbnails"),
			"description" => $this->translate("Max number of graphs to generate thumbnails for (\"check_command_1\":2,\"check_command_2\":3)"),
			"placeholder" => "\"nrpe_check_disk\":3",
			"validators" => array(
				new Zend_Validate_Regex("/^\"[^\"]+\":[1-9]*[0-9](, ?\"[^\"]+\":[1-9]*[0-9])*$/"),
			)
		));
	}

}
