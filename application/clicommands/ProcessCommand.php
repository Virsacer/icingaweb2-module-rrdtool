<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;

class ProcessCommand extends Command {

	private $stats = array("rows" => 0, "errors" => 0, "invalid" => 0, "skipped" => 0, "update" => 0, "create" => 0);

	/**
	 * Process PerfdataWriter output to generate rrd-files
	 * USAGE
	 *
	 *   icingacli rrdtool process
	 */
	public function defaultAction() {
		$config = $this->Config();
		if (!$config->get("rrdtool", "process", FALSE)) exit("Process not enabled in the config...");
		$path = rtrim($config->get("rrdtool", "perfdata", "/var/spool/icinga2/perfdata"), "/") . "/";
		$files = scandir($path);
		usort($files, function ($a, $b) {
			return str_replace(array("host", "service"), "", $a) <=> str_replace(array("host", "service"), "", $b);
		});

		$runtime = hrtime(TRUE);
		foreach ($files as $file) {
			if ($file == "." || $file == "..") continue;
			foreach (file($path . $file, FILE_IGNORE_NEW_LINES) as $data) {
				$this->stats['rows']++;
				$this->process($data);
			}
			unlink($path . $file);
			if (hrtime(TRUE) - $runtime >= 55000000000) break;
		}
		$runtime = (hrtime(TRUE) - $runtime) / 1000000000;

		$this->process(array(
			"DATATYPE" => "RRDTOOLPERFDATA",
			"TIMET" => time(),
			"HOSTNAME" => ".pnp-internal",
			"SERVICEDESC" => "runtime",
			"RRDTOOLPERFDATA" => "runtime=" . number_format($runtime, 6, ".", "") . "s rows=" . $this->stats['rows'] . " errors=" . $this->stats['errors'] . " invalid=" . $this->stats['invalid'] . " skipped=" . $this->stats['skipped'] . " update=" . $this->stats['update'] . " create=" . $this->stats['create'],
			"SERVICECHECKCOMMAND" => "pnp-runtime",
		));
	}

	protected function cleanup($string) {
		return str_replace(array("&", " ", ":", "/", "\\"), "_", $string);
	}

