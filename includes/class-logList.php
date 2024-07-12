<?php

/**
 * The core plugin class.
 */
class PermlogLogList {

  /**
   * Hook listener for admin_menu.
   * 
   * Add a menu item for logList viewer.
   */
  public static function addViewLogMenu() {
    add_submenu_page(
      'users.php',
      'Permissions Logger: Log Entries',
      'Permissions Logger',
      'edit_users',
      'permlog_loglist',
      ['PermlogLogList', 'logListHtml']
    );
  }

  /**
   * Page callback for permlog_loglist menu item.
   */
  public static function logListHtml() {  
    // check user capabilities; if not 'edit_users', print nothing.
    if (!current_user_can('edit_users')) {
      echo '<div class="wrap">Permisison denied.</div>';
      return;
    }
    
    $pluginData = PermlogUtil::getPluginData();    
    $logEntries = PermlogUtil::getLogEntriesFormatted();
    
    ?>
    <div class="wrap">
      <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
      <div class="notice">
        <p>See also: <a href="<?= $pluginData['PluginURI']; ?>" target="_blank">Documentation: <?= $pluginData['Name']; ?></a>.</p>
      </div>
      <table id="permlog-loglist" class="wp-list-table widefat fixed striped table-view-list">
        <thead>
          <tr>
            <th>Date/time</th>
            <th>User</th>
            <th>Roles Affected</th>
            <th>Permissions Added?</th>
            <th>Permissions Removed?</th>
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
              <td><a href="#<?= $logEntry['filename'] ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    
    <?php
  }

}
