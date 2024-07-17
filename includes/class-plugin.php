<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://joineryhq.com
 * @since      1.0.0
 *
 * @package    Caplog
 * @subpackage Caplog/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Caplog
 * @subpackage Caplog/includes
 * @author     Allen Shaw <allen@joineryhq.com>
 */
class CaplogPlugin {

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {

    // Reference https://developer.wordpress.org/reference/hooks/update_option_option/
    // We'll use this hook to respond to non-civicrm changes to capabilities.
    global $wpdb;
    $updateRolesActionName = 'update_option_' . $wpdb->prefix . 'user_roles';
    add_action($updateRolesActionName, ['CaplogPlugin', 'updateWpUserRoles'], 10, 3);

    // Register a shutdown handler to catch the final (we hope) state of things.
    add_action('shutdown', ['CaplogPlugin', 'wpShutdown'], 10);

    // Register a "log entries list" page for this plugin.
    add_action('admin_menu', array('CaplogLogViewer', 'addLogViewerMenu'), 9);

    // Add a CSS file for the log viewer.
    // Reference https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
    add_action('admin_enqueue_scripts', ['CaplogLogViewer', 'enqueueScripts']);

	}

  /**
   * Hook listener for update_option_wp_user_roles action.
   *
   * Gives us a chance to snapshot the 'original' caps config for later comparison.
   *
   * @param array $oldValue
   * @param array $newValue
   * @param string $option
   */
  public static function updateWpUserRoles($oldValue, $newValue, $option) {
    // Only on the first time this hook is called, we'll take a snapshot of the
    // original values, to be referenced later in our 'shutdown' handler.
    static $once = false;
    if (!$once) {
      $once = true;
      CaplogUtil::$statics['CAPLOG_OLD_CAPS'] = $oldValue;
    }
  }


  /**
   * Hook listener for 'shutdown' action.
   *
   * We'll use this to gather the "final" (we hope) roles/capabilities configuration,
   * and compare it to original equivalant config. We can then log all differences
   * in one log entry (even though the 'update_option_wp_user_roles' hook may
   * have fired many times for separate changes on this PHP invocation.)
   *
   * In theory, some other 'shutdown' hook may modify capabilities after this;
   * such changes will not be logged.
   */
  public static function wpShutdown() {
    if (!empty(CaplogUtil::$statics['CAPLOG_OLD_CAPS'])) {
      // If static 'CAPLOG_OLD_CAPS' has a value, it means that 'update_option_wp_user_roles'
      // was called earlier. We'll compare that original caps value to the current
      // caps value, and log any differences.
      $originalCaps = CaplogUtil::$statics['CAPLOG_OLD_CAPS'];
      $finalCaps = wp_roles()->roles;
      $diffCapabilities = self::diffCapabilities($originalCaps, $finalCaps);
      if (!empty($diffCapabilities)) {
        // If there are any capability differences, log them.
        self::log($diffCapabilities);
      }
    }
  }

  /**
   * Given "old" and "new" versions of the array data in the wp_user_roles
   * option value, calculate what capabilities were added or removed for any role.
   * Omit comparison on the 'administrator' role, because CiviCRM insists
   * on removing/adding some caps for that role even when the user doesn't
   * call for it.
   *
   * @param array $oldCaps
   * @param array $newCaps
   *
   * @return array
   *   In the form of:
   *   $array = [
   *     'added' => [
   *       $role => [
   *         'cap_1',
   *       ],
   *     ],
   *     'removed' => [
   *       $role => [
   *         'cap_2',
   *       ],
   *     ],
   *   ];
   */
  public static function diffCapabilities($oldCaps, $newCaps) {
    // Initialize the final array to return;
    $diff = [];

    $oldCaps = CaplogUtil::sanitizeCaps($oldCaps);
    $newCaps = CaplogUtil::sanitizeCaps($newCaps);

    foreach ($oldCaps as $oldRole => $oldRoleProperties) {
      if (!empty($oldRoleProperties['capabilities'])) {
        $arrayDiffKey = array_diff_key($oldRoleProperties['capabilities'], $newCaps[$oldRole]['capabilities']);
        if (!empty($arrayDiffKey)) {
          $diff['removed'][$oldRole] = array_keys($arrayDiffKey);
        }
      }
    }
    foreach ($newCaps as $newRole => $newRoleProperties) {
      if (!empty($newRoleProperties['capabilities'])) {
        $arrayDiffKey = array_diff_key($newRoleProperties['capabilities'], $oldCaps[$newRole]['capabilities']);
        if (!empty($arrayDiffKey)) {
          $diff['added'][$newRole] = array_keys($arrayDiffKey);
        }
      }
    }
    return $diff;
  }

  /**
   * Give the output of self::diffCapabilities, format it as a multi-line string
   * for printing into a log file.
   *
   * @param array $diff
   * @return string
   */
  public static function formatDiff($diff) {
    $lines = [];
    foreach ($diff as $action => $role) {
      foreach ($role as $roleKey => $capabilities) {
        foreach ($capabilities as $capability) {
          $lines[] = "{$action}\t{$roleKey}\t{$capability}";
        }
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Given the output of self::diffCapabilities, print it, along with relevant
   * metadata, to a log file.
   *
   * @param array $diff
   */
  public static function log($diff) {
    // Note current user amd time.
    $current_user = wp_get_current_user();
    $timestamp = time();

    // Convert $diff to lines we can print.
    $diffLines = self::formatDiff($diff);

    // Build and populate an array of header lines.
    $headerLines = [];
    $headerLines['User'] = "{$current_user->user_login} (id={$current_user->ID})";
    $headerLines['Referer'] = $_SERVER['HTTP_REFERER'];
    $headerLines['Is wp-cli'] = (defined('WP_CLI') && WP_CLI ? 'Yes' : 'No');
    // (We always use Unix timestamps on save, and format/tz-adjust them on display.
    $headerLines['Timestamp'] = $timestamp;

    // Build and poplate an array of metadata to store in filename.
    // (The log list presents this data in a table, and it's easier to get
    // it from the filename than from reading each file.)
    $rolesAffected = array_unique(
      array_merge(
        array_keys($diff['added'] ?? []),
        array_keys($diff['removed'] ?? [])
      )
    );
    $actions = array_keys($diff);
    $fileNameMeta = [
      'u' => $current_user->ID,
      't' => $timestamp,
      'r' => $rolesAffected,
      'a' => $actions,
      'm' => microtime(),
    ];

    // Create the filename based on that filename metadata.
    $fileName = CaplogUtil::encodeFilename($timestamp, $fileNameMeta);

    // Wrtie the log file
    $logFile = CaplogUtil::getLogDir() . '/' . $fileName;
    $fp = fopen($logFile, 'w');
    foreach ($headerLines as $label => $value) {
      fputs($fp, "{$label}\t{$value}\n");
    }
    fputs($fp, "--\n");
    fputs($fp, "$diffLines\n");
    fclose($fp);

    // Finally, cleanup any too-old log files.
    CaplogUtil::deleteOldLogs();
  }
}
