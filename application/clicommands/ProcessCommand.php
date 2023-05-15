<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Rrdtool\Rrdtool;

class ProcessCommand extends Command {

	private $logs = FALSE;
	private $verbose = FALSE;
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
		if ($config->get("rrdtool", "logging", FALSE)) $this->logs = array();
		if ($config->get("rrdtool", "verbose", FALSE)) $this->verbose = TRUE;
		$path = rtrim($config->get("rrdtool", "perfdata", "/var/spool/icinga2/perfdata"), "/") . "/";
		$files = scandir($path);
		usort($files, function ($a, $b) {
			return str_replace(array("host", "service"), "", $a) <=> str_replace(array("host", "service"), "", $b);
		});

		$this->log2("Start processing");
		$runtime = hrtime(TRUE);
		foreach ($files as $file) {
			if ($file == "." || $file == "..") continue;
			$data = file($path . $file, FILE_IGNORE_NEW_LINES);
			foreach ($data as $item) {
				$this->stats['rows']++;
				$this->process($item);
			}
			unlink($path . $file);
			if (hrtime(TRUE) - $runtime >= 55000000000) {
				$this->log2("Stopping due to timelimit");
				break;
			}
		}
		$runtime = (hrtime(TRUE) - $runtime) / 1000000000;

		$stats = "runtime=" . number_format($runtime, 6, ".", "") . "s rows=" . $this->stats['rows'] . " errors=" . $this->stats['errors'] . " invalid=" . $this->stats['invalid'] . " skipped=" . $this->stats['skipped'] . " update=" . $this->stats['update'] . " create=" . $this->stats['create'];
		$this->process(array("DATATYPE" => "RRDTOOLPERFDATA", "TIMET" => time(), "HOSTNAME" => ".pnp-internal", "SERVICEDESC" => "runtime", "RRDTOOLPERFDATA" => $stats, "SERVICECHECKCOMMAND" => "pnp-runtime"));
		$this->log2("End processing (" . $stats . ")");

		if ($this->logs !== FALSE && count($this->logs)) {
			file_put_contents(rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/rrdtool.log", $this->logs, FILE_APPEND);
		}
	}

	protected function log($message, $data = NULL) {
		if ($this->logs === FALSE) return;
		$log = date("Y-m-d H:i:s") . "\t";
		if (is_array($data)) {
			$log .= "Timestamp: " . date("Y-m-d H:i:s", $data['TIMET']) . "\t";
			$log .= "Service:" . ($data['SERVICEDESC'] ?? "Host Perfdata") . "(" . ($data['SERVICESTATE'] ?? "?") . ")\t";
			$log .= "Host:" . $data['HOSTNAME'] . "(" . ($data['HOSTSTATE'] ?? "?") . ")\t";
		}
		$this->logs[] = $log . $message . "\n";
	}

	protected function log2($message, $data = NULL) {
		if ($this->verbose === FALSE) return;
		$this->log($message, $data);
	}

