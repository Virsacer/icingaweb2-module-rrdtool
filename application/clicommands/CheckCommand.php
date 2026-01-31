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
		$exit = 0;
		$echo = array();
		$time = time();
		$config = $this->Config();
		$updated = array("HOST" => 0, "SERVICE" => 0, "RRDTOOL" => 0);
		$path = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icingaweb2/rrdtool"), "/") . "/";

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
				$echo["1-" . $file] = "[CRITICAL] " . $file . ": " . $xml->RRD->TXT;
				$exit = 2;
			} else {
				$echo["2-" . $file] = "[WARNING] " . $file . ": " . $xml->RRD->TXT;
				if (!$exit) $exit = 1;
			}
		}

		if ($time - $updated['RRDTOOL'] > 300) {
			$echo["1-!RRDTOOL"] = "[CRITICAL] RRDs have not been updated since " . date("Y-m-d H:i:s", $updated['RRDTOOL']);
			$exit = 2;
		} else {
			if ($time - $updated['HOST'] > 300) {
				$echo["1-!HOST"] = "[CRITICAL] Host RRDs have not been updated since " . date("Y-m-d H:i:s", $updated['HOST']);
				$exit = 2;
			}
			if ($time - $updated['SERVICE'] > 300) {
				$echo["1-!SERVICE"] = "[CRITICAL] Service RRDs have not been updated since " . date("Y-m-d H:i:s", $updated['SERVICE']);
				$exit = 2;
			}
		}

		ksort($echo);
		$echo = preg_replace("/" . preg_quote($path, "/") . "/", "", "\n" . implode("\n", $echo) . "\n");

		if ($exit == 2) {
			echo "CRITICAL:" . $echo;
		} elseif ($exit == 1) {
			echo "WARNING:" . $echo;
		} else {
			echo "OK: " . count($files) . " XML files\n";
		}
		exit($exit);
	}
}
