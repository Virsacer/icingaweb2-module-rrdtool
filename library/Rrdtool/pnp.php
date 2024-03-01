<?php

class pnp {

	public static function adjust_unit($value, $base = 1000, $format = "%.3lF", $target_unit = NULL) {
		$format = str_replace("f", "F", $format);
		preg_match("/^-?([0-9\.]+)\s*(\D?)(\D?)/", $value, $matches);
		if ($matches[2] == "%") {
			if ($matches[0][0] == "-") $matches[1] *= -1;
			$value = sprintf($format, $matches[1]);
			return array($value . " %", trim($value), "%", 1);
		}
		$symbols = array(-3 => "n", -2 => "u", -1 => "m", 0 => "", "K", "M", "G", "T", "P", "E", "Z", "Y");
		if ($matches[2] == "B" || $matches[2] == "b" || $matches[2] == "s") {
			$matches[3] = $matches[2];
			$matches[2] = "";
		}
		$value = $matches[1] * $base ** array_search($matches[2], $symbols);
		$exponent = intval(floor(log($value) / log($base)));
		if ($target_unit != NULL) $exponent = array_search($target_unit[0], $symbols);
		$value /= $base ** $exponent;
		$divisor = $value ? $matches[1] / $value : 1;
		if ($matches[0][0] == "-") $value *= -1;
		$value = sprintf($format, $value);
		return array($value . " " . $symbols[$exponent] . $matches[3], trim($value), $symbols[$exponent] . $matches[3], $divisor);
	}

}
