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
 * @package    Permlog
 * @subpackage Permlog/includes
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
 * @package    Permlog
 * @subpackage Permlog/includes
 * @author     Allen Shaw <allen@joineryhq.com>
 */
class PermlogPlugin {

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {

    // Reference https://developer.wordpress.org/reference/hooks/update_option_option/
    // We'll use this hook to respond to non-civicrm changes to permissions.
    add_action('update_option_wp_user_roles', ['PermlogPlugin', 'updateWpUserRoles'], 10, 3);

    // civicrm_access_roles is called on every civicrm page init; this is the only
    // way I've found to reliably check the final permissions after submission
    // of civicrm's CRM_ACL_Form_WordPress_Permissions form (I thought to use hook_civicrm_postProcess,
    // but this form uses civicrm_exit() before that hook is fired.)
    add_filter( 'civicrm_access_roles', ['PermlogPlugin', 'civicrmAccessRoles'], 10, 1);

    // We'll use this hook to identify when civicrm's CRM_ACL_Form_WordPress_Permissions
    // form has been submitted. This is an important distinction, because civicrm
    // has a, well, unusual way of managing changes submited in this form, which is:
    // remove all civicrm permisisons, and then add them back one-at-a-time. So if
    // we blithely respond on the update_option_wp_user_roles, we'll never be
    // able to compare before-and after.
    add_filter( 'civicrm_validateForm', ['PermlogPlugin', 'civicrmValidateForm'], 10, 5 );

    // Register a "log entries list" page for this plugin.
    add_action('admin_menu', array('PermlogLogViewer', 'addLogViewerMenu'), 9);

    // Add a CSS file for the log viewer.
    // Reference https://developer.wordpress.org/reference/hooks/admin_enqueue_scripts/
    add_action('admin_enqueue_scripts', ['PermlogLogViewer', 'enqueueScripts']);

	}

  /**
   * Hook listener for civicrm_access_roles filter. This hook gets called a lot; we'll
   * only take action if we've just submitted the CRM_ACL_Form_WordPress_Permissions
   * form.
   *
   * @param array $args
   * @return array $args as received, unchanged.
   */
  public static function civicrmAccessRoles($args){
    // Check whether we've just submitted the civicrm "WP permisisons" form
    $isFormValidate = Civi::$statics['PERMLOG_IS_CIVICRM_PERMISSION_FORM'];
    if ($isFormValidate) {
      // If we have, then we should have a snapshot of pre-submission permissions,
      // which we can compare to current permisisons.
      $diffPermissions = self::diffPermissions(Civi::$statics['PERMLOG_OLD_PERMS'], get_option('wp_user_roles'));
      if (!empty($diffPermissions)) {
        // If there are any permission differences, log them.
        self::log($diffPermissions);
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
   * We need this so that we can identify when we've submitted civicrm's "WP Permissions"
   * form, and so we can take a pre-modification snapshot of permissions, for
   * later post-modification comparison.
   */
  public static function civicrmValidateForm($formName, &$fields, &$files, &$form, &$errors){
    if ($formName == 'CRM_ACL_Form_WordPress_Permissions') {
      Civi::$statics['PERMLOG_IS_CIVICRM_PERMISSION_FORM'] = TRUE;
      Civi::$statics['PERMLOG_OLD_PERMS'] = get_option('wp_user_roles');
    }
  }

  /**
   * Hook listener for update_option_wp_user_roles action.
   *
   * We'll only take action if we're NOT submitting the civicrm "WP Permissions"
   * form.
   *
   * @param array $oldValue
   * @param array $newValue
   * @param string $option
   */
  public static function updateWpUserRoles($oldValue, $newValue, $option) {
    // Check whether we've just submitted the civicrm "WP permisisons" form
    $isFormValidate = Civi::$statics['PERMLOG_IS_CIVICRM_PERMISSION_FORM'];
    if (!$isFormValidate) {
      // If we're not, then this is some other mechanism modifying user permissions.
      // So just compare the permissions $oldValue and $newValue.
      $diffPermissions = self::diffPermissions($oldValue, $newValue);
      if (!empty($diffPermissions)) {
        // If there's a difference in permisisons, log that.
        self::log($diffPermissions);
      }
    }
  }

  /**
   * Given "old" and "new" versions of the array data in the wp_user_roles
   * option value, calculate what permissions were added or removed for any role.
   * Omit comparison on the 'administrator' role, because CiviCRM insists
   * on removing/adding some perms for that role even when the user doesn't
   * call for it.
   *
   * @param array $oldPerms
   * @param array $newPerms
   *
   * @return array
   *   In the form of:
   *   $array = [
   *     'added' => [
   *       $role => [
   *         'perm_1',
   *       ],
   *     ],
   *     'removed' => [
   *       $role => [
   *         'perm_2',
   *       ],
   *     ],
   *   ];
   */
  public static function diffPermissions($oldPerms, $newPerms) {
    // Initialize the final array to return;
    $diff = [];

    // We don't want to bother comparing 'administrator' permissions, so unset them.
    unset($oldPerms['administrator'], $newPerms['administrator']);

    foreach ($oldPerms as $oldRole => $oldRoleProperties) {
      if (!empty($oldRoleProperties['capabilities'])) {
        $arrayDiffKey = array_diff_key($oldRoleProperties['capabilities'], $newPerms[$oldRole]['capabilities']);
        if (!empty($arrayDiffKey)) {
          $diff['removed'][$oldRole] = array_keys($arrayDiffKey);
        }
      }
    }
    foreach ($newPerms as $newRole => $newRoleProperties) {
      if (!empty($newRoleProperties['capabilities'])) {
        $arrayDiffKey = array_diff_key($newRoleProperties['capabilities'], $oldPerms[$newRole]['capabilities']);
        if (!empty($arrayDiffKey)) {
          $diff['added'][$newRole] = array_keys($arrayDiffKey);
        }
      }
    }
    return $diff;
  }

  /**
   * Give the output of self::diffPermissions, format it as a multi-line string
   * for printing into a log file.
   *
   * @param array $diff
   * @return string
   */
  public static function formatDiff($diff) {
    $lines = [];
    foreach ($diff as $action => $role) {
      foreach ($role as $roleKey => $permissions) {
        foreach ($permissions as $permission) {
          $lines[] = "{$action}\t{$roleKey}\t{$permission}";
        }
      }
    }
    return implode("\n", $lines);
  }

  /**
   * Given the output of self::diffPermisisons, print it, along with relevant
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
    $headerLines['Timestamp'] = date('Y-m-d H:i:s', $timestamp);

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
    ];

    // Create the filename based on that filename metadata.
    $fileName = PermlogUtil::encodeFilename($timestamp, $fileNameMeta);

    // Wrtie the log file
    $logFile = PermlogUtil::getLogDir() . '/' . $fileName;
    $fp = fopen($logFile, 'w');
    foreach ($headerLines as $label => $value) {
      fputs($fp, "{$label}\t{$value}\n");
    }
    fputs($fp, "--\n");
    fputs($fp, "$diffLines\n");
    fclose($fp);

    // Finally, cleanup any too-old log files.
    PermlogUtil::deleteOldLogs();
  }
}
