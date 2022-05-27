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
	 *   		[Timestamp]-[Timestamp], [YYYY]-[MM], [YYYY]-Q[1-4], [YYYY]
	 *
	 * OPTIONAL
	 *
	 *   --service	Name of the service
	 *   --datasource	Name or number of the datasource
	 *   --size	Size: image, thumb, large, huge,
	 *   		[Width]x[Height]  (Size of graph itself)
	 *   		[Width]X[Height]  (Graph without legend)
	 *   		[Width]*[Height]  (Size of whole image)
	 *   --dark	Use dark theme
	 */
	public function defaultAction() {
		$file = $this->params->shiftRequired("file");
		$host = $this->params->shiftRequired("host");
		$range = $this->params->shiftRequired("range");
		$service = $this->params->shift("service", "_HOST_");
		$datasource = $this->params->shift("datasource", "");
		$size = $this->params->shift("size", "image");
		$dark = $this->params->shift("dark", "");
		if ($this->hasRemainingParams()) return $this->showUsage("default");

		$config = $this->Config();
		if ($host == ".pnp-internal") $service = "runtime";
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/pnp4nagios"), "/") . "/" . $host . "/" . str_replace(array("/", " "), "_", $service) . ".xml";
		if (!file_exists($xml)) $this->fail("XML missing");

		switch ($size) {
			case "image":
				$params = "--width=500 --height=100 ";
				break;
			case "thumb":
				$params = "--width=96 --height=32 --only-graph ";
				break;
			case "large":
				$params = "--width=1000 --height=200 ";
				break;
			case "huge":
				$params = "--width=1600 --height=900 --full-size-mode ";
				break;
			default:
				if (preg_match("/^([0-9]+)([xX\*])([0-9]+)$/", $size, $matches)) {
					$params = "--width=" . $matches[1] . " --height=" . $matches[3] . " ";
					if ($matches[2] == "X") $params .= "--only-graph ";
					if ($matches[2] == "*") $params .= "--full-size-mode ";
				} else $params = "--width=500 --height=100 ";
		}
		$range = GraphController::parseRange($range);
		$params .= "--start=" . $range['start'] . " --end=" . $range['end'] . " ";

		require(SYSPATH . "/library/Rrdtool/apply_template.php");

		if ($datasource == "") {
			$datasource = array_key_first($opt);
		} elseif (!array_key_exists($datasource, $opt)) {
			$datasource = array_search($datasource, $ds_name);
			if ($datasource === FALSE) $this->fail("No such datasource");
		}
		if (!preg_match_all("/(-v |--vertical-label)/i", $opt[$datasource], $match)) $params .= "--vertical-label=\" \" ";
		if ($dark) $params .= \rrd::darkteint();

		$params .= rtrim($opt[$datasource]) . " " . $def[$datasource];
		if (extension_loaded("rrd")) {
			$params = preg_replace("/( |=)'([^']*)'/", "$1\"$2\"", str_replace("\:", ":", $params));
			$return = rrd_graph($file, str_replace("\\\\", "\\", preg_replace("/\"/", "", preg_split('/\s(?=([^"]*"[^"]*")*[^"]*$)/', $params, NULL, PREG_SPLIT_NO_EMPTY))));
			echo $return ? $return['xsize'] . "x" . $return['ysize'] . "\n" : rrd_error() . "\n";
		} else {
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " graph " . $file . " " . $params, $return);
		}
	}

}
