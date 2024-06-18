<?php

class rrd {

	public static function alerter($vname, $label, $warning, $critical, $opacity = "FF", $unit = "", $color_ok = "#00FF00", $color_warn = "#FFFF00", $color_crit = "#FF0000", $color_line = "#0000FF") {
		return rrd::alerter_gr($vname, $label, $warning, $critical, $opacity, $unit, $color_ok, $color_warn, $color_crit, $color_line, FALSE);
	}

	public static function alerter_gr($vname, $label, $warning, $critical, $opacity = "FF", $unit = "", $color_ok = "#00FF00", $color_warn = "#FFFF00", $color_crit = "#FF0000", $color_line = "#0000FF", $start_color = "#FFFFFF") {
		$ok_vname = "var" . substr(sha1(rand()), 1, 4);
		$comp_vname = "var" . substr(sha1(rand()), 1, 4);
		$warn_vname = "var" . substr(sha1(rand()), 1, 4);
		$crit_vname = "var" . substr(sha1(rand()), 1, 4);
		if ($warning < $critical) {
			$data = "CDEF:" . $ok_vname . "=" . $vname . "," . $warning . ",LT," . $vname . ",UNKN,IF ";
			$data .= "CDEF:" . $comp_vname . "=" . $vname . "," . $critical . ",LT," . $vname . ",UNKN,IF ";
			$data .= "CDEF:" . $warn_vname . "=" . $comp_vname . "," . $warning . ",GE," . $comp_vname . ",UNKN,IF ";
			$data .= "CDEF:" . $crit_vname . "=" . $vname . "," . $critical . ",GE," . $vname . ",UNKN,IF ";
		} else {
			$data = "CDEF:" . $ok_vname . "=" . $vname . "," . $warning . ",GT," . $vname . ",UNKN,IF ";
			$data .= "CDEF:" . $comp_vname . "=" . $vname . "," . $critical . ",GE," . $vname . ",UNKN,IF ";
			$data .= "CDEF:" . $warn_vname . "=" . $comp_vname . "," . $warning . ",LE," . $comp_vname . ",UNKN,IF ";
			$data .= "CDEF:" . $crit_vname . "=" . $vname . "," . $critical . ",LT," . $vname . ",UNKN,IF ";
		}
		if ($start_color !== FALSE) {
			$data .= rrd::gradient($ok_vname, $start_color, $color_ok . $opacity);
			$data .= rrd::gradient($warn_vname, $start_color, $color_warn . $opacity);
			$data .= rrd::gradient($crit_vname, $start_color, $color_crit . $opacity);
		} else {
			$data .= rrd::area($ok_vname, $color_ok . $opacity);
			$data .= rrd::area($warn_vname, $color_warn . $opacity);
			$data .= rrd::area($crit_vname, $color_crit . $opacity);
		}
		$data .= rrd::line1($vname, $color_line, $label);
		return $data;
	}

	public static function area($vname, $color, $text = FALSE, $stack = FALSE) {
		return "AREA:" . $vname . $color . ":\"" . $text . "\"" . ($stack ? ":STACK " : " ");
	}

	public static function cdef($vname, $rpn) {
		return "CDEF:" . $vname . "=" . $rpn . " ";
	}

	public static function colbright($color, $steps) {
		preg_match("/^#?(([0-9A-F])([0-9A-F])([0-9A-F])|([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2}))([0-9A-F]{2})?$/i", $color, $color);
		$color[1] = max(0, min(255, hexdec($color[2] . $color[2] . @$color[5]) + $steps));
		$color[2] = max(0, min(255, hexdec($color[3] . $color[3] . @$color[6]) + $steps));
		$color[3] = max(0, min(255, hexdec($color[4] . $color[4] . @$color[7]) + $steps));
		return sprintf("#%02X%02X%02X", $color[1], $color[2], $color[3]);
	}

	public static function color($num = 0, $alpha = "FF", $scheme = array()) {
		$num = intval($num);
		if (array_key_exists($num, $scheme)) return $scheme[$num] . $alpha;
		$colors = array();
		foreach (array("CC", "FF", "99", "66") as $ri) {
			for ($z = 1; $z < 8; $z++) {
				$color = "#";
				$color .= ($z & 4) >= 1 ? $ri : "00";
				$color .= ($z & 2) >= 1 ? $ri : "00";
				$color .= ($z & 1) >= 1 ? $ri : "00";
				$icolor = rrd::color_inverse($color);
				$colors[] = $color . $alpha;
				$colors[] = $icolor . $alpha;
			}
		}
		return array_key_exists($num, $colors) ? $colors[$num] : $colors[0];
	}

