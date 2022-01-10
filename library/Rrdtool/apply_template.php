<?php

$ds_name = array();
$this->DS = array();
if (is_string($xml)) $xml = simplexml_load_file($xml);

$i = 1;
foreach ($xml->DATASOURCE as $data) {
	$data->RRDFILE = str_replace(dirname($data->RRDFILE), rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host, $data->RRDFILE);
	if (preg_match("/^[A-Z]__(_used)?$/", $data->NAME)) $data->NAME = str_replace(" used", "", $data->LABEL);
	foreach ($data as $key => $val) {
		$val = addslashes($val);
		$this->DS[$i - 1][$key] = $val;
		$key = strtoupper($key);
		$$key[$i] = $val;
	}
	if (count($xml->DATASOURCE) == 1) $ds_name[$i] = $data->LABEL;
	$i++;
}

$hostname = $xml->NAGIOS_DISP_HOSTNAME;
$servicedesc = $xml->NAGIOS_DISP_SERVICEDESC;
$this->MACRO = array(
	"DISP_HOSTNAME" => $hostname,
	"DISP_SERVICEDESC" => $servicedesc,
	"TIMET" => $xml->NAGIOS_TIMET,
);

require_once(SYSPATH . "/library/Rrdtool/Kohana_Exception.php");
require_once(SYSPATH . "/library/Rrdtool/rrd.php");
require_once(SYSPATH . "/library/Rrdtool/pnp.php");

$template = str_replace("check_check_", "check_", "check_" . $data->TEMPLATE);
if ($host == ".pnp-internal") $template = "pnp-runtime";

ob_start();
if (version_compare(PHP_VERSION, "8.0.0", "<")) {
	$oldlocale = setlocale(LC_ALL, 0);
	setlocale(LC_NUMERIC, "C", "en_US", "en_US.utf8", "en_US.UTF-8");
}
if (file_exists(SYSPATH . "/templates/" . $template . ".php")) {
	require(SYSPATH . "/templates/" . $template . ".php");
} elseif (file_exists(SYSPATH . "/library/vendor/pnp4nagios/templates/" . $template . ".php")) {
	require(SYSPATH . "/library/vendor/pnp4nagios/templates/" . $template . ".php");
} elseif (file_exists(SYSPATH . "/templates/default.php")) {
	require(SYSPATH . "/templates/default.php");
} else require(SYSPATH . "/library/vendor/pnp4nagios/templates/default.php");
if (version_compare(PHP_VERSION, "8.0.0", "<")) setlocale(LC_ALL, $oldlocale);
ob_end_clean();

if (is_string($params)) {
	foreach ($def as &$data) $data = addcslashes($data, ":");
} else {
	foreach ($ds_name as &$data) $data = stripslashes($data);
}
