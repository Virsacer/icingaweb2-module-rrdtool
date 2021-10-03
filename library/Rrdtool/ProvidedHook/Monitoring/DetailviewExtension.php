<?php

namespace Icinga\Module\Rrdtool\ProvidedHook\Monitoring;

use Icinga\Application\Config;
use Icinga\Module\Monitoring\Hook\DetailviewExtensionHook;
use Icinga\Module\Monitoring\Object\MonitoredObject;

class DetailviewExtension extends DetailviewExtensionHook {

	public function getHtmlForObject(MonitoredObject $object) {
		$config = Config::module("rrdtool");
		$host = $object->type == MonitoredObject::TYPE_HOST ? $object->host : $object->host->getName();
		$service = $object->type == MonitoredObject::TYPE_SERVICE ? $object->service_display_name : "_HOST_";
		$service = str_replace(array("/", " "), "_", $service);
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . $service . ".xml";
		if (file_exists($xml)) {
			$view = $this->getView();
			$params = array("host" => $host);
			if ($service != "_HOST_") $params['service'] = $service;
			ob_start();
			?>
			<h2><?php echo $view->translate("Graphs"); ?></h2>
			<a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=year" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=year" alt=""/></a>
			<a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=month" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=month" alt=""/></a>
			<a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=week" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=week" alt=""/></a>
			<a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=day" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=day" alt=""/></a>
			<a href="<?php echo $view->href("rrdtool/graph/view", $params); ?>&amp;range=hours" data-base-target="_next"><img src="<?php echo $view->href("rrdtool/graph?thumb", $params); ?>&amp;range=hours" alt=""/></a>
			<?php
			return ob_get_clean();
		}
	}

}