	public static function color_inverse($color) {
		if (preg_match("/^#?(([0-9A-F])([0-9A-F])([0-9A-F])|[0-9A-F]{6})$/i", $color, $color)) {
			if (strlen($color[1]) != 6) $color[1] = $color[2] . $color[2] . $color[3] . $color[3] . $color[4] . $color[4];
			return "#" . str_pad(dechex(16777215 - hexdec($color[1])), 6, "0", STR_PAD_LEFT);
		}
		return "#000000";
	}

	public static function comment($text) {
		return "COMMENT:\"" . $text . "\" ";
	}

	public static function cut($string, $length = 18, $align = "left") {
		if (strlen($string) > $length) return substr($string, 0, $length - 1) . "…";
		return str_pad($string, $length, " ", $align == "left" ? STR_PAD_RIGHT : STR_PAD_LEFT);
	}

	public static function darkteint() {
		return "--color=ARROW#FFFFFF --color=AXIS#FFFFFF --color=BACK#444444 --color=CANVAS#000000 --color=FONT#FFFFFF --color=FRAME#888888 --color=MGRID#888888 ";
	}

	public static function debug($data) {
		if ($data !== FALSE) {
			ob_start();
			var_dump($data);
			$data = preg_replace("/(AREA|CDEF|COMMENT|GPRINT|HRULE|LINE|VDEF)/", "\n$1", ob_get_clean());
			echo "<pre>" . print_r($data, TRUE) . "</pre>";
		}
	}

	public static function def($vname, $rrdfile, $ds, $cf = "AVERAGE") {
		return "DEF:" . $vname . "=\"" . $rrdfile . "\":" . $ds . ":" . $cf . " ";
	}

	public static function gprint($vname, $cf = "AVERAGE", $text = "%6.2lf %s") {
		if (is_array($cf)) return rrd::gprinta($vname, $cf, $text, "l");
		return "GPRINT:" . $vname . ":" . $cf . ":\"" . $text . "\" ";
	}

	public static function gprinta($vname, $cf = "AVERAGE", $text = "%6.2lf %s", $align = "") {
		if (is_array($cf)) {
			$data = "";
			foreach ($cf as $key => $val) {
				$data .= "GPRINT:" . $vname . ":" . $val . ":\"" . $text . " " . ucfirst(strtolower($val));
				if ($key == array_key_last($cf) && $align != "") $data .= "\\" . $align;
				$data .= "\" ";
			}
			return $data;
		}
		return "GPRINT:" . $vname . ":" . $cf . ":\"" . $text . "\" ";
	}

