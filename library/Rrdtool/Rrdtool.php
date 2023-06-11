<?php

namespace Icinga\Module\Rrdtool;

use Icinga\Application\Config;
use Icinga\Application\Icinga;

class Rrdtool {

	static function cleanup($string) {
		return str_replace(array(" ", "&", "/", ":", "\\", "*", "?", "\"", "<", ">", "|"), "_", $string);
	}

	public function graphs($host, $service) {
		$config = Config::module("rrdtool");
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . $this->cleanup($host) . "/" . $this->cleanup($service) . ".xml";
		if (file_exists($xml)) {
			$view = Icinga::app()->getViewRenderer()->view;
			$params = array("host" => $host);
			if ($service != "_HOST_") $params['service'] = $service;

			$thumbnails = 1;
			$overrides = json_decode("{" . $config->get("rrdtool", "thumbnails", '"nrpe_check_disk":3') . "}", TRUE);
			if (is_array($overrides)) {
				$xml = simplexml_load_file($xml);
				if (array_key_exists((string) $xml->NAGIOS_CHECK_COMMAND, $overrides)) {
					require(SYSPATH . "/library/Rrdtool/apply_template.php");
					$thumbnails = min(count($def), $overrides[(string) $xml->NAGIOS_CHECK_COMMAND]);
				}
			}

			ob_start();
			$datasource = "";
			for ($i = 0; $i < $thumbnails; $i++) {
				if ($i == 0) echo "<div class=\"icinga-module module-rrdtool\"><h2>" . mt("rrdtool", "Graphs") . "</h2>";
				if ($thumbnails > 1) {
					$datasource = array_keys($def)[$i];
					if (!empty($ds_name[$datasource])) echo $ds_name[$datasource] . "<br/>";
					$datasource = "&amp;datasource=" . $datasource;
				}
				?>
				<figure><a href="<?php echo $view->href("rrdtool/graph", $params); ?>&amp;range=year" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params) . $datasource; ?>&amp;range=year" alt=""/></a><figcaption>1 <?php echo mt("rrdtool", "Year"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph", $params); ?>&amp;range=month" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params) . $datasource; ?>&amp;range=month" alt=""/></a><figcaption>1 <?php echo mt("rrdtool", "Month"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph", $params); ?>&amp;range=week" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params) . $datasource; ?>&amp;range=week" alt=""/></a><figcaption>1 <?php echo mt("rrdtool", "Week"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph", $params); ?>&amp;range=day" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params) . $datasource; ?>&amp;range=day" alt=""/></a><figcaption>1 <?php echo mt("rrdtool", "Day"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph", $params); ?>&amp;range=hours" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params) . $datasource; ?>&amp;range=hours" alt=""/></a><figcaption>4 <?php echo mt("rrdtool", "Hours"); ?></figcaption></figure>
				<br/>
				<?php
			}
			if ($i) echo "</div>";
			return ob_get_clean();
		}
	}

	static function parseRange($range) {
		if (strlen($range) == 4 && intval($range) >= 1980) {
			$tab = "custom";
			$start = strtotime($range . "-01-01");
			$end = strtotime($range . "-12-31 23:59:59");
		} elseif (preg_match("/^([1-9][0-9]+)-(Q[1-4]|[0-9]+)$/", $range, $matches)) {
			$tab = "custom";
			if (strlen($matches[1]) == 4 && $matches[2] >= 1 && $matches[2] <= 12) {
				$start = strtotime($matches[1] . "-" . str_pad($matches[2], 2, "0", STR_PAD_LEFT) . "-01");
				$end = mktime(0, 0, 0, $matches[2] + 1, 1, $matches[1]) - 1;
			} elseif (strlen($matches[1]) == 4 && $matches[2][0] == "Q") {
				$start = strtotime($matches[1] . "-" . str_replace(array("Q1", "Q2", "Q3", "Q4"), array("01", "04", "07", "10"), $matches[2]) . "-01");
				$end = strtotime($matches[1] . "-" . str_replace(array("Q1", "Q2", "Q3", "Q4"), array("03-31", "06-30", "09-30", "12-31"), $matches[2]) . " 23:59:59");
			} else {
				unset($matches[0]);
				$start = min($matches);
				$end = max($matches);
			}
			if ($start < 315529200) $start = 315529200;
			if ($end < 315529200) $end = time();
		} else {
			$tab = $range;
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
					$tab = "custom";
					$start = time() - 3600;
					break;
				default:
					$range = $tab = "month";
					$start = time() - 30 * 86400;
			}
			$end = time();
		}
		return array("range" => $range, "start" => $start, "end" => $end, "tab" => $tab);
	}

}
