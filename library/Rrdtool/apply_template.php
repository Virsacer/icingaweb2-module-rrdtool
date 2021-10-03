<?php

$ds_name = array();
$this->DS = array();
$xml = simplexml_load_file($xml);

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

require_once($this->Module()->getBaseDir() . "/library/Rrdtool/rrd.php");
require_once($this->Module()->getBaseDir() . "/library/Rrdtool/pnp.php");

$template = str_replace("check_check_", "check_", "check_" . $data->TEMPLATE);

ob_start();
if (file_exists($this->Module()->getBaseDir() . "/templates/" . $template . ".php")) {
	require_once($this->Module()->getBaseDir() . "/templates/" . $template . ".php");
} elseif (file_exists($this->Module()->getBaseDir() . "/library/vendor/pnp4nagios/templates/" . $template . ".php")) {
	require_once($this->Module()->getBaseDir() . "/library/vendor/pnp4nagios/templates/" . $template . ".php");
} elseif ($host == ".pnp-internal") {
	require_once($this->Module()->getBaseDir() . "/library/vendor/pnp4nagios/templates/pnp-runtime.php");
} else require_once($this->Module()->getBaseDir() . "/library/vendor/pnp4nagios/templates/default.php");
ob_end_clean();
