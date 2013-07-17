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

function label($labels,$label){
  if(!empty($labels[$label]))
    return $labels[$label];
  else
    return $label;
}

function send($from,$to,$headers,$body,$rcmail){
  if(!$rcmail->config->get('cron_smtp_user')) {
    preg_match('#Subject: (.*?)$#im', $headers, $matches);
    $subject = $matches[1];
    $ret = mail($to, $subject, $body, $headers);
  }
  else {
    if(!is_object($rcmail->smtp))
      $rcmail->smtp_init(true);
    $ret = $rcmail->smtp->send_mail($from, $to, $headers, $body);
  }
  return $ret;
}

function mime($from,$to,$parts,$rcmail){
  $subject = $parts['subject'];
  $body = $parts['body'];
  $ical = $parts['attachment'];
   
  $ctb = md5(rand() . microtime());

  $headers  = "Return-Path: $from\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "X-RC-Attachment: ICS\r\n";
  $headers .= "Content-Type: multipart/mixed; boundary=\"=_$ctb\"\r\n";
  $headers .= "Date: " . date('r', time()) . "\r\n";
  $headers .= "From: $from\r\n";
  $headers .= "To: $to\r\n";
  $headers .= "Subject: $subject\r\n";
  $headers .= "Reply-To: $from\r\n";

  $msg_body  = "--=_$ctb";
  $msg_body .= "\r\n";
  $mpb = md5(rand() . microtime());
  $msg_body .= "Content-Type: multipart/alternative; boundary=\"=_$mpb\"\r\n\r\n";

  $txt_body  = "--=_$mpb";
  $txt_body .= "\r\n";
  $txt_body .= "Content-Transfer-Encoding: 7bit\r\n";
  $txt_body .= 'Content-Type: text/plain; charset="' . RCMAIL_CHARSET . '"' . "\r\n\r\n";
  $LINE_LENGTH = $rcmail->config->get('line_length', 72);  
  $h2t = new html2text($body, false, true, 0);
  $txt = rc_wordwrap($h2t->get_text(), $LINE_LENGTH, "\r\n");
  $txt = wordwrap($txt, 998, "\r\n", true);
  $txt_body .= "$txt\r\n";
  $txt_body .= "--=_$mpb";
  $txt_body .= "\r\n";
          
  $msg_body .= $txt_body;
          
  $msg_body .= "Content-Transfer-Encoding: quoted-printable\r\n";
  $msg_body .= 'Content-Type: text/html; charset="' . RCMAIL_CHARSET . '"' . "\r\n\r\n";
  $msg_body .= str_replace("=","=3D",$body);
  $msg_body .= "\r\n\r\n";
  $msg_body .= "--=_$mpb--";
  $msg_body .= "\r\n\r\n";
          
  $ics  = "--=_$ctb";
  $ics .= "\r\n";
  $ics .= "Content-Transfer-Encoding: base64\r\n";
  $ics .= 'Content-Type: text/calendar; name=calendar.ics; charset="' . RCMAIL_CHARSET . '"' . "\r\n";
  $ics .= "Content-Disposition: attachment; filename=calendar.ics\r\n\r\n";
          
  $ics .= chunk_split(base64_encode($ical), $LINE_LENGTH, "\r\n");

  $ics .= "--=_$ctb--";
          
  $msg_body .= $ics;
  
  return array('headers'=>$headers,'body'=>$msg_body);

}

