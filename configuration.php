<?php

$this->provideConfigTab("settings", array(
	"title" => $this->translate("Configure rrdtool"),
	"label" => $this->translate("Settings"),
	"url" => "config/settings"
));

if (file_exists(rtrim($this->getConfig()->get("rrdtool", "rrdpath", "/var/lib/icingaweb2/rrdtool"), "/") . "/.pnp-internal/runtime.xml")) {
	$this->provideConfigTab("stats", array(
		"title" => $this->translate("Show statistics"),
		"label" => $this->translate("Stats"),
		"url" => "graph?host=.pnp-internal&range=week"
	));
}

$this->provideJsFile("vendor/jquery.imgareaselect.min.js");
