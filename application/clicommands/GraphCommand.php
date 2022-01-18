<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Rrdtool\Controllers\GraphController;

class GraphCommand extends Command {

	/**
	 * Generate a graph
	 * USAGE
	 *
	 *   icingacli rrdtool graph [options]
	 *
	 * REQUIRED
	 *
	 *   --file	Output
	 *   --host	Hostname
	 *   --range	Range: year, month, week, hours, hour,
	 * 			[Timestamp]-[Timestamp], [YYYY]-[MM], [YYYY]-Q[1-4]
	 *
	 * OPTIONAL
	 *
	 *   --service	Name of the service
	 *   --datasource	Name or number of the datasource
	 *   --size	Size: image, thumb, large, [Width]x[Height], [Width]X[Height]
	 */
	public function defaultAction() {
		$file = $this->params->shiftRequired("file");
		$host = $this->params->shiftRequired("host");
		$range = $this->params->shiftRequired("range");
		$service = $this->params->shift("service", "_HOST_");
		$datasource = $this->params->shift("datasource", "");
		$size = $this->params->shift("size", "image");
		if ($this->hasRemainingParams()) return $this->showUsage("default");

		$config = $this->Config();
		if ($host == ".pnp-internal") $service = "runtime";
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
		if (!file_exists($xml)) $this->fail("XML missing");

		switch ($size) {
			case "image":
				$params = "--width 500 --height 100 ";
				break;
			case "thumb":
				$params = "--only-graph --width 96 --height 32 ";
				break;
			case "large":
				$params = "--width 1000 --height 200 ";
				break;
			default:
				$params = "--width 500 --height 100 ";
				if (preg_match("/^([0-9]+)([xX])([0-9]+)$/", $size, $matches)) {
					$params = $matches[2] == "X" ? "--only-graph " : "";
					$params .= "--width " . $matches[1] . " --height " . $matches[3] . " ";
				}
		}
		$range = GraphController::parseRange($range);
		$params .= "--start " . $range['start'] . " --end " . $range['end'] . " ";

		require(SYSPATH . "/library/Rrdtool/apply_template.php");

		if (!intval($datasource)) $datasource = array_search($datasource, $ds_name);
		if ($datasource === FALSE || !array_key_exists($datasource, $opt)) $datasource = array_key_first($def);
		if (!preg_match_all("/(-v |--vertical-label)/i", $opt[$datasource], $match)) $params .= "--vertical-label=' ' ";
		passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " graph " . $file . " " . $params . rtrim($opt[$datasource]) . " " . $def[$datasource], $return);
	}

}
