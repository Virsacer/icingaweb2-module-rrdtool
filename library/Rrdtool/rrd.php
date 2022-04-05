<?php

require_once(SYSPATH . "/library/vendor/pnp4nagios/rrd.php");

class rrd extends rrd_Core {

	public static function darkteint() {
		return "--color=ARROW#FFFFFF --color=AXIS#FFFFFF --color=BACK#444444 --color=CANVAS#000000 --color=FONT#FFFFFF --color=FRAME#888888 --color=MGRID#888888 ";
	}

}