function compose($val,$tz,$labels,$rcmail,$ical){
  $stz = date_default_timezone_get();
  date_default_timezone_set($tz);
  $webmail_url = RCMAIL_URL;
  $prefix = label($labels,'reminder');
  $append = '';
  if($val['end_unix'] && $val['end_unix'] != $val['start_unix']){
    $append .= " - ";
    if(date('dmY',$val['start_unix']) != date('dmY',$val['end_unix']))
      if($val['allDay'])
        $append .= date('d.m.Y',$val['end_unix']);
      else
        $append .= date($rcmail->config->get('date_long','d.m.Y H:i'),$val['end_unix']);
    else
      if(!$val['allDay'])
        $append .= date('H:i',$val['end_unix']);
  }
  if($val['allDay'])
    $date = date('d.m.Y',$val['start_unix']);
  else
    $date = date($rcmail->config->get('date_long','d.m.Y H:i'),$val['start_unix']);
  if($val['title']){
    $subject = $prefix . ": " . $val['title'] . " @ " . $date . $append;
  } 
  else
    $subject = $prefix . " @ " . $date . $append;
    
  $allDay = "";
  if($val['allDay']){
    $allDay = "(".label($labels,'all-day').")";
  }
  else{
    if($val['end_unix'])
      $val['end_unix'] = $val['end_unix'];  
  }
  $val['start_unix'] = (int) $val['start_unix'];
  $val['end_unix'] = (int) $val['end_unix'];
  $val['expires'] = (int) $val['expires'];

  $nl = "\r\n";
  $id = "rcical";
  if($val['recur'] != 0){
    $id="rcicalrr";
  }
  $body  = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN">' . $nl;
  $body .= '<html><body>' . $nl;
  $body .= '<div id="' . $id . '"><table>'.$nl;
  if($val['del'] == 1){
    $body .= '<tr><td colspan="2"><b>' . label($labels,'removed') . '</b></td></tr>'.$nl;
    $body .= '<tr><td colspan="2"><hr /></td></tr>'.$nl;
  }
  if($val['title'])
    $body .= '<tr><td>' . label($labels,'summary') . ': </td><td>' . $val['title'] . '</td></tr>'.$nl;
  if($val['description'])  
    $body .= '<tr><td>' . label($labels,'description') . ': </td><td>' . $val['description'] . '</td></tr>'.$nl;
  if($val['location'])  
    $body .= '<tr><td>' . label($labels,'location') . ': </td><td>' . $val['location'] . '</td></tr>'.$nl;
  if($val['categories'])  
    $body .= '<tr><td>' . label($labels,'category') . ': </td><td>' . $val['categories'] . '</td></tr>'.$nl;
  if($val['start_unix'])  
    $body .= '<tr><td>' . label($labels,'start') . ': </td><td>' . ($allDay?str_replace(date($rcmail->config->get('date_today'),$val['start_unix']),"",date($rcmail->config->get('date_long'),$val['start_unix'])):date($rcmail->config->get('date_long'),$val['start_unix'])) . ' (' . $tz . ') ' . $allDay . '</td></tr>'.$nl;
  if($val['start_unix'] != $val['end_unix'] && $val['end_unix'])
    $body .= '<tr><td>' . label($labels,'end') . ': </td><td>' . ($allDay?str_replace(date($rcmail->config->get('date_today'),$val['end_unix']),"",date($rcmail->config->get('date_long'),$val['end_unix'])):date($rcmail->config->get('date_long'),$val['end_unix'])) . ' (' . $tz . ') ' . $allDay . '</td></tr>'.$nl;
  if($val['recur'] != 0){
    $body .= '<tr><td>' . label($labels,'recur') . ': </td><td>';
    $a_weekdays = array(0=>'SU',1=>'MO',2=>'TU',3=>'WE',4=>'TH',5=>'FR',6=>'SA');
    $freq = "";
    $t = strtotime($val['expires']);
    if(!$t){
      $t = 2082758399;
    }
    $until = "UNTIL=" . date('Ymd',$t) . "T235959Z";
    switch($val['rr']){
      case 'd':
        if($val['recur'] == 86401){
          $body .= label($labels,'workday');
          $freq = 'RRULE:FREQ=DAILY;' . $until . ';BYDAY=';
          foreach($rcmail->config->get('workdays') as $key1 => $val1){
            $freq .= $a_weekdays[$val1] . ",";
          }
          $freq = substr($freq,0,strlen($freq)-1);
        }
        else{
          $intval = round($val['recur'] / 86400,0);
          $body .= label($labels,'day');
          $freq = 'RRULE:FREQ=DAILY;' . $until . ';INTERVAL=' . $intval;
        }
        break;
      case 'w':
        $intval = round($val['recur'] / 604800,0);
        $body .= label($labels,'week');
        $freq = 'RRULE:FREQ=WEEKLY;' . $until . ';INTERVAL=' . $intval;
        break;
      case 'm':
        $intval = round($val['recur'] / 2592000,0);
        $body .= label($labels,'month');
        $freq = 'RRULE:FREQ=MONTHLY;' . $until . ';INTERVAL=' . $intval;
        break;
      case 'y':
        $intval = round($val['recur'] / 31536000,0);
        $body .= label($labels,'year');
        $freq = 'RRULE:FREQ=YEARLY;' . $until . ';INTERVAL=' . $intval;
        break;    }
    if($val['occurrences'] > 0)
      $freq .= ';COUNT=' . $val['occurrences'];
    if($val['byday'])
      $freq .= ';BYDAY=' . $val['byday'];
    if($val['bymonth'])
      $freq .= ';BYMONTH=' . $val['bymonth'];
    if($val['bymonthday'])
      $freq .= ';BYMONTHDAY=' . $val['bymonthday'];   
    $body .= '</td></tr>'.$nl;
    $body .= '<tr><td>' . label($labels,'rrule') . ':</td><td>' . str_replace("RRULE:","",$freq) . '</td></tr>' . $nl;
    $body .= '<tr><td>' . label($labels,'expires') . ': </td><td>' . substr(date($rcmail->config->get('date_long'),$t),0,10) . '</td></tr>'.$nl;
  }
  if($val['del'] != 1)
    $body .= '<tr><td>URL: </td><td><a href="' . $webmail_url . '?_task=dummy&amp;_action=plugin.calendar&amp;_date=' . $val['start_unix'] . '" target="_new">' . label($labels,'click_here') . '</a></td></tr>'.$nl;  
  $body .= '</table></div>'.$nl;
  $body .= '<p style="text-align:justify;width:443px;">' . label($labels, 'reminderfooter') . '</p>' . $nl;
  $body .= '</body></html>' . $nl;
  if (function_exists('mb_encode_mimeheader')){
    mb_internal_encoding(RCMAIL_CHARSET);
    $subject= mb_encode_mimeheader($subject,
      RCMAIL_CHARSET, 'Q', $rcmail->config->header_delimiter(), 8);
  }
  else{
    $subject = '=?UTF-8?B?'.base64_encode($subject). '?=';
  }
  $val['start'] = $val['start_unix'];
  $val['end'] = $val['end_unix'];
  date_default_timezone_set($stz);
  return array('subject'=>$subject,'body'=>$body,'attachment'=>$ical);
}
/* End Functions */

