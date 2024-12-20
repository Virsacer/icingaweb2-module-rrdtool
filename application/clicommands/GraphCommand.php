<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Rrdtool\Rrdtool;

class GraphCommand extends Command {

	protected $DS, $MACRO;

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
	 *   --size	Size: image, thumb, large, huge, ultra,
	 *   		[Width]x[Height]  (Size of graph itself)
	 *   		[Width]X[Height]  (Graph without legend)
	 *   		[Width]*[Height]  (Size of whole image)
	 *   --dark	Use dark theme
	 *   --format	PNG|SVG|EPS|PDF
	 */
	public function defaultAction() {
		$file = $this->params->shiftRequired("file");
		$host = $this->params->shiftRequired("host");
		$range = $this->params->shiftRequired("range");
		$service = $this->params->shift("service", "_HOST_");
		$datasource = $this->params->shift("datasource", "");
		$size = $this->params->shift("size", "image");
		$dark = $this->params->shift("dark", "");
		$format = $this->params->shift("format", "");
		if ($this->hasRemainingParams()) return $this->showUsage("default");

		$config = $this->Config();
		if ($host == ".pnp-internal") $service = "runtime";
		$xml = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . Rrdtool::cleanup($host) . "/" . Rrdtool::cleanup($service) . ".xml";
		if (!file_exists($xml)) $this->fail("XML missing");

		if ($range == "datasources") {
			require(SYSPATH . "/library/Rrdtool/apply_template.php");
			foreach ($ds_name as $datasource) echo $datasource . "\n";
			exit();
		}

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
				$params = "--width=1496 --height=1024 --full-size-mode ";
				break;
			case "ultra":
				$params = "--width=3440 --height=1440 --full-size-mode ";
				break;
			default:
				if (preg_match("/^([0-9]+)([xX\*])([0-9]+)$/", $size, $matches)) {
					$params = "--width=" . $matches[1] . " --height=" . $matches[3] . " ";
					if ($matches[2] == "X") $params .= "--only-graph ";
					if ($matches[2] == "*") $params .= "--full-size-mode ";
				} else $params = "--width=500 --height=100 ";
		}
		$range = Rrdtool::parseRange($range);
		$params .= "--start=" . $range['start'] . " --end=" . $range['end'] . " ";
		if (preg_match("/^(PNG|SVG|EPS|PDF)$/i", $format)) $params .= "--imgformat=" . strtoupper($format) . " ";

		require(SYSPATH . "/library/Rrdtool/apply_template.php");

		if ($datasource == "") {
			$datasource = array_key_first($opt);
		} elseif (!array_key_exists($datasource, $opt)) {
			$datasource = array_search($datasource, $ds_name);
			if ($datasource === FALSE) $this->fail("No such datasource");
		}
		if (!preg_match_all("/(-v |--vertical-label)/i", $opt[$datasource], $match)) $params .= "--vertical-label=\" \" ";
		if ($dark) $params .= \rrd::darkteint();

		$rrdcached = $config->get("rrdtool", "rrdcached", "");
		if ($rrdcached) $params .= "--daemon=" . $rrdcached . " ";

		if (extension_loaded("rrd")) {
			$params = preg_replace("/(.+ |=)'([^']*)'/", "$1\"$2\"", $params . rtrim($opt[$datasource]) . " " . $def[$datasource]);
			$params = preg_split('/\s(?=([^"]*"[^"]*")*[^"]*$)/', $params, -1, PREG_SPLIT_NO_EMPTY);
			foreach ($params as $key => $val) {
				if (preg_match("/(.*)\"(.*)\"(.*)/", $val, $match)) {
					if (strpos($match[1], ":") !== FALSE) $match[2] = addcslashes($match[2], ":");
					$params[$key] = $match[1] . str_replace("\\\\", "\\", $match[2]) . ($match[3] ?? "");
				}
			}
			$return = rrd_graph($file, $params);
			$rrd = rrd_error();
			echo ($return && !$rrd) ? $return['xsize'] . "x" . $return['ysize'] . "\n" : $rrd . "\n";
		} else {
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " graph \"" . $file . "\" " . $params . rtrim($opt[$datasource]) . " " . addcslashes($def[$datasource], ":"), $return);
		}
	}
}
