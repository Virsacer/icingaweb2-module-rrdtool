<?php

namespace Icinga\Module\Rrdtool\Clicommands;

use Icinga\Cli\Command;

class JoinCommand extends Command {

	/**
	 * Join RRD files
	 * USAGE
	 *
	 *   icingacli rrdtool join Host/Service.xml
	 */
	public function defaultAction() {
		$config = $this->Config();
		$params = $this->params->getAllStandalone();
		if (count($params) != 1) return $this->showUsage("default");

		ob_start();
		passthru($config->get("rrdtool", "rrdtool", "rrdtool"), $return);
		if ($return) exit("The rrdtool binary is required for this function...");
		ob_end_clean();

		$path = rtrim($config->get("rrdtool", "rrdpath", "/var/lib/icinga2/rrdtool"), "/") . "/";
		$file = end($params);
		if (!file_exists($path . $file)) $this->fail("XML does not exist");
		$xml = file_get_contents($path . $file);
		if (strpos($xml, $path . $file) === FALSE) $this->fail("XML contains different path");
		if (strpos($xml, "<RRD_STORAGE_TYPE>MULTIPLE") === FALSE) $this->fail("RRD is already a single file");
		$xml = @simplexml_load_string($xml);
		if (libxml_get_last_error() !== FALSE) $this->fail("XML is invalid");
		if ($xml->NAGIOS_HOSTNAME == ".pnp-internal") $this->fail("Not allowed");

		$rrdcached = $config->get("rrdtool", "rrdcached", "");
		if ($rrdcached) {
			$rrdcached = "--daemon=" . $rrdcached . " ";
			$files = " \"" . implode("\" \"", $xml->xpath('//DATASOURCE/RRDFILE')) . "\"";
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " flushcached " . $rrdcached . $files, $return);
			if ($return) exit();
		}

		$ds = 1;
		$datetime = date(".Y-m-d_His");
		$xml->NAGIOS_RRDFILE = str_replace("_" . $xml->DATASOURCE[0]->NAME . ".rrd", ".rrd", $xml->DATASOURCE[0]->RRDFILE);
		foreach ($xml->DATASOURCE as $datasource) {
			ob_start();
			passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " dump " . $rrdcached . "\"" . $datasource->RRDFILE . "\"", $return);

			if ($ds == 1) {
				$data = trim(ob_get_clean());
				$xml1 = simplexml_load_string($data);
				$count1 = count($xml1->rra);

				$xml1->ds->addChild("replace_ds");
				foreach ($xml1->rra as $rra) {
					$rra->cdp_prep->addChild("replace_" . $rra->cf . $rra->pdp_per_row . "_cdp_prep");
				}
			} else {
				$xml2 = simplexml_load_string(trim(ob_get_clean()));
				for ($i = 0; $i < $count1; $i++) {
					$count2 = count($xml1->rra[$i]->database->row);
					for ($j = 0; $j < $count2; $j++) {
						$xml1->rra[$i]->database->row[$j]->addChild("v", $xml2->rra[$i]->database->row[$j]->v);
					}
				}
				$data = $xml1->asXML();

				$xml2->ds->name = " " . $ds . " ";
				$data = str_replace("<replace_ds/>", "</ds>\n\n\t" . str_replace("</ds>", "", $xml2->ds->asXML()) . "<replace_ds/>", $data);
				foreach ($xml2->rra as $rra) {
					$replace = "<replace_" . $rra->cf . $rra->pdp_per_row . "_cdp_prep/>";
					$data = str_replace($replace, "\t" . $rra->cdp_prep->ds->asXML() . "\n\t\t" . $replace, $data);
				}
				$xml1 = simplexml_load_string($data);
			}

			rename($datasource->RRDFILE, $datasource->RRDFILE . $datetime);
			$datasource->RRDFILE = $xml->NAGIOS_RRDFILE;
			$datasource->RRD_STORAGE_TYPE = "SINGLE";
			$datasource->DS = $ds++;
		}

		if (file_exists($xml->NAGIOS_RRDFILE)) rename($xml->NAGIOS_RRDFILE, $xml->NAGIOS_RRDFILE . $datetime);
		file_put_contents($xml->NAGIOS_RRDFILE . ".dump" . $datetime, preg_replace("/<replace_[^\/]+\/>/", "", $data));
		passthru($config->get("rrdtool", "rrdtool", "rrdtool") . " restore \"" . $xml->NAGIOS_RRDFILE . ".dump" . $datetime . "\" " . $xml->NAGIOS_RRDFILE, $return);
		unlink($xml->NAGIOS_RRDFILE . ".dump" . $datetime);

		rename($path . $file, $path . $file . $datetime);
		$xml = preg_replace("/<([^\/]+)\/>/", "<$1></$1>", $xml->asXML());
		file_put_contents($path . $file, $xml);
	}
}