/* Program */

include INSTALL_PATH . 'program/include/iniset.php';

$rcmail = rcmail::get_instance();

foreach($rcmail_config as $key => $val){
  $rcmail->config->set($key, $val);
}
if($rcmail->config->get('smtp_user') != ''){
  $rcmail->config->set('smtp_user',$rcmail->config->get('cron_smtp_user'));
}
if($rcmail->config->get('smtp_pass') != ''){
  $rcmail->config->set('smtp_pass',$rcmail->config->get('cron_smtp_pass'));
}

if(!is_dir($rcmail_config['log_dir']))
  ini_set('error_log', INSTALL_PATH.'logs/errors');
include_once INSTALL_PATH . 'plugins/calendar/program/utils.php';
include_once INSTALL_PATH . 'plugins/http_request/class.http.php';
$utils = new Utils($rcmail);

$nupcoming = 0;

$result = $rcmail->db->query(
  "SELECT * FROM " . dbtable(get_table_name('reminders'),$rcmail) . " 
   WHERE ".dbq('runtime',$rcmail)."<? AND ".dbq('type',$rcmail)."=?",
   $time_start_s,
   'email'
);
$reminders = array();
while ($result && ($reminder = $rcmail->db->fetch_assoc($result))) {
  $reminders[] = $reminder;
}

