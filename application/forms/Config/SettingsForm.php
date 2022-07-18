<?php

namespace Icinga\Module\Rrdtool\Forms\Config;

use Icinga\Forms\ConfigForm;
use Zend_Validate_Regex;

class SettingsForm extends ConfigForm {

	public function init() {
		$this->setSubmitLabel($this->translate("Save changes"));
	}

	public function createElements(array $formData) {
		$this->addElement("text", "rrdtool_rrdpath", array(
			"label" => $this->translate("Path to RRD and XML files"),
			"description" => $this->translate("The path where the RRD and XML files are located."),
			"placeholder" => "/var/lib/icinga2/rrdtool",
		));
		$this->addElement("text", "rrdtool_templates", array(
			"label" => $this->translate("Path to templates"),
			"description" => $this->translate("The path where the templates are located."),
			"placeholder" => "templates",
		));
		$this->addElement("note", "rrdtool_spacer_1", array(
			"value" => "<br/>",
		));
		$this->addElement("text", "rrdtool_perfdata", array(
			"label" => $this->translate("Path to PerfdataWriter output"),
			"description" => $this->translate("The path where the PerfdataWriter output is located."),
			"placeholder" => "/var/spool/icinga2/perfdata",
		));
		$this->addElement("text", "rrdtool_multiple", array(
			"label" => $this->translate("Checks with multiple RRDs"),
			"description" => $this->translate("Checkcommands that should use a RRD for each datasource.\n(\"check_command_1\",\"check_command_2\")"),
			"placeholder" => "",
			"validators" => array(
				new Zend_Validate_Regex("/^\"[^\"]+\"(, ?\"[^\"]+\")*$/"),
			),
		));
		$this->addElement("checkbox", "rrdtool_process", array(
			"label" => $this->translate("Enable perfdata processing"),
			"description" => $this->translate("Enable the CLI command for processing and writing perfdata to RRDs.\n(\"icingacli rrdtool process\")"),
		));
		$this->addElement("note", "rrdtool_spacer_2", array(
			"value" => "<br/>",
		));
		$this->addElement("note", "rrdtool_extension", array(
			"label" => $this->translate("PHP RRD Extension"),
			"value" => "<i class=\"icon-" . (extension_loaded("rrd") ? "ok" : "cancel") . "\"></i>",
			"decorators" => array(
				"Label",
				array(array("labelWrap" => "HtmlTag"), array("tag" => "div", "class" => "control-label-group")),
				"ViewHelper",
				array("HtmlTag", array("tag" => "div", "class" => "control-group")),
			),
		));
		$this->addElement("text", "rrdtool_rrdtool", array(
			"label" => $this->translate("Path to rrdtool binary"),
			"description" => $this->translate("The path where the rrdtool executable is located.\n(Only used if PHP extension is not available.)"),
			"placeholder" => "rrdtool",
		));
		$this->addElement("text", "rrdtool_thumbnails", array(
			"label" => $this->translate("Override max thumbnails"),
			"description" => $this->translate("Max number of graphs to generate thumbnails for.\n(\"check_command_1\":2,\"check_command_2\":3)"),
			"placeholder" => "\"nrpe_check_disk\":3",
			"validators" => array(
				new Zend_Validate_Regex("/^\"[^\"]+\":[1-9]*[0-9](, ?\"[^\"]+\":[1-9]*[0-9])*$/"),
			),
		));
	}

}
