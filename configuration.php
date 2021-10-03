<?php

$this->provideConfigTab("settings", array(
	"title" => $this->translate("Configure rrdtool"),
	"label" => $this->translate("Settings"),
	"url" => "config/settings"
));

$this->provideConfigTab("stats", array(
	"title" => $this->translate("Stats"),
	"label" => $this->translate("Stats"),
	"url" => "graph/view?host=.pnp-internal&range=week"
));

$this->provideJsFile("vendor/jquery.imgareaselect.min.js");
