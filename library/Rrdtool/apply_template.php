<?php

$ds_name = array();
$this->DS = array();
if (is_string($xml)) $xml = simplexml_load_file($xml);

$i = 1;
foreach ($xml->DATASOURCE as $data) {
	$data->RRDFILE = str_replace(dirname($data->RRDFILE), rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host, $data->RRDFILE);
	foreach ($data as $key => $val) {
		$this->DS[$i][$key] = (string) $val;
		$key = strtoupper($key);
		$$key[$i] = (string) $val;
	}
	$ds_name[$i] = $data->NAME;
	$i++;
}

$rrdfile = $data->RRDFILE;
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
if (file_exists(SYSPATH . "/templates/" . $template . ".php")) {
	require(SYSPATH . "/templates/" . $template . ".php");
} elseif (file_exists(SYSPATH . "/library/vendor/pnp4nagios/templates/" . $template . ".php")) {
	require(SYSPATH . "/library/vendor/pnp4nagios/templates/" . $template . ".php");
} else require(SYSPATH . "/library/vendor/pnp4nagios/templates/default.php");
ob_end_clean();
