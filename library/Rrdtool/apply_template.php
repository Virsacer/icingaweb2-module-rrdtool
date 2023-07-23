<?php

use Icinga\Module\Rrdtool\Rrdtool;

$ds_name = array();
$this->DS = array();
if (is_string($xml)) $xml = simplexml_load_file($xml);

$i = 1;
foreach ($xml->DATASOURCE as $data) {
	$data->RRDFILE = str_replace(dirname($data->RRDFILE), rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . Rrdtool::cleanup($host), $data->RRDFILE);
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

require_once(SYSPATH . "/library/Rrdtool/rrd.php");
require_once(SYSPATH . "/library/Rrdtool/pnp.php");

$template = str_replace("check_check_", "check_", "check_" . $data->TEMPLATE);
if ($host == ".pnp-internal") $template = "pnp-runtime";

ob_start();
if (version_compare(PHP_VERSION, "8.0.0", "<")) {
	$oldlocale = setlocale(LC_NUMERIC, 0);
	setlocale(LC_NUMERIC, "C", "en_US", "en_US.utf8", "en_US.UTF-8");
}

$templates = rtrim($config->get("rrdtool", "templates", "templates"), "/") . "/";
if (substr($templates, 0, 1) != "/") $templates = SYSPATH . "/" . $templates;

if (file_exists($templates . $template . ".php")) {
	require($templates . $template . ".php");
} elseif (file_exists($templates . "pnp4nagios/" . $template . ".php")) {
	require($templates . "pnp4nagios/" . $template . ".php");
} elseif (file_exists($templates . "default.php")) {
	require($templates . "default.php");
} else require(SYSPATH . "/templates/pnp4nagios/default.php");
if (isset($oldlocale)) setlocale(LC_NUMERIC, $oldlocale);
ob_end_clean();

if ($hostname == ".pnp-internal") {
	$opt[1] = str_replace("process_perfdata.pl", "RRDTOOL", $opt[1]);
	$def[1] = str_replace(rrd::gprint("var1", array("MAX", "AVERAGE"), "%6.2lf$UNIT[1]"), rrd::gprint("var1", array("AVERAGE", "MIN", "MAX"), "%6.3lf$UNIT[1]"), $def[1]);
	$def[1] = str_replace(rrd::gprint("t_var1", array("MAX", "AVERAGE"), "%6.2lf$UNIT[1]"), rrd::gprint("t_var1", array("AVERAGE", "MIN", "MAX"), "%6.3lf$UNIT[1]"), $def[1]);
	for($i=2; $i <= count($DS); $i++) {
		$def[2] = str_replace(rrd::gprint("var$i", array("MAX", "AVERAGE"), "%4.0lf$UNIT[$i]"), rrd::gprint("var$i", array("AVERAGE", "MIN", "MAX"), "%4.0lf$UNIT[$i]"), $def[2]);
	}
}

foreach ($ds_name as $key => $val) $ds_name[$key] = stripslashes($val);
