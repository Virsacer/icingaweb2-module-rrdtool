<?php

namespace Icinga\Module\Rrdtool\Controllers;

use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Module\Rrdtool\Rrdtool;
use Icinga\Web\Controller;
use Icinga\Web\StyleSheet;

class GraphController extends Controller {

	protected $DS, $MACRO;

	public function init() {
		if (isset($_GET['image']) || isset($_GET['thumb']) || isset($_GET['large']) || isset($_GET['huge']) || preg_match("/^([0-9]+)([xX\*])([0-9]+)(&.*)?$/", $_SERVER['QUERY_STRING'])) {
			$config = $this->Config();
			$host = $this->params->get("host", "");
			$service = $host == ".pnp-internal" ? "runtime" : $this->params->get("service", "_HOST_");
			$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . Rrdtool::cleanup($host) . "/" . Rrdtool::cleanup($service) . ".xml";
			if (file_exists($xml)) {
				if (isset($_GET['image'])) {
					$params = "--width=500 --height=100 ";
				} elseif (isset($_GET['thumb'])) {
					$params = "--width=96 --height=32 --only-graph --color=BACK#00000000 --color=CANVAS#00000000 ";
				} elseif (isset($_GET['large'])) {
					$params = "--width=1000 --height=200 ";
				} elseif (isset($_GET['huge'])) {
					$params = "--width=1600 --height=900 --full-size-mode ";
				} elseif (preg_match("/^([0-9]+)([xX\*])([0-9]+)(&.*)?$/", $_SERVER['QUERY_STRING'], $matches)) {
					$params = "--width=" . $matches[1] . " --height=" . $matches[3] . " ";
					if ($matches[2] == "X") $params .= "--only-graph ";
					if ($matches[2] == "*") $params .= "--full-size-mode ";
				}
				$range = Rrdtool::parseRange($this->params->get("range", ""));
				$params .= "--start=" . $range['start'] . " --end=" . $range['end'] . " ";

				require($this->Module()->getBaseDir() . "/library/Rrdtool/apply_template.php");

				$return = "";
				$datasource = $this->params->get("datasource", array_key_first($opt));
				if (!array_key_exists($datasource, $opt)) {
					$datasource = array_search($datasource, $ds_name);
					if ($datasource === FALSE) $return = $data = "No such datasource";
				}

				if (empty($return)) {
					if (!preg_match_all("/(-v |--vertical-label)/i", $opt[$datasource], $match)) $params .= "--vertical-label=\" \" ";

					if (!isset($_GET['thumb'])) {
						$theme = Config::app()->get("themes", "default", StyleSheet::DEFAULT_THEME);
						$user = $this->Auth()->getUser()->getPreferences();
						$theme = $user->getValue("icingaweb", "theme", $theme);
						$file = StyleSheet::getThemeFile($theme);
						$file = $file !== NULL ? @file_get_contents($file) : FALSE;
						if (!$file || strpos($file, "@light-mode:") !== FALSE || strpos($file, "@body-bg-color:") === FALSE) {
							if (!$user->getValue("icingaweb", "theme_mode", StyleSheet::DEFAULT_MODE != "none")) $params .= \rrd::darkteint();
						} elseif (strpos($file, "@body-bg-color:") !== FALSE) {
							preg_match("/@body-bg-color:\s*(.*?);/", $file, $color);
							if ($color[1][0] == "@") {
								$base = @file_get_contents(Icinga::app()->getBaseDir("public") . "/css/icinga/base.less");
								preg_match("/" . $color[1] . ":\s*(.*?);/", $file . $base, $color);
							}
							if (preg_match("/^#([0-9a-fA-F]{1,2})([0-9a-fA-F]{1,2})([0-9a-fA-F]{1,2})$/", $color[1], $color)) {
								$color[1] = hexdec(str_pad($color[1], 2, $color[1])) * .299;
								$color[2] = hexdec(str_pad($color[2], 2, $color[2])) * .587;
								$color[3] = hexdec(str_pad($color[3], 2, $color[3])) * .114;
								if ($color[1] + $color[2] + $color[3] < 128) $params .= \rrd::darkteint();
							}
						}
					}

					if (extension_loaded("rrd")) {
						$params = preg_replace("/(.+ |=)'([^']*)'/", "$1\"$2\"", $params . rtrim($opt[$datasource]) . " " . $def[$datasource]);
						$params = preg_split('/\s(?=([^"]*"[^"]*")*[^"]*$)/', $params, -1, PREG_SPLIT_NO_EMPTY);
						foreach ($params as $key => $val) {
							if (preg_match("/(.*)\"(.*)\"(.*)/", $val, $match)) {
								if (strpos($match[1], ":") !== FALSE) $match[2] = addcslashes($match[2], ":");
								$params[$key] = $match[1] . str_replace("\\\\", "\\", $match[2]) . ($match[3] ?? "");
							}
						}
						try {
							$rrd = new \RRDGraph("-");
							$rrd->setOptions($params);
							$data = $rrd->saveVerbose()['image'];
						} catch (\Exception $return) {
							$data = $return->getMessage();
						}
					} else {
						ob_start();
						passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " graph - " . $params . rtrim($opt[$datasource]) . " " . addcslashes($def[$datasource], ":") . " 2>&1", $return);
						$data = ob_get_clean();
					}
				}
			} else $return = $data = "XML missing";

			header("Content-type: image/png");
			if ($return || substr($data, 1, 3) != "PNG") {
				$size = 2;
				$width = 1;
				$fontwidth = imagefontwidth($size);
				$fontheight = imagefontheight($size);
				$data = preg_replace("/ (--|AREA|CDEF|COMMENT|GPRINT|HRULE|LINE|VDEF)/", "\n$1", $data);
				$lines = explode("\n", $data . "\n");
				foreach ($lines as $line) {
					$width = max($width, $fontwidth * mb_strlen($line));
				}
				$image = imagecreatetruecolor($width + $fontwidth, $fontheight * count($lines));
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

	public function indexAction() {
		$range = Rrdtool::parseRange($this->params->get("range", ""));
		$this->getTabs()->activate($range['tab']);

		$host = $this->params->get("host", "");
		$params = array("host" => $host);
		$service = $this->params->get("service", "_HOST_");
		if ($service != "_HOST_") {
			$params['service'] = $service;
			$this->view->title = $service . " " . $this->translate("on") . " " . $host;
		} else $this->view->title = $host;
		$params['range'] = $range['range'];
		$this->view->icingadb = $this->hasPermission("module/icingadb") && Icinga::app()->getModuleManager()->hasLoaded("icingadb");
		$this->view->size = $this->params->get("size", "image");
		$this->view->start = $range['start'];
		$this->view->end = $range['end'];
		$this->view->params = $params;

		$def = array();
		$ds_name = array();
		$config = $this->Config();
		if ($host == ".pnp-internal") $service = "runtime";
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . Rrdtool::cleanup($host) . "/" . Rrdtool::cleanup($service) . ".xml";
		if (file_exists($xml)) require($this->Module()->getBaseDir() . "/library/Rrdtool/apply_template.php");
		$datasource = $this->params->get("datasource", "");
		if ($datasource != "") {
			if (!array_key_exists($datasource, $opt)) $datasource = array_search($datasource, $ds_name);
			if ($datasource !== FALSE) $def = array($datasource => $def[$datasource]);
		}
		$this->view->defs = $def;
		$this->view->ds_name = $ds_name;
	}

	public function getTabs() {
		$tabs = parent::getTabs();
		$host = $this->params->get("host", "");
		$service = $this->params->get("service", "");
		$params = "rrdtool/graph?host=" . $host;
		if ($service) $params .= "&service=" . $service;
		if ($host == ".pnp-internal" && $this->hasPermission("config/modules")) {
			$tabs->add("Module: rrdtool", array("label" => $this->translate("Module: rrdtool"), "url" => "config/module?name=rrdtool"));
			$tabs->add("settings", array("title" => $this->translate("Configure rrdtool"), "label" => $this->translate("Settings"), "url" => "rrdtool/config/settings"));
		}
		$size = $this->params->get("size", "");
		if ($size) $size = "&size=" . $size;
		$datasource = $this->params->get("datasource", "");
		if ($datasource) $datasource = "&datasource=" . $datasource;
		$tabs->add("year", array("title" => "1 " . $this->translate("Year"), "url" => $params . "&range=year" . $size . $datasource));
		$tabs->add("month", array("title" => "1 " . $this->translate("Month"), "url" => $params . "&range=month" . $size . $datasource));
		$tabs->add("week", array("title" => "1 " . $this->translate("Week"), "url" => $params . "&range=week" . $size . $datasource));
		$tabs->add("day", array("title" => "1 " . $this->translate("Day"), "url" => $params . "&range=day" . $size . $datasource));
		$tabs->add("hours", array("title" => "4 " . $this->translate("Hours"), "url" => $params . "&range=hours" . $size . $datasource));
		$range = Rrdtool::parseRange($this->params->get("range", ""));
		if ($range['tab'] == "custom") $tabs->add("custom", array("title" => $this->translate("Custom"), "url" => $params . "&range=" . $range['range']));
		return $tabs;
	}

}
