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

    // civicrm_access_roles is called on every civicrm page init; this is the only
    // way I've found to reliably check the final capabilities after submission
    // of civicrm's "WordPress Access Control" form (I thought to use hook_civicrm_postProcess,
    // but this form uses civicrm_exit() before that hook is fired.)
    add_filter( 'civicrm_access_roles', ['CaplogPlugin', 'civicrmAccessRoles'], 10, 1);

    // We'll use this hook to identify when civicrm's "WordPress Access Control"
    // form has been submitted. This is an important distinction, because civicrm
    // has a, well, unusual way of managing changes submited in this form, which is:
    // remove all civicrm-related capabilities, and then add them back one-at-a-time. So if
    // we blithely respond on the update_option_wp_user_roles, we'll never be
    // able to compare before-and after.
    add_filter( 'civicrm_validateForm', ['CaplogPlugin', 'civicrmValidateForm'], 10, 5 );

    // Register a "log entries list" page for this plugin.
    add_action('admin_menu', array('CaplogLogViewer', 'addLogViewerMenu'), 9);

    // Add a CSS file for the log viewer.
    // Reference https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
    add_action('admin_enqueue_scripts', ['CaplogLogViewer', 'enqueueScripts']);

	}

  /**
   * Hook listener for civicrm_access_roles filter. This hook gets called a lot; we'll
   * only take action if we've just submitted the "WordPress Access Control"
   * form.
   *
   * @param array $args
   * @return array $args as received, unchanged.
   */
  public static function civicrmAccessRoles($args){
    // Check whether we've just submitted the civicrm "WordPress Access Control" form
    $isFormValidate = CaplogUtil::$statics['CAPLOG_IS_CIVICRM_CAPABILITY_FORM'];
    if ($isFormValidate) {
      // If we have, then we should have a snapshot of pre-submission capabilities,
      // which we can compare to current capabilities.
      $diffCapabilities = self::diffCapabilities(CaplogUtil::$statics['CAPLOG_OLD_CAPS'], wp_roles()->roles);
      if (!empty($diffCapabilities)) {
        // If there are any capability differences, log them.
        self::log($diffCapabilities);
      }
    }
    // Return $args unchanged; without this, the upstream code execution breaks,
    // and the current user can be blocked out of CiviCRM until some other WP
    // admin page is loaded.
    return $args;
  }

  /**
   * Implements hook_civicrm_validateForm.
   *
   * We need this so that we can identify when we've submitted civicrm's "WP Capabilities"
   * form, and so we can take a pre-modification snapshot of capabilities, for
   * later post-modification comparison.
   */
  public static function civicrmValidateForm($formName, &$fields, &$files, &$form, &$errors){
    if ($formName == 'CRM_ACL_Form_WordPress_Permissions') {
      CaplogUtil::$statics['CAPLOG_IS_CIVICRM_CAPABILITY_FORM'] = TRUE;
      CaplogUtil::$statics['CAPLOG_OLD_CAPS'] = wp_roles()->roles;
    }
  }

  /**
   * Hook listener for update_option_wp_user_roles action.
   *
   * We'll only take action if we're NOT submitting the civicrm "WP Capabilities"
   * form.
   *
   * @param array $oldValue
   * @param array $newValue
   * @param string $option
   */
  public static function updateWpUserRoles($oldValue, $newValue, $option) {
    // Check whether we've just submitted the civicrm "WordPress Access Control" form
    $isFormValidate = CaplogUtil::$statics['CAPLOG_IS_CIVICRM_CAPABILITY_FORM'];
    if (!$isFormValidate) {
      // If we're not, then this is some other mechanism modifying user capabilities.
      // So just compare the capabilities $oldValue and $newValue.
      $diffCapabilities = self::diffCapabilities($oldValue, $newValue);
      if (!empty($diffCapabilities)) {
        // If there's a difference in capabilities, log that.
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
