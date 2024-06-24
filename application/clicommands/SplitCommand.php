<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;

class SplitCommand extends Command {

	/**
	 * Split RRD file
	 * USAGE
	 *
	 *   icingacli rrdtool split Host/Service.xml
	 */
	public function defaultAction() {
		$config = $this->Config();
		$params = $this->params->getAllStandalone();
		if (count($params) != 1) return $this->showUsage("default");

		ob_start();
		passthru($config->get("rrdtool", "rrdtool", "rrdtool"), $return);
		if ($return) exit("The rrdtool binary is required for this function...");
		ob_end_clean();

		$path = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/";
		$file = end($params);
		if (!file_exists($path . $file)) $this->fail("XML does not exist");
		$xml = file_get_contents($path . $file);
		if (strpos($xml, "<RRD_STORAGE_TYPE>MULTIPLE") !== FALSE) $this->fail("RRD is already split in multiple files");
		$xml = @simplexml_load_string($xml);
		if (libxml_get_last_error() !== FALSE) $this->fail("XML is invalid");

		$rrdcached = $config->get("rrdtool", "rrdcached", "");
		if ($rrdcached) {
			$rrdcached = "--daemon=" . $rrdcached . " ";
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " flushcached " . $rrdcached . "\"" . $xml->DATASOURCE->RRDFILE . "\"", $return);
			if ($return) exit();
		}

		foreach ($xml->DATASOURCE as $datasource) {
			$datasource->RRDFILE = str_replace(".rrd", "_" . $datasource->NAME . ".rrd", $datasource->RRDFILE);
			copy($xml->NAGIOS_RRDFILE, $datasource->RRDFILE);

			$tune = "";
			for ($i = 1; $i <= count($xml->DATASOURCE); $i++) {
				if ($i == $datasource->DS) continue;
				$tune .= " DEL:" . $i;
			}
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " tune " . $rrdcached . "\"" . $datasource->RRDFILE . "\"" . $tune, $return);
			if ($datasource->DS != 1) {
				passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " tune " . $rrdcached . "\"" . $datasource->RRDFILE . "\" --data-source-rename " . $datasource->DS . ":1", $return);
				$datasource->DS = 1;
			}
			if ($return) unlink($datasource->RRDFILE);

			$datasource->RRD_STORAGE_TYPE = "MULTIPLE";
		}

		$datetime = date(".Y-m-d_His");
		rename($path . $file, $path . $file . $datetime);
		rename($xml->NAGIOS_RRDFILE, $xml->NAGIOS_RRDFILE . $datetime);

		$xml->NAGIOS_RRDFILE = "";
		$xml = preg_replace("/<([^\/]+)\/>/", "<$1></$1>", $xml->asXML());
		file_put_contents($path . $file, $xml);
	}
}
