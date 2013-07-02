<?php
chdir(dirname(__FILE__));
$time_start = microtime_float();
$time_start_s = time();

/* Configuration */
if(isset($_SERVER['SCRIPT_FILENAME']))
  $dir = dirname($_SERVER['SCRIPT_FILENAME']);
else if(isset($_SERVER['SCRIPT_NAME']))
  $dir = dirname($_SERVER['SCRIPT_NAME']);
else{
  die("Can't detect File Location");
}
$dir = str_replace('plugins/calendar/cron','',$dir);
$dir = str_replace('plugins\\calendar\\cron','',$dir);
if(file_exists($dir . 'plugins/calendar/cron/reminders.php')){
  define('INSTALL_PATH', str_replace('plugins/calendar/cron','',$dir));
}
else{
  $dir = str_replace('plugins/calendar/cron','',$_SERVER['PWD']);
  define('INSTALL_PATH', $dir);
}
if(file_exists(INSTALL_PATH . 'plugins/global_config/config.inc.php')){
  include INSTALL_PATH . 'plugins/global_config/config.inc.php';
}
else{
  $ext = ".dist";
  if(file_exists(INSTALL_PATH . 'plugins/calendar/config.inc.php'))
    $ext = "";
  include INSTALL_PATH . 'plugins/calendar/config.inc.php' . $ext;
}

if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != $rcmail_config['cron_ip']){
  die("Access denied");
}

define('RCMAIL_URL', $rcmail_config['cron_rc_url']);
/* End Configuration */

/* Functions */
function microtime_float(){
  list($usec, $sec) = explode(" ", microtime());
  return ((float)$usec + (float)$sec);
}

function dbq($str, $rcmail) {
  return $rcmail->db->quoteIdentifier($str);
}
  
function dbtable($str, $rcmail) {
  return $rcmail->db->quoteIdentifier(get_table_name($str));
}

/* End Functions */


/* Program */
include INSTALL_PATH . 'program/include/iniset.php';
$rcmail = rcmail::get_instance();
foreach($rcmail_config as $key => $val)  
  $rcmail->config->set($key, $val);
if(!is_dir($rcmail_config['log_dir'])) 
  ini_set('error_log', INSTALL_PATH.'logs/errors');
include_once INSTALL_PATH . 'plugins/calendar/program/utils.php';
include_once INSTALL_PATH . 'plugins/http_request/class.http.php';
$utils = new Utils($rcmail);

$numusers = 0;

$res = $rcmail->db->query('SELECT * FROM ' . dbtable(get_table_name('users'),$rcmail) . ' WHERE ' . dbq('user_id',$rcmail) .'<>?',0);
while($ret = $rcmail->db->fetch_assoc($res)){
  $users[] = $ret;
}

foreach($users as $idx => $user){
  $preferences = unserialize($user['preferences']);
  if($preferences['backend'] == 'caldav'){
    $numusers ++;
    print "Sync User " . $user['username'] . " ...\n";
    $utils->curlRequest($rcmail->config->get('cron_rc_url') . '?_cron=1&_import=1&_userid=' . $user['user_id'] . '&_cronstart=' . $time_start_s);
    print "sleeping 1 second ...\n";
    sleep(1);
  }
}
$time_end = microtime_float();
$time = $time_end - $time_start;

if($rcmail->config->get('cron_log') == true){
  if($numusers > 0){
    write_log('calendar',"Sync CalDAV reminders cron job");
    write_log('calendar',"  $numusers users(s) processed.");
    write_log('calendar',"  Script terminated after $time seconds runtime.");
  }
}

$rcmail->session->destroy(session_id());
print "done [$time seconds runtime] " . date('Y-m-d H:i:s',time()) . "\n";
exit;
/* End Program */