	public static function gradient($vname, $start_color = "#0000A0", $end_color = "#F0F0F0", $label = FALSE, $steps = 20, $lower = FALSE) {
		preg_match("/^#?(([0-9A-F])([0-9A-F])([0-9A-F])|([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2}))([0-9A-F]{2})?$/i", $start_color, $start_color);
		$start_color[1] = hexdec($start_color[2] . $start_color[2] . @$start_color[5]);
		$start_color[2] = hexdec($start_color[3] . $start_color[3] . @$start_color[6]);
		$start_color[3] = hexdec($start_color[4] . $start_color[4] . @$start_color[7]);
		preg_match("/^#?(([0-9A-F])([0-9A-F])([0-9A-F])|([0-9A-F]{2})([0-9A-F]{2})([0-9A-F]{2}))([0-9A-F]{2})?$/i", $end_color, $diff_color);
		$diff_color[1] = hexdec($diff_color[2] . $diff_color[2] . @$diff_color[5]) - $start_color[1];
		$diff_color[2] = hexdec($diff_color[3] . $diff_color[3] . @$diff_color[6]) - $start_color[2];
		$diff_color[3] = hexdec($diff_color[4] . $diff_color[4] . @$diff_color[7]) - $start_color[3];
		if (preg_match("/^([0-9]{1,2}|100)%$/", $lower, $matches)) {
			$data = sprintf("CDEF:%sminimum=%s,100,/,%d,* ", $vname, $vname, $matches[1]);
		} elseif (preg_match("/^([0-9]+)$/", $lower, $matches)) {
			$data = sprintf("CDEF:%sminimum=%s,%d,- ", $vname, $vname, $matches[1]);
		} else {
			$data = sprintf("CDEF:%sminimum=%s,%s,- ", $vname, $vname, $vname);
		}
		$gradient_vname = "var" . substr(sha1(rand()), 1, 4);
		for ($i = $steps; $i > 0; $i--) {
			$data .= sprintf("CDEF:%s%d=%s,%sminimum,-,%d,/,%d,*,%sminimum,+ ", $gradient_vname, $i, $vname, $vname, $steps, $i, $vname);
		}
		for ($i = $steps; $i > 0; $i--) {
			$factor = $i / $steps;
			$r = round($start_color[1] + $diff_color[1] * $factor);
			$g = round($start_color[2] + $diff_color[2] * $factor);
			$b = round($start_color[3] + $diff_color[3] * $factor);
			if ($i == $steps && $label) {
				$data .= sprintf("AREA:%s%d#%02X%02X%02X:\"%s\" ", $gradient_vname, $i, $r, $g, $b, $label);
			} else {
				$data .= sprintf("AREA:%s%d#%02X%02X%02X ", $gradient_vname, $i, $r, $g, $b);
			}
		}
		return $data;
	}

	public static function hrule($value, $color, $text = FALSE) {
		if ($value == "~") return "";
		return "HRULE:" . $value . $color . ":\"" . $text . "\" ";
	}

	public static function line($type, $vname, $color, $text = FALSE, $stack = FALSE) {
		return "LINE" . $type . ":" . $vname . $color . ":\"" . $text . "\"" . ($stack ? ":STACK " : " ");
	}

	public static function line1($vname, $color, $text = FALSE, $stack = FALSE) {
		return rrd::line(1, $vname, $color, $text, $stack);
	}

	public static function line2($vname, $color, $text = FALSE, $stack = FALSE) {
		return rrd::line(2, $vname, $color, $text, $stack);
	}

	public static function line3($vname, $color, $text = FALSE, $stack = FALSE) {
		return rrd::line(3, $vname, $color, $text, $stack);
	}

	public static function tick($vname, $color, $fraction = FALSE, $label = FALSE) {
		return "TICK:" . $vname . $color . ($fraction ? ":" . $fraction : "") . ($label ? ":" . $label : "") . " ";
	}

	public static function ticker($vname, $warning, $critical, $fraction = -0.05, $opacity = "FF", $color_ok = "#00FF00", $color_warn = "#FFFF00", $color_crit = "#FF0000") {
		$ok_vname = "var" . substr(sha1(rand()), 1, 4);
		$comp_vname = "var" . substr(sha1(rand()), 1, 4);
		$warn_vname = "var" . substr(sha1(rand()), 1, 4);
		$crit_vname = "var" . substr(sha1(rand()), 1, 4);
		$temp_vname = "var" . substr(sha1(rand()), 1, 4);
		$data = "CDEF:" . $temp_vname . "=" . $vname . "," . $warning . ",LT," . $vname . ",UNKN,IF ";
		$data .= "CDEF:" . $comp_vname . "=" . $vname . "," . $critical . ",LT," . $vname . ",UNKN,IF ";
		$data .= "CDEF:" . $warn_vname . "=" . $comp_vname . "," . $warning . ",GE," . $comp_vname . ",UNKN,IF ";
		$data .= "CDEF:" . $crit_vname . "=" . $vname . "," . $critical . ",GE," . $vname . ",UNKN,IF ";
		$data .= "CDEF:" . $ok_vname . "=" . $temp_vname . ",0,EQ,0.000001," . $temp_vname . ",IF ";
		$data .= rrd::tick($ok_vname, $color_ok . $opacity, $fraction);
		$data .= rrd::tick($warn_vname, $color_warn . $opacity, $fraction);
		$data .= rrd::tick($crit_vname, $color_crit . $opacity, $fraction);
		return $data;
	}

	public static function vdef($vname, $rpn) {
		return "VDEF:" . $vname . "=" . $rpn . " ";
	}

}
