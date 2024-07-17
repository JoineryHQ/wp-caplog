# WordPress: Capabilities Logger

Log all changes to WordPress role capabilities.

## Functionality

This plugin aims to maintain a log of any changes to the capabilities granted to WordPress roles.

It has been specifically tested to log such changes initiated by these mechanisms:
* [CiviCRM](https://civicrm.org/)'s "WordPress Access Control" form.
* The [User Role Editor](https://wordpress.org/plugins/user-role-editor/) plugin.
* The [Members](https://wordpress.org/plugins/members/) plugin.

## Usage

When this plugin is activated, logging begins automatically.

Log entries are available through the admin menu item Users > Capabilities Logger (/wp-admin/users.php?page=caplog_logviewer)

Each log entry provides a detailed view of the action, including the following:
* Username of the logged-in user at the time of the change.
* Date and time of the change.
* URL of the page on which the change was made.
* A detailed list of every capability added and/or removed from each affected role.
* (Note that changes to the Administrator role are not logged, because this role already has "all capabilities".)

To conserve disk space, any log entries over 365 days old are periodically removed.


## Configuration
No configuration is required; no configuration options are available.

## Installation
* Copy this package to the `/plugins`directory on your WordPress site.
* Activate the plugin "Capabilities Logger".

## Support

Support for this plugin is handled under Joinery's ["As-Is Support" policy](https://joineryhq.com/software-support-levels#as-is-support).

Public issue queue for this plugin: https://github.com/JoineryHQ/wp-caplog/issues