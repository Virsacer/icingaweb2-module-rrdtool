<?php

class pnp {

	public static function adjust_unit($value, $base = 1000, $format = "%.3lf") {
		preg_match("/^-?([0-9\.]+)\s*(\D?)(\D?)/", $value, $matches);
		if ($matches[2] == "%") {
			$value = sprintf($format, $matches[1]);
			if ($matches[0][0] == "-") $value = "-" . $value;
			return array($value . " %", $value, "%", 1);
		}
		$symbols = array(-3 => "n", -2 => "u", -1 => "m", 0 => "", "K", "M", "G", "T", "P", "E", "Z", "Y");
		if ($matches[2] == "B" || $matches[2] == "b" || $matches[2] == "s") {
			$matches[3] = $matches[2];
			$matches[2] = "";
		}
		$value = $matches[1] * $base ** array_search($matches[2], $symbols);
		$exponent = floor(log($value) / log($base));
		$value /= $base ** $exponent;
		$divisor = $matches[1] / $value;
		if ($matches[0][0] == "-") $value *= -1;
		$value = sprintf($format, $value);
		return array($value . " " . $symbols[$exponent] . $matches[3], trim($value), $symbols[$exponent] . $matches[3], $divisor);
	}

}
