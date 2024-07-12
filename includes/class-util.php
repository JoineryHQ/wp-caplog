<?php

/**
 * Utility methods for permlog plugin.
 */
class PermlogUtil {

  /**
   * Get array of plugin metadata.
   */
  public static function getPluginData() {
    static $pluginData;
    if (!isset($pluginData)) {
      $pluginData = get_plugin_data(__DIR__ . '/../permlog.php');
    }
    return $pluginData;
  }

  /**
   * Given a set of metdata for a log file, create a base64-encoded filename containing that data.
   *
   * Filename will be prefixed by $timestamp for ease of sorting.
   *
   * @param int $timestamp
   * @param array $metaData
   * @return string
   */
  public static function encodeFilename($timestamp, $metaData) {
    return $timestamp . '.' . base64_encode(json_encode($metaData)) . '.log';
  }

  /**
   * Given a filename (assumed to have been created by self::endeRilename()),
   * return an array of file metadata.
   *
   * @param string $filename
   * @return array
   */
  public static function decodeFilename($filename) {
    $metaData = [];
    list($junk, $base64string, $junk) = explode('.', $filename);
    $metaData = json_decode(base64_decode($base64string), TRUE);
    return $metaData;
  }

  /**
   * Get (and create if necessary) the directory in which this plugin should
   * store log files.
   *
   * @return string
   */
  public static function getLogDir() {
    $uploadDir = wp_upload_dir('', FALSE);
    $logDir = $uploadDir['basedir'] . '/' . 'permlog';
    if (!is_dir($logDir)) {
      mkdir($logDir);
    }
    return $logDir;
  }

  /**
   * Given an array of file metadata, format it for display in log list.
   *
   * @staticvar array $users Static cache for usernames per id.
   * @param array $metaData
   * @return array
   */
  public static function formatLogMeta($metaData) {
    static $users = [];
    if (empty($users[$metaData['u']])) {
      $userData = get_userdata($metaData['u']);
      $users[$metaData['u']] = $userData->user_login;
    }

    $ret = [
      'timestamp' => date('Y-m-d H:i:s', $metaData['t']),
      'roles' => implode(', ', $metaData['r']),
      'added' => self::formatBoolean(in_array('added', $metaData['a'])),
      'removed' => self::formatBoolean(in_array('removed', $metaData['a'])),
      'filename' => $metaData['filename'],
      'username' => $users[$metaData['u']],
    ];

    return $ret;
  }

  /**
   * Format a boolean value for display in log list.
   *
   * @param bool $bool
   * @return string
   */
  public static function formatBoolean($bool) {
    return ($bool ? 'Yes' : '-');
  }

  /**
   * Get a full, ordered list of log entries, formatted for display in log list.
   *
   * @return array
   */
  public static function getLogEntriesFormatted() {
    $logDir = self::getLogDir();
    $logFiles = glob($logDir .'/*.log');
    $logFiles = array_reverse($logFiles);
    $logEntries = [];
    foreach ($logFiles as $logFile) {
      $fileName = basename($logFile);
      $metaData = self::decodeFilename($fileName);
      $metaData['filename'] = $fileName;
      $formatted = self::formatLogMeta($metaData);
      $logEntries[] = $formatted;
    }
    return $logEntries;
  }

  /**
   * Format a single log entry for display in the log viewer.
   * @param string $fileName Base filename of the given log file.
   */
  public static function formatSingleLog($fileName) {
    $logFile = self::getLogDir() . '/' . $fileName;
    $lines = file($logFile);

    $headerData = [];
    $rowData = [];
    $pastRowMarker = FALSE;
    foreach ($lines as $line) {
      if (trim($line) == '--') {
        $pastRowMarker = TRUE;
        continue;
      }
      if (!$pastRowMarker) {
        list($label, $value) = explode("\t", $line);
        $headerData[$label] = $value;
      }
      else {
        $rowData[] = explode("\t", $line);
      }
    }
    ?>

    <?php if (!empty($headerData)) : ?>
      <table id="permlog-headers"><tbody>
      <?php foreach ($headerData as $label => $value) : ?>
        <tr><th class="permlog-label"><?= $label ?></th><td><?= $value ?></td></tr>
      <?php endforeach;?>
      </tbody></table>
    <?php endif; ?>

    <?php if (!empty($rowData)) : ?>
      <table id="permlog-rows" class="wp-list-table widefat fixed striped table-view-list">
        <thead>
          <tr>
            <th>Action</th>
            <th>Permission</th>
            <th>Role</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rowData as $row) : ?>
          <tr>
            <td class="permlog-action permlog-action-<?= $row[0] ?>"><?= $row[0] ?></td>
            <td><?= $row[2] ?></td>
            <td><?= $row[1] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php
  }
}
