<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;

class CheckCommand extends Command {

	/**
	 * Check if XML files are beeing updated and if they contain error messages
	 * USAGE
	 *
	 *   icingacli rrdtool check
	 */
	public function defaultAction() {
		$out = "";
		$status = 0;
		$time = time();
		$config = $this->Config();
		$updated = array("HOST" => 0, "SERVICE" => 0, "RRDTOOL" => 0);
		$path = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/";

		libxml_use_internal_errors(TRUE);
		$files = glob($path . "*/*.xml");
		array_unshift($files, $path . ".pnp-internal/runtime.xml");
		foreach ($files as $file) {
			$xml = simplexml_load_file($file);
			if (libxml_get_last_error() !== FALSE) {
				libxml_clear_errors();
				continue;
			}

			$type = $xml->NAGIOS_HOSTNAME == ".pnp-internal" ? "RRDTOOL" : str_replace("PERFDATA", "", $xml->NAGIOS_DATATYPE);
			if (intval($xml->NAGIOS_TIMET) > $updated[$type]) $updated[$type] = intval($xml->NAGIOS_TIMET);

			if ($xml->RRD->RC == 0) continue;

			$messages = explode(", ", $xml->RRD->TXT);
			$messages = preg_grep("/(conversion of .* to float|Malformed perfdata|minimum one second step)/", $messages, PREG_GREP_INVERT);
			if (count($messages)) {
				$status = 2;
				$out .= "\n[CRITICAL] ";
			} else {
				if (!$status) $status = 1;
				$out .= "\n[WARNING] ";
			}
			$out .= $file . ": " . $xml->RRD->TXT;
		}

		if ($time - $updated['RRDTOOL'] > 300) {
			$status = 2;
			$out = "\n[CRITICAL] RRDs have not been updated since " . date("Y-m-d H:i:s", $updated['RRDTOOL']) . $out;
		} else {
			if ($time - $updated['SERVICE'] > 300) {
				$status = 2;
				$out = "\n[CRITICAL] Service RRDs have not been updated since " . date("Y-m-d H:i:s", $updated['SERVICE']) . $out;
			}
			if ($time - $updated['HOST'] > 300) {
				$status = 2;
				$out = "\n[CRITICAL] Host RRDs have not been updated since " . date("Y-m-d H:i:s", $updated['HOST']) . $out;
			}
		}

		$out = preg_replace("/" . preg_quote($path, "/") . "/", "", rtrim($out));

		if ($status == 2) {
			echo "CRITICAL: " . $out;
		} elseif ($status == 1) {
			echo "WARNING: " . $out;
		} else {
			echo "OK: " . count($files) . " XML files";
		}
		exit($status);
	}
}
