<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;

class CheckCommand extends Command {

	/**
	 * Check for error messages in XML files
	 * USAGE
	 *
	 *   icingacli rrdtool check
	 */
	public function defaultAction() {
		$out = "";
		$status = 0;
		$config = $this->Config();
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
			if ($xml->RRD->RC == 0) continue;

			if ($status != 2) {
				$messages = explode(", ", $xml->RRD->TXT);
				$messages = preg_grep("/(conversion of .* to float|Malformed perfdata|minimum one second step)/", $messages, PREG_GREP_INVERT);
				$status = count($messages) ? 2 : 1;
			}
			$out .= $file . ": " . $xml->RRD->TXT . "\n";
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
