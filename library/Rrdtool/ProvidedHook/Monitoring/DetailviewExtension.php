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
			ob_start();
			?>
			<h2><?php echo $view->translate("Graphs"); ?></h2>
			<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=year" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=year" alt=""/></a><figcaption>1 <?php echo $view->translate("Year"); ?></figcaption></figure>
			<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=month" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=month" alt=""/></a><figcaption>1 <?php echo $view->translate("Month"); ?></figcaption></figure>
			<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=week" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=week" alt=""/></a><figcaption>1 <?php echo $view->translate("Week"); ?></figcaption></figure>
			<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=day" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=day" alt=""/></a><figcaption>1 <?php echo $view->translate("Day"); ?></figcaption></figure>
			<figure><a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=hours" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=hours" alt=""/></a><figcaption>4 <?php echo $view->translate("Hours"); ?></figcaption></figure>
			<?php
			return ob_get_clean();
		}
	}

}
