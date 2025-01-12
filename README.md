# rrdtool module for Icinga Web 2

## About

This module is a replacement for pnp4nagios.

It uses `rrdtool` to store perfdata (from Icinga2 PerfdataWriter) in RRDs and to generate graphs from RRDs.

[![Screenshot](.github/Screenshot.png)](.github/Screenshot.png)

[![Graphs](.github/Graphs.png)](.github/Graphs.png)

## License

Icinga Web 2 and this Icinga Web 2 module are licensed under the terms of the GNU General Public License Version 2, you will find a copy of this license in the LICENSE file included in the source package.

## Requirements

This module requires Icinga Web 2 (>= 2.9.0) and the PHP RRD-extension (highly recommended) or the rrdtool-binaries.

Fast storage: Writing a lot of RRD files also requres a lot of IO.

## Installation

Extract this module to your Icinga Web 2 modules directory as `rrdtool` directory.

## Configuration

### Icinga2

To enable the PerfdataWriter you need to run `icinga2 feature enable perfdata`.

You can set the `rotation_interval` a little lower to have less perfdata waiting for the next processing run.

When you use checkcommands like `nrpe` or `by_ssh`, that actually call other commands, you can add a customvar like `rrdtool` to your services and append it to the checkcommand in your perfdata to allow differentiation:

    object PerfdataWriter "perfdata" {
    	rotation_interval = 10s
    	service_format_template = "DATATYPE::SERVICEPERFDATA\tTIMET::$service.last_check$\tHOSTNAME::$host.name$\tSERVICEDESC::$service.name$\tSERVICEPERFDATA::$service.perfdata$\tSERVICECHECKCOMMAND::$service.check_command$$rrdtool$\tHOSTSTATE::$host.state$\tHOSTSTATETYPE::$host.state_type$\tSERVICESTATE::$service.state$\tSERVICESTATETYPE::$service.state_type$"
    }

### Module

To be able to render the graphs, PHP and/or the webserver needs the permission to read the RRD and XML files. A check is included in the configuration page.

After the module has been configured, you can set up a cronjob running the command `icingacli rrdtool process` every minute to process the perfdata-files and generate/update the RRDs. The user needs the permission to write the RRD and XML files.

If you need to process lots of perfdata, you can use `icingacli rrdtool process bulk` for bulk mode. Make sure only one processing-instance is running at a time.

By default all perfdata of a service is written into a single RRD. Having less files to be written is better for performance, but to be able to update an RRD, the number of datasources must not change. For checks where the number of datasource might change (for example disk partitions or virtual network interfaces), each datasource can be written to a dedicated RRD.

To do that, you have to list the checkcommands (including the value of the customvar, if you use it like suggested above) in the "Checks with multiple RRDs" setting. To prevent dataloss, this setting only effects new RRDs. Existing ones keep their "mode", but can be converted using the commands `icingacli rrdtool split Host/Service.xml` or `icingacli rrdtool join Host/Service.xml`. Keep in mind that especially joining might be time and memory intesive.

### Templates

Additional pnp4nagios-templates should be compatible and can be placed in the `templates` directory.

The module searches for templates named like the checkcommands (including the value of the customvar, if you use it like suggested above) with the extension `.php`.

The original pnp4nagios-templates are also included as a fallback. If no file matches, the default-template `default.php` is used.

### rrdcached

You can also use `rrdcached`. Keep in mind, that while it reduces IO, dataloss might occur unless it is actually written to disk.

The `base_dir` for the daemon needs to be set to the directory where the RRD and XML files are (or to one of its parents).

The user running PHP and/or the webserver needs the permission to access the daemon.

Also read the security considerations in the `rrdcached` manual.

## CLI

This module also provides CLI commands. For a list of commands run `icingacli rrdtool`.

You can export graphs with various parameters. See `icingacli rrdtool graph --help` for details.

The values for `size` and `range` are also valid in URLs. Example: `/rrdtool/graph?1500*200&host=.pnp-internal&range=2025`
