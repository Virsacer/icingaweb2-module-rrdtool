<?php

namespace Icinga\Module\Rrdtool\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\DetailviewExtensionHook;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Rrdtool\Rrdtool;

class DetailviewExtension extends DetailviewExtensionHook {

	public function getHtmlForObject(MonitoredObject $object) {
		if ($object->getType() == MonitoredObject::TYPE_HOST) {
			return (new Rrdtool())->graphs($object->getName(), "_HOST_");
		}
		return (new Rrdtool())->graphs($object->getHost()->getName(), $object->getName());
	}

}
