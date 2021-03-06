# rrdtool module for Icinga Web 2

## About

This module is a replacement for pnp4nagios.

It uses `rrdtool` to store perfdata (from Icinga2 PerfdataWriter) in RRDs and to generate graphs from RRDs.

[![Screenshot](.github/Screenshot.png)](.github/Screenshot.png)

## License

Icinga Web 2 and this Icinga Web 2 module are licensed under the terms of the GNU General Public License Version 2, you will find a copy of this license in the LICENSE file included in the source package.

## Requirements

This module requires Icinga Web 2 (>= 2.9.0) and the PHP RRD-extension (highly recommended) or the rrdtool-binaries.

## Installation

Extract this module to your Icinga Web 2 modules directory as `rrdtool` directory.

Additional pnp4nagios-templates should be compatible and can be placed in the `templates` directory.

## Configuration

To enable the PerfdataWriter you need to run `icinga2 feature enable perfdata`.

You can set the `rotation_interval` a little lower to have less perfdata waiting for the next processing run.

When you use checkcommands like `nrpe`, that actually call other commands, you can add a customvar like `rrdtool` to your services and append it to the checkcommand in your perfdata to allow differentiation:

    object PerfdataWriter "perfdata" {
    	rotation_interval = 10s
    	service_format_template = "DATATYPE::SERVICEPERFDATA\tTIMET::$service.last_check$\tHOSTNAME::$host.name$\tSERVICEDESC::$service.name$\tSERVICEPERFDATA::$service.perfdata$\tSERVICECHECKCOMMAND::$service.check_command$$rrdtool$\tHOSTSTATE::$host.state$\tHOSTSTATETYPE::$host.state_type$\tSERVICESTATE::$service.state$\tSERVICESTATETYPE::$service.state_type$"
    }

By default all perfdata of a service is written to a single RRD. They expect the number of datasources to stay the same.

On some checks it might be expected that this number changes over time. You can configure these checkcommands so that a different RRD will be used for each datasource.

Example: A check that lists all disks or partitions. When each is in a separate file they can be updated independently. So adding or removing disks/partition does not prevent the update.

After configuring the module you can set up a cronjob running `icingacli rrdtool process` every minute to generate/update the RRDs.

This module also has a CLI command to export graphs with various parameters. See `icingacli rrdtool graph --help` for details.