$notifiers = array();
foreach($reminders as $key => $reminder){
  $props = unserialize($reminder['props']);
  $preferences = unserialize($props['user_data']['preferences']);
  $lg = $props['lang'];
  include INSTALL_PATH . 'plugins/calendar/localization/en_US.inc';
  $merge = $labels;
  if(file_exists(INSTALL_PATH . 'plugins/calendar/localization/' . $lg . '.inc')){
    include INSTALL_PATH . 'plugins/calendar/localization/' . $lg . '.inc';
  }
  if(is_array($merge) && is_array($labels))
    $labels = array_merge($merge, $labels);
  $tz = $preferences['tzname'];
  if(!$tz)
    $tz = date_default_timezone_get();
  $from = $rcmail->config->get('cron_sender');
  $to = $props['event']['remindermailto'];
  $parts = compose($props['event'],$tz,$labels,$rcmail,$props['ics']);
  $mime = mime($from,$to,$parts,$rcmail);
  $hash = md5($to . $props['event']['uid']);
  if($notifiers[$hash]){
    $query = $rcmail->db->query(
      "DELETE FROM " . dbtable('reminders',$rcmail) . "
      WHERE " . dbq('reminder_id',$rcmail) . "=?",
      $reminder['reminder_id']
    );
  }
  else{
    $notifiers[$hash]['to'] = $to;
    $notifiers[$hash]['from'] = $from;
    $notifiers[$hash]['user_id'] = $reminder['user_id'];
    $notifiers[$hash]['headers'] = $mime['headers'];
    $notifiers[$hash]['body'] = $mime['body'];
    $notifiers[$hash]['reminder_id'] = $reminder['reminder_id'];
    $notifiers[$hash]['backend'] = $preferences['backend'];
    $notifiers[$hash]['event_id'] = $props['event']['id'];
    $notifiers[$hash]['type'] = $reminder['type'];
  }
}
foreach($notifiers as $key => $notifier){
  if($notifier['type'] == 'email'){
    $ret = send($notifier['from'],$notifier['to'],$notifier['headers'],$notifier['body'],$rcmail);
    if($ret){
      $nupcoming ++;
      $query = $rcmail->db->query(
        "DELETE FROM " . dbtable('reminders',$rcmail) . "
        WHERE " . dbq('reminder_id',$rcmail) . "=?",
        $notifier['reminder_id']
      );
      $events_table = $rcmail->config->get('db_table_events', 'events');
      $db_table = str_replace('_caldav','',$events_table);
      $default = array(
        'database' => '', // default db table
        'caldav' => '_caldav', // caldav db table (= default db table) extended by _caldav
      );
      $map = $rcmail->config->get('backend_db_table_map', $default);
      if($notifier['backend'] == 'caldav'){
        $db_table .= $map['caldav'];
      }
      else{
        $db_table .= $map['database'];
      }
      $query = $rcmail->db->query(
        "UPDATE " .dbtable($db_table,$rcmail) . " 
        SET ".
          dbq('remindersent',$rcmail)."=?
        WHERE ".dbq('event_id',$rcmail)."=?",
        time(),
        $notifier['event_id']
      );
    }
  }
  $utils->curlRequest($rcmail->config->get('cron_rc_url') . '?_cron=1&_schedule=1&_userid=' . $notifier['user_id'] . '&_event_id=' . $notifier['event_id'] . '&_backend=' . $notifier['backend'] . '&_cronstart=' . $time_start_s);
}

$time_end = microtime_float();
$time = $time_end - $time_start;

if($rcmail->config->get('cron_log') == true){
  if($nupcoming > 0){
    write_log('calendar',"Reminders cron job");
    write_log('calendar',"  $nupcoming upcoming event(s) notified.");
    write_log('calendar',"  Script terminated after $time seconds runtime.");
  }
}

$rcmail->session->destroy(session_id());
print "done [$time seconds runtime] " . date('Y-m-d H:i:s',time()) . "\n";
exit;
/* End Program */
?>