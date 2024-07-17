<?php

/**
 * The logViewer.
 */
class CaplogLogViewer {

  public static function enqueueScripts($hook) {
    if ($hook == 'users_page_caplog_logviewer') {
      // Add our own custom css.
      wp_register_style("caplog-logviewer-css", plugins_url('admin/css/logviewer.css', dirname(__FILE__)), array(), filemtime(plugin_dir_path(dirname(__FILE__)) . 'admin/css/logviewer.css'));
      wp_enqueue_style('caplog-logviewer-css');
    }
  }

  /**
   * Hook listener for admin_menu.
   *
   * Add a menu item for log viewer.
   */
  public static function addLogViewerMenu() {
    add_submenu_page(
      'users.php',
      'Capabilities Logger: Log Entries',
      'Capabilities Logger',
      'edit_users',
      'caplog_logviewer',
      ['CaplogLogViewer', 'logViewerHtml']
    );
  }

  /**
   * Page callback for caplog_logviewer menu item.
   */
  public static function logViewerHtml() {
    echo '<div class="wrap">';

    // check user capabilities; if not 'edit_users', print nothing.
    if (!current_user_can('edit_users')) {
      echo 'Access denied.';
    }
    elseif(!empty($_GET['file'])) {
      self::logSingleHtml($_GET['file']);
    }
    else {
      // Purge old logs before creating the list.
      CaplogUtil::deleteOldLogs();
      // Show the list of log entries.
      self::logListHtml();
    }

    echo '</div>';
  }

  public static function logSingleHtml($fileName) {
    ?>
      <h1 class="wp-heading-inline">Capabilities Logger: View Log Entry</h1>
      <a href="admin.php?page=caplog_logviewer" class="page-title-action">All Log Entries</a>
    <?php

    echo CaplogUtil::formatSingleLog($fileName);
  }

  public static function logListHtml() {

    $pluginData = CaplogUtil::getPluginData();
    $logEntries = CaplogUtil::getLogEntriesFormatted();

    ?>
    <div class="wrap">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <div class="notice">
        <p>Log entries are removed after <?= CaplogUtil::getLogMaxAgeDays() ?> days.</p>
        <p>See also: <a href="<?= $pluginData['PluginURI']; ?>" target="_blank">Documentation for <?= $pluginData['Name']; ?> (v<?= $pluginData['Version'] ?>)</a>.</p>
      </div>
      <?php if(!empty($logEntries)) : ?>
        <table id="caplog-loglist" class="wp-list-table widefat fixed striped table-view-list">
          <thead>
            <tr>
              <th>Date/time</th>
              <th>User</th>
              <th>Roles Affected</th>
              <th>Capabilities Added?</th>
              <th>Capabilities Removed?</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logEntries as $logEntry): ?>
              <tr>
                <td><?= $logEntry['timestamp'] ?></td>
                <td><?= $logEntry['username'] ?></td>
                <td><?= $logEntry['roles'] ?></td>
                <td><?= $logEntry['added'] ?></td>
                <td><?= $logEntry['removed'] ?></td>
                <td><a href="admin.php?page=caplog_logviewer&file=<?= $logEntry['filename'] ?>">View</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else : ?>
        <p>No log entries exist at this time.</p>
      <?php endif; ?>

    <?php
  }

}