	protected function process($data) {
		$error = "";
		if (!is_array($data)) {
			preg_match_all("/(\w*)::([^\t]*)/", $data, $data);
			$data = array_combine($data[1], $data[2]);
		}

		$perfdata = $data['PERFDATA'] = str_replace(",", ".", trim($data[$data['DATATYPE']]));
		if (!$perfdata) {
			$this->stats['skipped']++;
			return; //No perfdata
		}

		$data['DATASOURCES'] = array();
		while (preg_match("/^([^=]+)=([\d\.\-]+|U|)([\w\/%]*)(;@?([\d\.\-~:]*))?(;@?([\d\.\-~:]*))?(;([\d\.\-]*))?(;([\d\.\-]*))?\s*(.*?)$/", $perfdata, $datasource)) {
			$perfdata = array_pop($datasource);
			$data['DATASOURCES'][] = $datasource;
		}
		if ($perfdata) {
			$this->stats['invalid']++;
			$error = "Malformed perfdata";
		}

		$config = $this->Config();
		$path = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . $this->cleanup($data['HOSTNAME']);
		if (!is_dir($path)) mkdir($path, 0777, TRUE);

		if ($data['DATATYPE'] == "HOSTPERFDATA") {
			$data['CHECKCOMMAND'] = $data['HOSTCHECKCOMMAND'];
			$data['SERVICEDESC'] = "Host Perfdata";
			$data['RRD'] = "_HOST_";
		} else {
			$data['CHECKCOMMAND'] = $data['SERVICECHECKCOMMAND'];
			$data['RRD'] = $this->cleanup($data['SERVICEDESC']);
		}

		$multiple = json_decode("[" . $config->get("rrdtool", "multiple", "") . "]", TRUE);
		if (!is_array($multiple)) exit("Config for multiple-mode is invalid...");
		if (in_array($data['CHECKCOMMAND'], $multiple) || $data['HOSTNAME'] == ".pnp-internal") {
			$data['RRD_STORAGE_TYPE'] = "MULTIPLE";
		} else {
			$data['RRD_STORAGE_TYPE'] = "SINGLE";
			$data['NAGIOS_RRDFILE'] = $path . "/" . $data['RRD'] . ".rrd";
		}

		$xml = new \XMLWriter();
		$xml->openUri($path . "/" . $data['RRD'] . ".xml");
		$xml->setIndent(TRUE);
		$xml->setIndentString("\t");
		$xml->startDocument("1.0", "UTF-8", "yes");
		$xml->startElement("NAGIOS");

		$ds = 1;
		foreach ($data['DATASOURCES'] as &$datasource) {
			$xml->startElement("DATASOURCE");

			$datasource['LABEL'] = str_replace(array("&", "\"", "'"), "", $datasource[1]);
			$datasource['NAME'] = $this->cleanup(str_replace(array("\"", "'"), "", $datasource[1]));
			$datasource['ACT'] = $datasource[2] == "" ? "U" : $datasource[2];
			$datasource['UNIT'] = $datasource[3] == "%" ? "%%" : $datasource[3];
			$datasource['WARN'] = $datasource[5];
			$datasource['CRIT'] = $datasource[7];
			$datasource['MIN'] = $datasource[9];
			$datasource['MAX'] = $datasource[11];
			$datasource['RRDFILE'] = $path . "/" . $data['RRD'] . ($data['RRD_STORAGE_TYPE'] == "MULTIPLE" ? "_" . $datasource['NAME'] : "") . ".rrd";

			//https://nagios-plugins.org/doc/guidelines.html#THRESHOLDFORMAT
			if (preg_match("/^~?([\d\.\-]+)?:([\d\.\-]+)?$/", $datasource['WARN'], $range)) {
				$datasource['WARN'] = "";
				$datasource['WARN_MIN'] = $range[1] ?? "";
				$datasource['WARN_MAX'] = $range[2] ?? "";
				$datasource['WARN_RANGE_TYPE'] = $datasource[4][1] == "@" ? "inside" : "outside";
			} else $datasource['WARN_MIN'] = $datasource['WARN_MAX'] = $datasource['WARN_RANGE_TYPE'] = "";

			if (preg_match("/^~?([\d\.\-]+)?:([\d\.\-]+)?$/", $datasource['CRIT'], $range)) {
				$datasource['CRIT'] = "";
				$datasource['CRIT_MIN'] = $range[1] ?? "";
				$datasource['CRIT_MAX'] = $range[2] ?? "";
				$datasource['CRIT_RANGE_TYPE'] = $datasource[6][1] == "@" ? "inside" : "outside";
			} else $datasource['CRIT_MIN'] = $datasource['CRIT_MAX'] = $datasource['CRIT_RANGE_TYPE'] = "";

			$xml->writeElement("TEMPLATE", $data['CHECKCOMMAND']);
			$xml->writeElement("RRDFILE", $datasource['RRDFILE']);
			$xml->writeElement("RRD_STORAGE_TYPE", $data['RRD_STORAGE_TYPE']);
			$xml->writeElement("RRD_HEARTBEAT", "8640");
			$xml->writeElement("IS_MULTI", "0");
			$xml->writeElement("DS", $ds);
			$xml->writeElement("NAME", $datasource['NAME']);
			$xml->writeElement("LABEL", $datasource['LABEL']);
			$xml->writeElement("UNIT", $datasource['UNIT']);
			$xml->writeElement("ACT", $datasource['ACT']);
			$xml->writeElement("WARN", $datasource['WARN']);
			$xml->writeElement("WARN_MIN", $datasource['WARN_MIN']);
			$xml->writeElement("WARN_MAX", $datasource['WARN_MAX']);
			$xml->writeElement("WARN_RANGE_TYPE", $datasource['WARN_RANGE_TYPE']);
			$xml->writeElement("CRIT", $datasource['CRIT']);
			$xml->writeElement("CRIT_MIN", $datasource['CRIT_MIN']);
			$xml->writeElement("CRIT_MAX", $datasource['CRIT_MAX']);
			$xml->writeElement("CRIT_RANGE_TYPE", $datasource['CRIT_RANGE_TYPE']);
			$xml->writeElement("MIN", $datasource['MIN']);
			$xml->writeElement("MAX", $datasource['MAX']);
			$xml->endElement();
			if ($data['RRD_STORAGE_TYPE'] == "SINGLE") $ds++;
		}

		if (!$error) {
			$rrd = $this->rrd($data);
		} else $rrd = array(1, $error);

		$xml->startElement("RRD");
		$xml->writeElement("RC", $rrd[0]);
		$xml->writeElement("TXT", $rrd[1]);
		$xml->endElement();

		$xml->writeElement("NAGIOS_AUTH_HOSTNAME", $data['HOSTNAME']);
		$xml->writeElement("NAGIOS_AUTH_SERVICEDESC", $data['SERVICEDESC']);
		$xml->writeElement("NAGIOS_CHECK_COMMAND", $data['CHECKCOMMAND']);
		$xml->writeElement("NAGIOS_DATATYPE", str_replace("RRDTOOL", "SERVICE", $data['DATATYPE']));
		$xml->writeElement("NAGIOS_DISP_HOSTNAME", $data['HOSTNAME']);
		$xml->writeElement("NAGIOS_DISP_SERVICEDESC", $data['SERVICEDESC']);
		if ($data['DATATYPE'] == "HOSTPERFDATA") {
			$xml->writeElement("NAGIOS_HOSTCHECKCOMMAND", $data['CHECKCOMMAND']);
			$xml->writeElement("NAGIOS_HOSTNAME", $data['HOSTNAME']);
			$xml->writeElement("NAGIOS_HOSTPERFDATA", $data['HOSTPERFDATA']);
		} else $xml->writeElement("NAGIOS_HOSTNAME", $data['HOSTNAME']);
		if ($data['DATATYPE'] != "RRDTOOLPERFDATA") {
			$xml->writeElement("NAGIOS_HOSTSTATE", $data['HOSTSTATE']);
			$xml->writeElement("NAGIOS_HOSTSTATETYPE", $data['HOSTSTATETYPE']);
		}
		$xml->writeElement("NAGIOS_MULTI_PARENT", "");
		$xml->writeElement("NAGIOS_PERFDATA", $data['PERFDATA']);
		$xml->writeElement("NAGIOS_RRDFILE", $data['NAGIOS_RRDFILE'] ?? "");
		if ($data['DATATYPE'] == "SERVICEPERFDATA") {
			$xml->writeElement("NAGIOS_SERVICECHECKCOMMAND", $data['SERVICECHECKCOMMAND']);
			$xml->writeElement("NAGIOS_SERVICEDESC", $this->cleanup($data['SERVICEDESC']));
			$xml->writeElement("NAGIOS_SERVICEPERFDATA", $data['SERVICEPERFDATA']);
			$xml->writeElement("NAGIOS_SERVICESTATE", $data['SERVICESTATE']);
			$xml->writeElement("NAGIOS_SERVICESTATETYPE", $data['SERVICESTATETYPE']);
		} else $xml->writeElement("NAGIOS_SERVICEDESC", $data['RRD']);
		$xml->writeElement("NAGIOS_TIMET", $data['TIMET']);
		$xml->writeElement("NAGIOS_XMLFILE", $path . "/" . $data['RRD'] . ".xml");

		$xml->startElement("XML");
		$xml->writeElement("VERSION", 4);
		$xml->endElement();
		$xml->endDocument();
	}

