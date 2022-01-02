<?php

require_once(SYSPATH . "/library/vendor/pnp4nagios/rrd.php");

class rrd extends rrd_Core {

	public static function hrule($value = FALSE, $color = FALSE, $text = FALSE) {
		$output = parent::hrule($value, $color, $text);
		if (version_compare(PHP_VERSION, "8.0.0", "<")) {
			$output = preg_replace("/^([^,\"]+),/", "$1.", $output);
		}
		return $output;
	}

}
