<?php

namespace Icinga\Module\Rrdtool\Controllers;

use Icinga\Web\Controller;

class GraphController extends Controller {

	public function init() {
		if (isset($_GET['image']) || isset($_GET['thumb']) || isset($_GET['large'])) {
			$config = $this->Config();
			$host = $this->params->get("host", "");
			$service = $host == ".pnp-internal" ? "runtime" : $this->params->get("service", "_HOST_");
			$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
			if (file_exists($xml)) {
				if (isset($_GET['thumb'])) {
					$params = "--only-graph --width 96 --height 32 ";
				} elseif (isset($_GET['large'])) {
					$params = "--width 1000 --height 200 ";
				} else {
					$params = "--width 500 --height 100 ";
				}
				$range = $this->parseRange($this->params->get("range", ""));
				$params .= "--start " . $range['start'] . " --end " . $range['end'] . " ";

				require($this->Module()->getBaseDir() . "/library/Rrdtool/apply_template.php");

				$sourcename = array_search($this->params->get("sourcename", ""), $ds_name);
				$datasource = $sourcename !== FALSE ? $sourcename : $this->params->get("datasource", array_key_first($def));
				if (!preg_match_all("/(-v |--vertical-label)/i", $opt[$datasource], $match)) $params .= "--vertical-label=' ' ";

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
		$range = $this->parseRange($this->params->get("range", ""));
		$this->getTabs()->activate($range['range']);

		$host = $this->params->get("host", "");
		$params = array("host" => $host);
		$service = $this->params->get("service", "_HOST_");
		if ($service != "_HOST_") $params['service'] = $service;
		$params['range'] = $range['range'] == "custom" ? $range['start'] . "-" . $range['end'] : $range['range'];
		$this->view->start = $range['start'];
		$this->view->end = $range['end'];
		$this->view->params = $params;

		$def = array();
		$ds_name = array();
		$config = $this->Config();
		if ($host == ".pnp-internal") $service = "runtime";
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
		if (file_exists($xml)) require($this->Module()->getBaseDir() . "/library/Rrdtool/apply_template.php");
		$this->view->defs = $def;
		$this->view->ds_name = $ds_name;
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
		$range = $this->parseRange($this->params->get("range", ""));
		if ($range['range'] == "custom") $tabs->add("custom", array("title" => $this->translate("Custom"), "url" => $params . "&range=" . $range['start'] . "-" . $range['end']));
		return $tabs;
	}

	public static function parseRange($range) {
		if (preg_match("/^([0-9]+)-(Q?[0-9]+)$/", $range, $matches)) {
			$range = "custom";
			if (strlen($matches[1]) == 4 && $matches[2] >= 1 && $matches[2] <= 12) {
				$start = strtotime($matches[1] . "-" . str_pad($matches[2], 2, "0", STR_PAD_LEFT) . "-01");
				$end = mktime(0, 0, 0, $matches[2] + 1, 1, $matches[1]) - 1;
			} elseif (strlen($matches[1]) == 4 && $matches[2][0] == "Q" && $matches[2][1] >= 1 && $matches[2][1] <= 4) {
				$start = strtotime($matches[1] . "-" . str_replace(array("Q1", "Q2", "Q3", "Q4"), array("01", "04", "07", "10"), $matches[2]) . "-01");
				$end = strtotime($matches[1] . "-" . str_replace(array("Q1", "Q2", "Q3", "Q4"), array("03-31", "06-30", "09-30", "12-31"), $matches[2]) . " 23:59:59");
			} else {
				unset($matches[0]);
				$start = min($matches);
				$end = max($matches);
			}
		} else {
			switch ($range) {
				case "year":
					$start = time() - 365 * 86400;
					break;
				case "month":
					$start = time() - 30 * 86400;
					break;
				case "week":
					$start = time() - 7 * 86400;
					break;
				case "day":
					$start = time() - 86400;
					break;
				case "hours":
					$start = time() - 4 * 3600;
					break;
				case "hour":
					$range = "custom";
					$start = time() - 3600;
					break;
				default:
					$range = "month";
					$start = time() - 30 * 86400;
			}
			$end = time();
		}
		return array("range" => $range, "start" => $start, "end" => $end);
	}

}
