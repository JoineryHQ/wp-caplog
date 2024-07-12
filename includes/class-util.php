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
   * Given an array of file metadata, format it for display in logList.
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
   * Format a boolean value for display in logList.
   *
   * @param bool $bool
   * @return string
   */
  public static function formatBoolean($bool) {
    return ($bool ? 'Yes' : '-');
  }

  /**
   * Get a full, ordered list of log entries, formatted for display in logList.
   *
   * @return array
   */
  public static function getLogEntriesFormatted() {
    $logDir = self::getLogDir();
    $logFiles = glob($logDir .'/*.log');
    $logFiles = array_reverse($logFiles);
    $logEntries = [];
    foreach ($logFiles as $logFile) {
      $metaData = self::decodeFilename($logFile);
      $metaData['filename'] = $logFile;
      $formatted = self::formatLogMeta($metaData);
      $logEntries[] = $formatted;
    }
    return $logEntries;
  }
}
