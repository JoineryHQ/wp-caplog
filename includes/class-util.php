<?php

/**
 * Utility methods for caplog plugin.
 */
class CaplogUtil {
  /**
   * A central location for static variable storage.
   * @var array
   * ```
   * `CaplogUtil::$statics[__CLASS__]['foo'] = 'bar';
   * ```
   */
  public static $statics = [];

  /**
   * Get array of plugin metadata.
   */
  public static function getPluginData() {
    static $pluginData;
    if (!isset($pluginData)) {
      $pluginData = get_plugin_data(__DIR__ . '/../caplog.php');
    }
    return $pluginData;
  }

  /**
   * Given a set of metdata for a log file, create a base64-encoded filename containing that data.
   *
   * Filename will be prefixed by $tim3estamp for ease of sorting and for identifying old files to purge.
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
    $logDir = $uploadDir['basedir'] . '/' . 'caplog';
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
      // (We always use Unix timestamps on save, and format/tz-adjust them on display.
      'timestamp' => wp_date('Y-m-d H:i:s', $metaData['t']),
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
   * Get a list of (full system path for) all log files.
   */
  public static function getLogFilesList() {
    $logDir = self::getLogDir();
    $logFiles = glob($logDir .'/*.log');
    $logFiles = array_reverse($logFiles);
    return $logFiles;
  }
  /**
   * Get a full, ordered list of log entries, formatted for display in log list.
   *
   * @return array
   */
  public static function getLogEntriesFormatted() {
    $logFiles = self::getLogFilesList();
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
      $line = trim($line);
      if ($line == '--') {
        $pastRowMarker = TRUE;
        continue;
      }
      if (!$pastRowMarker) {
        list($label, $value) = explode("\t", $line);
        // If this is the timetamp, format it with wp timezone.
        // (We always use Unix timestamps on save, and format/tz-adjust them on display.
        if ($label == 'Timestamp') {
          $value = wp_date('Y-m-d H:i:s', $value);
        }
        $headerData[$label] = $value;
      }
      else {
        $rowData[] = explode("\t", $line);
      }
    }
    ?>

    <?php if (!empty($headerData)) : ?>
      <table id="caplog-headers"><tbody>
      <?php foreach ($headerData as $label => $value) : ?>
        <tr><th class="caplog-label"><?= $label ?></th><td><?= $value ?></td></tr>
      <?php endforeach;?>
      </tbody></table>
    <?php endif; ?>

    <?php if (!empty($rowData)) : ?>
      <table id="caplog-rows" class="wp-list-table widefat fixed striped table-view-list">
        <thead>
          <tr>
            <th>Action</th>
            <th>Capability</th>
            <th>Role</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rowData as $row) : ?>
          <tr>
            <td class="caplog-action caplog-action-<?= $row[0] ?>"><?= $row[0] ?></td>
            <td><?= $row[2] ?></td>
            <td><?= $row[1] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <?php
  }

  /**
   * Get the number of days we're allowed to preserve log files.
   * @return int
   */
  public static function getLogMaxAgeDays() {
    // Hard-coded to 364 days. We can make this configurable later.
    return 365;
  }

  /**
   * Delete log files which exceed the maximum log file age.
   */
  public static function deleteOldLogs() {
    // The lowest (oldest) allowable timestamp is [maxAgeDays * (seconds per day)] seconds ago.
    $minFileNameTimestamp = time() - (self::getLogMaxAgeDays() * 24 * 60 * 60);

    // Get all existing log files.
    $logFiles = self::getLogFilesList();
    foreach ($logFiles as $logFile) {
      $fileName = basename($logFile);
      list($fileNameTimestamp, $junk, $junk) = explode('.', $fileName);
      if ($fileNameTimestamp < $minFileNameTimestamp) {
        // If the timestamp (as shown in the filename) is less than the minimum
        // allowable timestamp, delete the file.
        unlink($logFile);
      }
    }
  }

  /**
   * Sanitize capabilities array by removing values we don't want to compare.
   *
   * - Capabilities with a value of 'false' are removed (we'll only compare those that are actually 'true').
   *
   * @param Array $caps As contained in wp option 'wp_user_roles'.
   * @return Array, modified.
   */
  public static function sanitizeCaps($caps) {
    // Remove any 'false' capabilities -- they're the same as if they did not exist,
    // and removing them makes comparisons easier.
    foreach ($caps as $role => &$roleProperties) {
      $roleProperties['capabilities'] = array_filter($roleProperties['capabilities']);
    }

    return $caps;
  }
}
