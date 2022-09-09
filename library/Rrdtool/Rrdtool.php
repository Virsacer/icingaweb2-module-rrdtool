<?php

namespace Icinga\Module\Rrdtool;

use Icinga\Application\Config;
use Icinga\Application\Icinga;

class Rrdtool {

	static function graphs($host, $service) {
		$config = Config::module("rrdtool");
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
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
				if ($thumbnails > 1) $datasource = "&amp;datasource=" . array_keys($def)[$i];
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

}
