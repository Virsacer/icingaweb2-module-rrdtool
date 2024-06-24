<?php

namespace Icinga\Module\Rrdtool\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\ServiceDetailExtensionHook;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Rrdtool\Rrdtool;
use ipl\Html\HtmlString;
use ipl\Html\ValidHtml;

class ServiceDetailExtension extends ServiceDetailExtensionHook {

	public function getHtmlForObject(Service $object): ValidHtml {
		return HtmlString::create((new Rrdtool())->graphs($object->host->name, $object->name));
	}
}