	protected function rrd($data) {
		$ds = 1;
		$update = "";
		$create = array();
		$returnmulti = "";
		$config = $this->Config();
		$file = $data['NAGIOS_RRDFILE'] ?? "";
		foreach ($data['DATASOURCES'] as $key => $datasource) {
			$update .= ":" . $datasource['ACT'];
			$create[] = "DS:" . $ds++ . ":GAUGE:8640:U:U";
			if ($data['RRD_STORAGE_TYPE'] == "MULTIPLE" || $key === array_key_last($data['DATASOURCES'])) {
				if ($data['RRD_STORAGE_TYPE'] == "MULTIPLE") {
					$update = ":" . $datasource['ACT'];
					$create = array("DS:1:GAUGE:8640:U:U");
					$file = $datasource['RRDFILE'];
				}

				if (!file_exists($file)) {
					$create = array_merge(array(
						"--start=" . ($data['TIMET'] - 1),
						"--step=60",
						"RRA:AVERAGE:0.5:1:2880",
						"RRA:AVERAGE:0.5:5:2880",
						"RRA:AVERAGE:0.5:30:4320",
						"RRA:AVERAGE:0.5:360:5840",
						"RRA:MAX:0.5:1:2880",
						"RRA:MAX:0.5:5:2880",
						"RRA:MAX:0.5:30:4320",
						"RRA:MAX:0.5:360:5840",
						"RRA:MIN:0.5:1:2880",
						"RRA:MIN:0.5:5:2880",
						"RRA:MIN:0.5:30:4320",
						"RRA:MIN:0.5:360:5840",
						), $create
					);
					if (extension_loaded("rrd")) {
						$return = !rrd_create($file, $create);
					} else {
						ob_start();
						passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " create " . $file . " " . implode(" ", $create) . " 2>&1", $return);
						$rrd = trim(ob_get_clean());
					}
					if ($return) {
						$this->stats['errors']++;
						if ($data['RRD_STORAGE_TYPE'] != "MULTIPLE") return array(1, $rrd ?? rrd_error());
						$returnmulti .= rrd_error() . ", ";
					}
					$this->stats['create']++;
				}

				if (extension_loaded("rrd")) {
					$return = !rrd_update($file, array($data['TIMET'] . $update));
				} else {
					ob_start();
					passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " update " . $file . " " . $data['TIMET'] . $update . " 2>&1", $return);
					$rrd = trim(ob_get_clean());
				}
				if ($return) {
					$this->stats['errors']++;
					if ($data['RRD_STORAGE_TYPE'] != "MULTIPLE") return array(1, $rrd ?? rrd_error());
					$returnmulti .= rrd_error() . ", ";
				}
				$this->stats['update']++;
			}
		}
		if ($returnmulti != "") return array(1, rtrim($returnmulti, ", "));
		return array(0, "successful updated");
	}

}