	protected function process($data) {
		$error = "";
		if (!is_array($data)) {
			preg_match_all("/(\w*)::([^\t]*)/", $data, $data);
			$data = array_combine($data[1], $data[2]);
		}

		if (!isset($data['HOSTCHECKCOMMAND']) && !isset($data['SERVICECHECKCOMMAND'])) {
			$this->stats['errors']++;
			$this->log("Error: Perfdata incomplete", $data);
			return;
		}

		$perfdata = $data['PERFDATA'] = str_replace(",", ".", trim($data[$data['DATATYPE']]));
		if (!$perfdata) {
			$this->stats['skipped']++;
			$this->log("Skipped: No perfdata", $data);
			return;
		}

		$data['DATASOURCES'] = array();
		while (preg_match("/^([^=]+)=([\d\.\-]+|U|)([\w\/%]*)(;@?([\d\.\-~:]*))?(;@?([\d\.\-~:]*))?(;([\d\.\-]*))?(;([\d\.\-]*))?\s*(.*?)$/", $perfdata, $datasource)) {
			unset($datasource[0]);
			$perfdata = array_pop($datasource);
			$data['DATASOURCES'][] = $datasource;
		}
		$data['DATASOURCES'] = array_intersect_key($data['DATASOURCES'], array_unique(array_map("json_encode", $data['DATASOURCES'])));
		if ($perfdata) {
			$this->stats['invalid']++;
			$this->log("Invalid: " . $data['PERFDATA'], $data);
			$error = "Malformed perfdata";
		}

		$config = $this->Config();
		$path = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/" . Rrdtool::cleanup($data['HOSTNAME']);
		if (!is_dir($path)) mkdir($path, 0777, TRUE);

		if ($data['DATATYPE'] == "HOSTPERFDATA") {
			$data['CHECKCOMMAND'] = $data['HOSTCHECKCOMMAND'];
			$data['SERVICEDESC'] = "Host Perfdata";
			$data['RRD'] = "_HOST_";
		} else {
			$data['CHECKCOMMAND'] = $data['SERVICECHECKCOMMAND'];
			$data['RRD'] = Rrdtool::cleanup($data['SERVICEDESC']);
		}

		$multiple = json_decode("[" . $config->get("rrdtool", "multiple", "") . "]", TRUE);
		if (!is_array($multiple)) exit("Config 'Checks with multiple RRDs' is invalid...");
		$data['RRD_STORAGE_TYPE'] = "SINGLE";
		if ($data['HOSTNAME'] == ".pnp-internal") {
			$data['RRD_STORAGE_TYPE'] = "MULTIPLE";
		} elseif (file_exists($path . "/" . $data['RRD'] . ".xml")) {
			$xml = file_get_contents($path . "/" . $data['RRD'] . ".xml");
			if (strpos($xml, "<RRD_STORAGE_TYPE>MULTIPLE") !== FALSE) $data['RRD_STORAGE_TYPE'] = "MULTIPLE";
		} elseif (in_array($data['CHECKCOMMAND'], $multiple)) {
			$data['RRD_STORAGE_TYPE'] = "MULTIPLE";
		}
		if ($data['RRD_STORAGE_TYPE'] == "SINGLE") $data['NAGIOS_RRDFILE'] = $path . "/" . $data['RRD'] . ".rrd";

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
			$datasource['NAME'] = Rrdtool::cleanup(str_replace(array("\"", "'"), "", $datasource[1]));
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
			$xml->writeElement("NAGIOS_HOSTSTATE", $data['HOSTSTATE'] ?? "?");
			$xml->writeElement("NAGIOS_HOSTSTATETYPE", $data['HOSTSTATETYPE'] ?? "?");
		}
		$xml->writeElement("NAGIOS_MULTI_PARENT", "");
		$xml->writeElement("NAGIOS_PERFDATA", $data['PERFDATA']);
		$xml->writeElement("NAGIOS_RRDFILE", $data['NAGIOS_RRDFILE'] ?? "");
		if ($data['DATATYPE'] == "SERVICEPERFDATA") {
			$xml->writeElement("NAGIOS_SERVICECHECKCOMMAND", $data['SERVICECHECKCOMMAND']);
			$xml->writeElement("NAGIOS_SERVICEDESC", Rrdtool::cleanup($data['SERVICEDESC']));
			$xml->writeElement("NAGIOS_SERVICEPERFDATA", $data['SERVICEPERFDATA']);
			$xml->writeElement("NAGIOS_SERVICESTATE", $data['SERVICESTATE'] ?? "?");
			$xml->writeElement("NAGIOS_SERVICESTATETYPE", $data['SERVICESTATETYPE'] ?? "?");
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
						$rrd = rrd_error();
					} else {
						ob_start();
						passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " create " . $file . " " . implode(" ", $create) . " 2>&1", $return);
						$rrd = trim(ob_get_clean());
					}
					if ($return) {
						$this->stats['errors']++;
						$this->log("Error: " . $rrd, $data);
						if ($data['RRD_STORAGE_TYPE'] != "MULTIPLE") return array(1, $rrd);
						$returnmulti .= $rrd . ", ";
					}
					$this->stats['create']++;
				}

				if (extension_loaded("rrd")) {
					$return = !rrd_update($file, array($data['TIMET'] . $update));
					$rrd = rrd_error();
				} else {
					ob_start();
					passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " update " . $file . " " . $data['TIMET'] . $update . " 2>&1", $return);
					$rrd = trim(ob_get_clean());
				}
				if ($return) {
					$this->stats['errors']++;
					$this->log("Error: " . $rrd, $data);
					if ($data['RRD_STORAGE_TYPE'] != "MULTIPLE") return array(1, $rrd);
					$returnmulti .= $rrd . ", ";
				}
				$this->stats['update']++;
			}
		}
		if ($returnmulti != "") return array(1, rtrim($returnmulti, ", "));
		return array(0, "successful updated");
	}

}
