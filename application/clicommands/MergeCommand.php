<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;

class MergeCommand extends Command {

	/**
	 * Merge RRD files
	 * USAGE
	 *
	 *   icingacli rrdtool merge Host/ServiceOLD.rrd [...] Host/ServiceNEW.rrd
	 */
	public function defaultAction() {
		$config = $this->Config();
		$params = $this->params->getAllStandalone();
		if (count($params) < 2) return $this->showUsage("default");

		ob_start();
		passthru($config->get("rrdtool", "rrdtool", "rrdtool"), $return);
		if ($return) exit("The rrdtool binary is required for this function...");
		ob_end_clean();

		$data = "";
		$rrd = array();
		$path = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/";
		$dest = $path . array_pop($params);
		if (!is_writable($dest) && (file_exists($dest) || !is_writable(dirname($dest)))) $this->fail("Destination file is not writable");

		$rrdcached = $config->get("rrdtool", "rrdcached", "");
		if ($rrdcached) {
			$rrdcached = "--daemon=" . $rrdcached . " ";
			$files = " \"" . $path . implode("\" \"" . $path, $params) . "\"";
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " flushcached " . $rrdcached . $files, $return);
			if ($return) exit();
		}

		$last = end($params);
		foreach ($params as $file) {
			if (!file_exists($path . $file)) $this->fail("Source file '" . $file . "' does not exist");
			ob_start();
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " dump " . $rrdcached . "\"" . $path . $file . "\"", $return);
			$lines = explode("\n", trim(ob_get_clean()));
			foreach ($lines as $line) {
				if (preg_match("/<cf>(.*)<\/cf>/", $line, $match)) {
					$cf = $match[1];
				} elseif (preg_match("/<pdp_per_row>(.*)<\/pdp_per_row>/", $line, $match)) {
					$pdp = $match[1];
				} elseif (preg_match("/ \/ (\d+) --> (.*)/", $line, $match)) {
					$key = $cf . $pdp;
					if (!array_key_exists($key, $rrd)) $rrd[$key] = array();
					if (!array_key_exists($match[1], $rrd[$key]) || strpos($match[2], "NaN") === FALSE) {
						$rrd[$key][$match[1]] = $line;
					} else $line = $rrd[$key][$match[1]];
				}
				if ($file == $last) $data .= trim($line);
			}
		}

		$datetime = date(".Y-m-d_His");
		if (file_exists($dest)) rename($dest, $dest . $datetime);
		file_put_contents($dest . ".dump" . $datetime, $data);
		passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " restore \"" . $dest . ".dump" . $datetime . "\" \"" . $dest . "\"", $return);
		unlink($dest . ".dump" . $datetime);
	}
}
