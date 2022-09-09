<?php

namespace Icinga\Module\Rrdtool\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\HostDetailExtensionHook;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Rrdtool\Rrdtool;
use ipl\Html\HtmlString;
use ipl\Html\ValidHtml;

class HostDetailExtension extends HostDetailExtensionHook {

	public function getHtmlForObject(Host $object): ValidHtml {
		return HtmlString::create(Rrdtool::graphs($object->name, "_HOST_"));
	}

}
