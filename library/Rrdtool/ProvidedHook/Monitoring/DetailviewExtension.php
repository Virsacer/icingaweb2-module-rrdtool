<?php

namespace Icinga\Module\Rrdtool\ProvidedHook\Monitoring;

use Icinga\Application\Config;
use Icinga\Module\Monitoring\Hook\DetailviewExtensionHook;
use Icinga\Module\Monitoring\Object\MonitoredObject;

class DetailviewExtension extends DetailviewExtensionHook {

	public function getHtmlForObject(MonitoredObject $object) {
		$config = Config::module("rrdtool");
		$host = $object->getType() == MonitoredObject::TYPE_HOST ? $object->getName() : $object->getHost()->getName();
		$service = $object->getType() == MonitoredObject::TYPE_SERVICE ? $object->getName() : "_HOST_";
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
		if (file_exists($xml)) {
			$view = $this->getView();
			$params = array("host" => $host);
			if ($service != "_HOST_") $params['service'] = $service;

			$xml = simplexml_load_file($xml);
			require(dirname(__FILE__) . "/../../apply_template.php");
			$overrides = json_decode("{" . $config->get("rrdtool", "thumbnails", '"nrpe_check_disk":3') . "}", TRUE);
			if (is_array($overrides) && array_key_exists((string) $xml->NAGIOS_CHECK_COMMAND, $overrides)) {
				$thumbnails = min(count($def), $overrides[(string) $xml->NAGIOS_CHECK_COMMAND]);
			} else $thumbnails = 1;
			$datasource = array_key_first($def);

			ob_start();
			for ($i = 0; $i < $thumbnails; $i++) {
				if ($i == 0) echo "<h2>" . $view->translate("Graphs") . "</h2>";
				?>
				<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=year" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;datasource=<?php echo $datasource + $i; ?>&amp;range=year" alt=""/></a><figcaption>1 <?php echo $view->translate("Year"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=month" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;datasource=<?php echo $datasource + $i; ?>&amp;range=month" alt=""/></a><figcaption>1 <?php echo $view->translate("Month"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=week" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;datasource=<?php echo $datasource + $i; ?>&amp;range=week" alt=""/></a><figcaption>1 <?php echo $view->translate("Week"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=day" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;datasource=<?php echo $datasource + $i; ?>&amp;range=day" alt=""/></a><figcaption>1 <?php echo $view->translate("Day"); ?></figcaption></figure>
				<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=hours" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;datasource=<?php echo $datasource + $i; ?>&amp;range=hours" alt=""/></a><figcaption>4 <?php echo $view->translate("Hours"); ?></figcaption></figure>
				<br/>
				<?php
			}
			return ob_get_clean();
		}
	}

}
