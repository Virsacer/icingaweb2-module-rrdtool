<?php

namespace Icinga\Module\rrdtool\Controllers;

use Icinga\Web\Controller;

class GraphController extends Controller {

	private $range = "";
	private $start = 0;
	private $end = 0;

	public function init() {
		if (isset($_GET['image']) || isset($_GET['thumb'])) {
			if (isset($_GET['thumb'])) {
				$params = "--only-graph --width 96 --height 32 ";
			} else {
				$params = "--width 500 --height 100 ";
			}

			$this->readRange();
			if ($this->start) $params .= "--start " . $this->start . " ";
			if ($this->end) $params .= "--end " . $this->end . " ";

			$config = $this->Config();
			$host = $this->params->get("host", "");
			$service = $host == ".pnp-internal" ? "runtime" : $this->params->get("service", "_HOST_");
			$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
			if (file_exists($xml)) {
				require($this->Module()->getBaseDir() . "/library/Rrdtool/apply_template.php");

				$sourcename = array_search($this->params->get("sourcename", ""), $ds_name);
				$datasource = $sourcename !== FALSE ? $sourcename : $this->params->get("datasource", array_key_first($def));
				if (!preg_match_all("/(-l|--vertical-label)/i", $opt[$datasource], $match)) $params .= "--vertical-label=' ' ";

				ob_start();
				passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " graph - " . $params . rtrim($opt[$datasource]) . " " . $def[$datasource], $return);
				$data = ob_get_clean();
			} else $return = $data = "XML missing";

			header("Content-type: image/png");
			if ($return || substr($data, 1, 3) != "PNG") {
				$size = 2;
				$width = 1;
				$lines = explode("\n", $data);
				$fontwidth = imagefontwidth($size);
				$fontheight = imagefontheight($size);
				foreach ($lines as $line) {
					$width = max($width, $fontwidth * mb_strlen($line));
				}
				$image = imagecreatetruecolor($width, $fontheight * count($lines) + (count($lines) - 1));
				imagefill($image, 0, 0, imagecolorallocate($image, 221, 221, 221));
				foreach ($lines as $offset => $line) {
					imagestring($image, $size, 0, $fontheight * $offset, $line, imagecolorallocate($image, 0, 0, 0));
				}
				imagepng($image);
			} else echo $data;
			exit();
		}
		parent::init();
	}

	public function viewAction() {
		$this->readRange();
		$this->getTabs()->activate($this->range);

		$host = $this->params->get("host", "");
		$params = array("host" => $host);
		$service = $this->params->get("service", "_HOST_");
		if ($service != "_HOST_") $params['service'] = $service;
		$params['range'] = $this->range == "custom" ? $this->start . "-" . $this->end : $this->range;
		$this->view->params = $params;

		$def = array();
		$ds_name = array();
		$config = $this->Config();
		if ($host == ".pnp-internal") $service = "runtime";
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
		if (file_exists($xml)) require($this->Module()->getBaseDir() . "/library/Rrdtool/apply_template.php");
		$this->view->defs = $def;
		$this->view->ds_name = $ds_name;
		$this->view->start = $this->start;
		$this->view->end = $this->end;
	}

	public function getTabs() {
		$tabs = parent::getTabs();
		$host = $this->params->get("host", "");
		$service = $this->params->get("service", "");
		$params = "rrdtool/graph/view?host=" . $host;
		if ($service) $params .= "&service=" . $service;
		if ($host == ".pnp-internal" && $this->hasPermission("config/modules")) {
			$tabs->add("Module: rrdtool", array("label" => $this->translate("Module: rrdtool"), "url" => "config/module?name=rrdtool"));
			$tabs->add("settings", array("title" => $this->translate("Configure rrdtool"), "label" => $this->translate("Settings"), "url" => "rrdtool/config/settings"));
		}
		$tabs->add("year", array("title" => "1 " . $this->translate("Year"), "url" => $params . "&range=year"));
		$tabs->add("month", array("title" => "1 " . $this->translate("Month"), "url" => $params . "&range=month"));
		$tabs->add("week", array("title" => "1 " . $this->translate("Week"), "url" => $params . "&range=week"));
		$tabs->add("day", array("title" => "1 " . $this->translate("Day"), "url" => $params . "&range=day"));
		$tabs->add("hours", array("title" => "4 " . $this->translate("Hours"), "url" => $params . "&range=hours"));
		if ($this->range == "custom") $tabs->add("custom", array("title" => $this->translate("Custom"), "url" => $params . "&range=" . $this->start . "-" . $this->end));
		return $tabs;
	}

	private function readRange() {
		if ($this->range != "") return;
		if (isset($_GET['range'])) {
			if (preg_match("/^([0-9]+)-([0-9]+)$/", $_GET['range'], $range)) {
				unset($range[0]);
				$this->start = min($range);
				$this->end = max($range);
				$this->range = "custom";
				return;
			}
			$this->range = $_GET['range'];
		}
		switch ($this->range) {
			case "year":
				$this->start = time() - 365 * 86400;
				break;
			case "month":
				$this->start = time() - 30 * 86400;
				break;
			case "week":
				$this->start = time() - 7 * 86400;
				break;
			case "day":
				$this->start = time() - 86400;
				break;
			case "hours":
				$this->start = time() - 4 * 3600;
				break;
			default:
				$this->range = "month";
				$this->start = time() - 30 * 86400;
		}
		$this->end = time();
	}

}
