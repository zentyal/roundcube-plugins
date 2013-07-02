<?php
class Utils
{
  private $rcmail = null;
  private $backend = null;
  public $categories = array();

  public function __construct($rcmail, $backend='dummy') {
    $this->rcmail = $rcmail;
    $this->backend = $backend;
    $this->categories = array_merge((array)$rcmail->config->get('public_categories',array()),(array)$rcmail->config->get('categories',array()));
  }
  /**
   * Flatten an array
   *
   * @param array
   * @return array
   * @access private
   */
  public function array_flatten($array) { 
    if (!is_array($array)) { 
      return false; 
    } 
    $result = array(); 
    foreach ($array as $key => $value) { 
      if (is_array($value)) { 
        $result = array_merge($result, $this->array_flatten($value)); 
      }
      else { 
        $result[] = $value; 
      }
    }
    return $result; 
  }
  
  /**
   * Map array received from database for the GUI
   *
   * @param array event
   * @return array
   * @access public
   */
  public function eventArrayMap($event,$category=false){
    if(!$ctz = get_input_value('_tz', RCUBE_INPUT_GET)){
      $ctz = calendar::getClientTimezoneName($this->rcmail->config->get('timezone', 'auto'));
    }
    if(!$btz = get_input_value('_btz', RCUBE_INPUT_GET)){
      $btz = $_SESSION['tzname'];
    }
    $stz = date_default_timezone_get();
    date_default_timezone_set($ctz);
    $cnfoffset = date('Z', $event['start']) / 3600;
    date_default_timezone_set($btz);
    $clientoffset = date('Z', $event['start']) / 3600;
    if($event['clone']){
      $clientcloneoffset = date('Z', $event['clone']) / 3600;
      $cloneoffset = ($cnfoffset - $clientcloneoffset) * 3600;
    }
    $offset = ($cnfoffset - $clientoffset) * 3600;
    date_default_timezone_set($stz);
    $event['start'] = $event['start'] + $offset;
    if($event['end'])
      $event['end'] = $event['end'] + $offset;
    if($event['clone'])
      $event['clone'] = $event['clone'] + $cloneoffset;

    if(isset($_GET['_from'])){
      $user = $this->getUser(get_input_value('_from', RCUBE_INPUT_GPC));
      $prefs = unserialize($user['preferences']);
      if(isset($prefs['categories'])){
        $this->categories = $prefs['categories'];
      }
    }
    $this->categories = array_merge($this->rcmail->config->get('public_categories',array()),$this->categories);
    if(!$category)
      $category = $event['categories'];
    $colors = $this->categories[$category];
    $color_save = $colors;
    if(!$colors){
     $colors = $this->rcmail->config->get('default_category');
    }
    $past = false;
    if($event['start'] < time() && $event['end'] < time()){
      $colors = $this->lighterColor($colors, 50);
      $past = true;
    }
    $fontcolor = '#' . $this->getFontColor($colors);
    $colors = '#' . $colors;
    $clone_formatted = null;
    if($event['clone']){
      $clone_formatted = gmdate('Y-m-d\TH:i:s.000+00:00',$event['clone']);
    }
    $clone_end_formatted = null;
    if($event['clone_end'])
      $clone_end_formatted = gmdate('Y-m-d\TH:i:s.000+00:00',$event['clone_end']);
    $jevent=array( 
      'id'                  => $event['event_id'],
      'hash'                => (string) hexdec(substr(sha1(serialize($event)),0,15)),
      'uid'                 => $event['uid'],
      'user_id'             => $this->rcmail->user->ID,
      'start'               => $event['start'],
      'start_unix'          => $event['start'],
      'end'                 => $event['end'],
      'end_unix'            => $event['end'],
      'title'               => $event['summary'],
      'description'         => stripcslashes($event['description']),
      'location'            => $event['location'],
      'className'           => asciiwords($event['categories'],true,''),
      'classNameDisp'       => $event['categories'],
      'classProtected'      => $event['classProtected'],
      'color_save'          => $color_save,
      'color'               => $colors,
      'backgroundColor'     => $colors,
      'borderColor'         => $colors,
      'textColor'           => $fontcolor,
      'allDay'              => false,
      'expires'             => $event['expires'],
      'expires_unix'        => $event['expires'],
      'url'                 => '#',
      'onclick'             => $event['onclick'],
      'rr'                  => $event['rr'],
      'recur'               => $event['recur'],
      'occurrences'         => $event['occurrences'],
      'hasoccurred'          => $event['hasoccurred'],
      'recurrence_id'       => $event['recurrence_id'],
      'editable'            => $event['editable'],
      'onclick'             => $event['onclick'],
      'clone'               => $event['clone'],
      'clone_end'           => $event['clone_end'],
      'clone_formatted'     => $clone_formatted,
      'clone_end_formatted' => $clone_end_formatted,
      'byday'               => $event['byday'],
      'bymonth'             => $event['bymonth'],
      'bymonthday'          => $event['bymonthday'],
      'reminder'            => $event['reminder'],
      'reminderservice'     => $event['reminderservice'],
      'remindermailto'      => $event['remindermailto'],
      'remindersent'        => $event['remindersent'],
      'reminder_id'         => $event['reminder_id'],
      'timestamp'           => $event['timestamp'],
      'past'                => $past,
      'del'                 => $event['del'],
      'initial'             => $event['initial']
    );
    return $jevent;
  }
  
  /**
   * Fetch user properties
   *
   * @param string id
   * @return array
   * @access public
   */
  function getUser($id=null) {
    $rcmail = $this->rcmail;
    if(empty($id))
      return array();
    $sql_result = $rcmail->db->query("SELECT * FROM ".get_table_name('users')." WHERE user_id=?", $id);
    $sql_arr = $rcmail->db->fetch_assoc($sql_result);
    return $sql_arr;
  }
  
  /**
   * Fetch a URL on server side
   *
   * @param string url
   * @param bool auth
   * @return string
   * @access public
   */
  public function curlRequest($url, $auth=false, $timeout=false) {
    // find me: implement fetch local feeds directly from database
    $http = new MyRCHttp;
    $httpConfig['method'] = 'GET';
    $httpConfig['target'] = $url;
    $http->initialize($httpConfig); 
    if(ini_get('safe_mode') || ini_get('open_basedir')){
      $http->useCurl(false);
    }
    if($auth)
      $http->setAuth($_SESSION['username'],$this->rcmail->decrypt($_SESSION['password']));
    if($timeout)
      $http->SetTimeout($timeout);
    $http->execute();
    $content = ($http->error) ? $http->error : $http->result;
    return $content;
  }    
  
  /**
   * Truncate all events
   *
   * @param integer User identifier
   * @param integer mode: 0 -> truncate, 1 -> set del flag, 2 -> restore, 3 -> delete
   * @access public
   */  
  public function truncateEvents($userid=null,$mode=0) {
    if(empty($this->rcmail->user->ID)){
      $this->rcmail->user->ID = $userid;
    }
    $this->backend->truncateEvents($mode);
  }
  
  /**
   * Purge all events
   *
   * @param integer User identifier
   * @access public
   */  
  public function purgeEvents($userid=null) {
    if(empty($this->rcmail->user->ID)){
      $this->rcmail->user->ID = $userid;
    }
    $this->backend->purgeEvents();
  }
  
  /**
   * Remove timestamps from all events
   *
   * @access public
   */  
  public function removeTimestamps($userid=false) {
    if(empty($this->rcmail->user->ID)){
      $this->rcmail->user->ID = $userid;
    }
    $this->backend->removeTimestamps();
  }
  
  /**
   * Import events from iCalendar format
   *
   * @param  array Associative events array
   * @access public
   */
  public function importEvents($events,$userid=false,$echo=false,$idoverwrite=false,$item=false,$client=false,$className=false,$href=false,$etag=false) {
    // find me: investigate the use of params echo, idoverwrite, item, client, className
    /* calendar.php  958: $arr = (array)$this->utils->importEvents($part,false,true,'preview'); // string
       calendar.php 1290: $success = $this->utils->importEvents($content); // string
       calendar.php 1314: $success = $this->utils->importEvents($part,false,false,false,$items); // string
       calendar.php 3303: if(is_array($this->utils->importEvents($cr,false,true))){ // string
       calendar.php 3863: $this->utils->importEvents($content,$userid=false,$echo=false,$idoverwrite=$url,$item=false,$client=false,$className); // string
       calendar.php 3925: $this->utils->importEvents($content,$userid=false,$echo=false,$idoverwrite=$url,$item=false,$client=false,$className); // string
       caldav.php   1164: $this->utils->importEvents($val['data']); // string
    */
    if(!$events){
      return true;
    }
    $freq = array(
            'DAILY'   =>     86400,
            'WEEKLY'  =>    604800,
            'MONTHLY' =>   2592000,
            'YEARLY'  =>  31536000,
            );            
    $rr   = array(
            'DAILY'   => 'd',
            'WEEKLY'  => 'w',
            'MONTHLY' => 'm',
            'YEARLY'  => 'y',
            );
    if($userid){
      $this->rcmail->user->ID = $userid;
    }
    $post_categories = get_input_value('categories', RCUBE_INPUT_GPC);
    if(!class_exists('vcalendar')){
      require_once(INSTALL_PATH . 'plugins/calendar/program/ical.class.php');
    }
    $cal = $events;
    $jsonevents = array();
    $items = 0;
    $ret = true;
    $vcalendar =  new vcalendar(array());
    $vcalendar->parse($cal);
    $stz = date_default_timezone_get();
    $ctz = calendar::getClientTimezoneName($this->rcmail->config->get('timezone', 'auto'));
    date_default_timezone_set($ctz);
    while($vevent = $vcalendar->getComponent("vevent")){
      $items ++;
      if($item){
        if($item != $items){
          continue;
        }
      }
      $xevent = array();
      if(is_array($vevent->dtstart)){
        if($vevent->dtstart['params']['VALUE'] == 'DATE'){
          $xevent['ALLDAY'] = true;
        }
        $val = implode('', $vevent->dtstart['value']);
        if($ts = strtotime($val)){
          if(is_numeric($ts)){
            $xevent['DTSTART'] = $ts;
            $xevent['SUMMARY'] = '';
            $xevent['DESCRIPTION'] = '';
            $xevent['LOCATION'] = '';
            $xevent['CATEGORIES'] = '';
            $recur = 0;
            $expires = 0;
            $remindertype = 0;
            $remindermailto = false;
            $reminderbefore = 0;
            if(is_array($vevent->dtend)){
              $val = implode('', $vevent->dtend['value']);
              $xevent['DTEND'] = strtotime($val);
            }
            else{
              $xevent['DTEND'] = min(0, $xevent['DTSTART']);
            }
            if(is_array($vevent->summary) && $vevent->summary['value']){
              $xevent['SUMMARY'] = $vevent->summary['value'];
            }
            if(is_array($vevent->description)){
              $xevent['DESCRIPTION'] = $vevent->description[0]['value'];
            }
            if(is_array($vevent->location)){
              $xevent['LOCATION'] = $vevent->location['value'];
            }
            if(is_array($vevent->categories)){
              $xevent['CATEGORIES'] = $vevent->categories[0]['value'];
            }
            if(!$className)
              $dbclassName = $xevent['CATEGORIES'];
            else
              $dbclassName = $className;
            if(is_array($vevent->uid)){
              $xevent['UID'] = $vevent->uid['value'];
            }
            if(is_array($vevent->recurrenceid)){
              $xevent['RECURRENCE-ID']['TZID'] = $vevent->recurrenceid['params']['TZID'];
              $xevent['RECURRENCE-ID']['unixtime'] = strtotime(implode('', $vevent->recurrenceid['value']));
              if(!empty($xevent['RECURRENCE-ID'])){
                if(is_array($xevent['RECURRENCE-ID'])){
                  $recurrence_id = $xevent['RECURRENCE-ID']['unixtime'];
                }
              }
            }
            if(is_array($vevent->exdate)){
              foreach($vevent->exdate as $date){
                $xevent['EXDATE'][] = strtotime(implode('', $date['value'][0]));
              }
              if(!empty($xevent['EXDATE'])){
                $exdates = array();
                $exdates[(int) $xevent['EXDATE']] = $xevent['EXDATE'];
                $exdates = array_values($exdates);
                $exdates = serialize($exdates);
              }
            }
            if(is_array($vevent->rrule)){
              $rrule = $vevent->rrule[0]['value'];
              if(is_array($rrule)){
                foreach($rrule as $prop => $val){
                  switch($prop){
                    case 'BYDAY':
                      foreach($val as $key => $value){
                        if(is_numeric($value)){
                          $val[$key] = $value . '|'; 
                        }
                      }
                      $val = str_replace('|,', '', implode(',', $this->array_flatten($val)));
                      break;
                    case 'BYMONTH':
                    case 'BYMONTHDAY':
                      if(is_array($val)){
                        $val = implode(',', $this->array_flatten($val));
                      }
                      break;
                    default:
                      if(is_array($val)){
                        $val = implode('', $val);
                        if($ts = strtotime($val)){
                          $val = $ts;
                        }
                      }
                  }
                  $xevent['RRULE'][$prop] = $val;
                }
              }
              if(!empty($xevent['RRULE']['FREQ'])){
                $recur = $freq[$xevent['RRULE']['FREQ']];
                if(!empty($xevent['RRULE']['INTERVAL'])){
                  $recur = $recur * $xevent['RRULE']['INTERVAL'];
                }
                $recur = $rr[$xevent['RRULE']['FREQ']] . $recur;
              }
              if(!empty($xevent['RRULE']['COUNT'])){
                $occurrences = $xevent['RRULE']['COUNT'];
              }
              else{
                $occurrences = 0;
              }
              if(!empty($xevent['RRULE']['UNTIL'])){
                $expires = $xevent['RRULE']['UNTIL'];
              }  
              else{
                $expires = strtotime(CALEOT);
              }
              if(!empty($xevent['RRULE']['BYDAY'])){
                $byday = $xevent['RRULE']['BYDAY'];
              }
              if(!empty($xevent['RRULE']['BYMONTH'])){
                $bymonth = $xevent['RRULE']['BYMONTH'];
              }
              if(!empty($xevent['RRULE']['BYMONTHDAY'])){
                $bymonthday = $xevent['RRULE']['BYMONTHDAY'];
              }
            }
            if($valarm = $vevent->getComponent('valarm')){
              if(is_array($valarm->attendee)){
                $xevent[strtolower($valarm->action['value'])]['mailto'] = str_ireplace('MAILTO:', '', $valarm->attendee[0]['value']);
              }
              if(is_array($valarm->trigger)){
                if($valarm->trigger['value']['sec']){
                  $xevent[strtolower($valarm->action['value'])]['trigger'] = $valarm->trigger['value']['sec'];
                }
                else if($valarm->trigger['value']['min']){
                  $xevent[strtolower($valarm->action['value'])]['trigger'] = $valarm->trigger['value']['min'] * 60;
                }
                else if($valarm->trigger['value']['hour']){
                  $xevent[strtolower($valarm->action['value'])]['trigger'] = $valarm->trigger['value']['hour'] * 3600;
                }
                else if($valarm->trigger['value']['day']){
                  $xevent[strtolower($valarm->action['value'])]['trigger'] = $valarm->trigger['value']['day'] * 86400;
                }
              }
              if(isset($xevent['email'])){
                $remindertype = 'email';
                $remindermailto = $xevent['email']['mailto'];
                $reminderbefore = $xevent['email']['trigger'];
              }
              else if(isset($xevent['display'])){
                $remindertype = 'popup';
                $reminderbefore = $xevent['display']['trigger'];
              }
            }
            if(empty($xevent['UID'])){
              $xevent['UID'] = md5(serialize($xevent));
            }
            if(empty($event['CATEGORIES'])){
              if($post_categories){
                $xevent['CATEGORIES'] = $post_categories;
              }
            }
            if(!$echo){
              $ret = $this->backend->newEvent(
                $xevent['DTSTART'],
                $xevent['DTEND'],
                str_replace("\\","",str_replace("\\n","\n",$xevent['SUMMARY'])),
                str_replace("\\","",str_replace("\\n","\n",$xevent['DESCRIPTION'])),
                str_replace("\\","",str_replace("\\n","\n",$xevent['LOCATION'])),
                str_replace("\\","",str_replace("\\n","\n",$dbclassName)),
                0,
                $recur,
                $expires,
                $occurrences,
                $byday,
                $bymonth,
                $bymonthday,
                $recurrence_id,
                $exdates,
                $reminderbefore,
                $remindertype,
                $remindermailto,
                array('uid' => $xevent['UID'], 'href' => $href, 'etag' => $etag),
                $client
              );
            }
            else{
              if($recur === 0)
                $recur = '';
              else{
                $recur = rcube_label('calendar.yes');
              }
              if(strtolower($xevent['CATEGORIES']) == '[undefined]'){
                $xevent['CATEGORIES'] = '';
              }
              if(!$className){
                $className = 'preview';
              }
              $jsonevents[] = array(
                'id' => $idoverwrite,
                'title' => str_replace("\\","",str_replace("\\n","\n",$xevent['SUMMARY'])),
                'start' => $xevent['DTSTART'],
                'end' => $xevent['DTEND'],
                'start_unix' => $xevent['DTSTART'],
                'end_unix' => $xevent['DTEND'],
                'allDay' => false,
                'recurs' => $recur,
                'description' => str_replace("\\","",str_replace("\\n","\n",$xevent['DESCRIPTION'])),
                'location' => str_replace("\\","",str_replace("\\n","\n",$xevent['LOCATION'])),
                'className' => $className,
                'category' => $xevent['CATEGORIES'],
                'classNameDisp' => $xevent['CATEGORIES'],
                'editable' => false,
              );
            }
          }
        }
      }
    }
    date_default_timezone_set($stz);
    if($echo){
      return $jsonevents;
    }
    if(is_array($ret)){
      return $ret;
    }
    else{
      return false;
    }
  }
  
  /**
   * Export events to iCalendar format
   *
   * @param  integer Start time events window ('Y-m-d H:i:s')
   * @param  integer End time events window ('Y-m-d H:i:s')
   * @return string  Events in iCalendar format (http://tools.ietf.org/html/rfc5545)
   * @access public
   */
  public function exportEvents($start, $end, $events=true, $showdel=false, $showclone=false, $category=false) {
    if (!empty($this->rcmail->user->ID)) {
      $rcmail = $this->rcmail;
      if(!is_array($events)){
        if($events === true){
          if($rcmail->config->get('backend') == 'caldav'){
            return $this->backend->exportEvents($category);
          }
          else{
            $events = $this->backend->getEvents($start, $end, $labels=array(), $category=false, $filter=false, $client=true);
          }
        }
      }
      $ical = "BEGIN:VCALENDAR\n";
      $ical .= "VERSION:2.0\n";
      $ical .= "PRODID:-//" . $rcmail->config->get('product_name') . "//NONSGML Calendar//EN\n";
      $ical .= "X-WR-Timezone: Europe/London\n";
      $stz = date_default_timezone_get();
      if($_SESSION['tzname'])
        $tz = $_SESSION['tzname'];
      else if(get_input_value('_btz', RCUBE_INPUT_GPC))
        $tz = get_input_value('_btz', RCUBE_INPUT_GPC);
      else if(get_input_value('_tz', RCUBE_INPUT_GPC))
        $tz = get_input_value('_tz', RCUBE_INPUT_GPC);
      else
        $tz = $stz;
      date_default_timezone_set($tz);
      foreach ($events as $event) {
        if(($event['del'] != 1 || $showdel) && (!$event['clone'] || $showclone)){
          $ical .= "BEGIN:VEVENT\n";
          if(
              (!$event['end'] || ($event['end'] - $event['start'] >= 86340 && $event['end'] - $event['start'] <= 86400)) ||
              ($event['end'] - $event['start'] >= (86340 - 3600) && $event['end'] - $event['start'] <= (86400 - 3600) && date('I', $event['start']) < date('I', $event['end'])) ||
              ($event['end'] && $event['end'] - $event['start'] >= (86340 + 3600) && $event['end'] - $event['start'] <= (86400 + 3600) && date('I', $event['start']) > date('I', $event['end']))
            ){
            $ical .= "DTSTART;VALUE=DATE:" . date('Ymd',$event['start']) . "\n";
            if($event['end'] && $event['end'] != $event['start']){
              $end = $event['end'] + 86340;
              if(date('I', $event['start']) < date('I', $end)){
                $end = $end - 3600;
              }
              $ical .= "DTEND;VALUE=DATE:" . date('Ymd',$end) . "\n";
            }
          }
          else if($event['clone']){
            $ical .= "DTSTART:" . gmdate('Ymd\THis\Z',$event['clone']) . "\n";
            if($event['clone'] != $event['clone_end']) {
              $ical .= "DTEND:" . gmdate('Ymd\THis\Z',$event['clone_end']) . "\n";
            }
          }
          else{
            $ical .= "DTSTART:" . gmdate('Ymd\THis\Z',$event['start']) . "\n";
            if($event['start'] != $event['end']) {
              $ical .= "DTEND:" . gmdate('Ymd\THis\Z',$event['end']) . "\n";
            }
          }
          $freq = $this->rrule($event);
          if($freq){
            $ical .= $freq . "\n";
            $freq = null;
          }
          $ical .= "DTSTAMP:" . gmdate('Ymd\THis\Z',time()) . "\n";
          $ical .= "SEQUENCE:" . time() . "\n";
          if(!empty($event['title']))
            $event['summary'] = $event['title'];
          if(!empty($event['summary']))
            $ical .= "SUMMARY:" . $event['summary'] . "\n";
          if(!empty($event['description']))  
            $ical .= "DESCRIPTION:" . str_replace("\n","\\n",str_replace("\r","",$event['description'])) . "\n";
          if(!empty($event['location'])) {
            $ical .= "LOCATION:" . $event['location'] . "\n";
          }
          if(!empty($event['classNameDisp']))
            $event['categories'] = $event['classNameDisp'];
          if(!empty($event['categories'])) {
            $ical .= "CATEGORIES:" . $event['categories'] . "\n";
          }
          if($event['uid']) {
            $ical .= "UID:" . $event['uid'] . "\n";
          }
          else{
            $ical .= "UID:" . $event['event_id'] . "@" . $rcmail->user->data['username'] . "\n";
          }
          if($event['exdates']){
            $exdates = @unserialize($event['exdates']);
            if(is_array($exdates)){
              foreach($exdates as $idx => $exdate){
                if(!$event['end'] || ($event['end'] - $event['start'] >= 86340 && $event['end'] - $event['start'] <= 86400) ||
                  (date('I', $event['start']) < date('I', $event['end']) && $event['start'] >= (86340 - 3600) && $event['end'] - $event['start'] <= (86400 - 3600)) ||
                  (date('I', $event['start']) > date('I', $event['end']) && $event['start'] >= (86340 + 3600) && $event['end'] - $event['start'] <= (86400 + 3600))
                ){
                  $ical .= "EXDATE;VALUE=DATE:" . date('Ymd',$exdate) . "\n";
                }
                else{
                  $ical .= "EXDATE:" . gmdate('Ymd\THis\Z',$exdate) . "\n";
                }
              }
            }
          }
          if($event['recurrence_id']){
            if(!$event['end'] || ($event['end'] - $event['start'] >= 86340 && $event['end'] - $event['start'] <= 86400) ||
               (date('I', $event['start']) < date('I', $event['end']) && $event['start'] >= (86340 - 3600) && $event['end'] - $event['start'] <= (86400 - 3600)) ||
               (date('I', $event['start']) > date('I', $event['end']) && $event['start'] >= (86340 + 3600) && $event['end'] - $event['start'] <= (86400 + 3600))
            ){
              $ical .= "RECURRENCE-ID;VALUE=DATE:" . date('Ymd',$event['recurrence_id']) . "\n";
            }
            else{
              $ical .= "RECURRENCE-ID:" . gmdate('Ymd\THis\Z',$event['recurrence_id']) . "\n";
            }
          }
          if($event['reminderservice'] == 'email'){
            $ical .= 'BEGIN:VALARM' . "\n";
            $ical .= 'ACTION:EMAIL' . "\n";
            if($event['summary']) {
              $ical .= 'SUMMARY:' . $event['summary'] . "\n";
            }
            if($event['description']){
              $ical .= 'DESCRIPTION:' . $event['description'] . "\n";
            }
            $ical .= 'ATTENDEE:mailto:' . $event['remindermailto'] . "\n";
            $unit = 'S';
            $t = 'T';
            $temp = $event['reminder'];
            if(($temp / 60)  == round($temp / 60)){
              $temp = $temp / 60;
              $unit = 'M';
            }
            if(($temp / 60)  == round($temp / 60)){
              $temp = $temp / 60;
              $unit = 'H';
            }
            if(($temp / 24)  == round($temp / 24)){
              $temp = $temp / 24;
              $unit = 'D';
              $t = '';
            }
            if(($temp / 7)  == round($temp / 7)){
              $temp = $temp / 7;
              $unit = 'W';
              $t = '';
            }
            $ical .= 'TRIGGER;VALUE=DURATION:-P' . $t . $temp . $unit . "\n";
            $ical .= 'END:VALARM' . "\n";
          }
          else if($event['reminderservice'] == 'popup'){
            $ical .= 'BEGIN:VALARM' . "\n";
            $ical .= 'ACTION:DISPLAY' . "\n";
            if($event['summary']) {
              $ical .= 'DESCRITION:' . $event['summary'] . "\n";
            }
            else if($event['description']){
              $ical .= 'DESCRIPTION:' . $event['description'] . "\n";
            }
            else{
              $ical .= 'DESCRIPTION:MyRoundcube Standard ' . "\n";
            }
            $unit = 'S';
            $t = 'T';
            $temp = $event['reminder'];
            if(($temp / 60)  == round($temp / 60)){
              $temp = $temp / 60;
              $unit = 'M';
            }
            if(($temp / 60)  == round($temp / 60)){
              $temp = $temp / 60;
              $unit = 'H';
            }
            if(($temp / 24)  == round($temp / 24)){
              $temp = $temp / 24;
              $unit = 'D';
              $t = '';
            }
            if(($temp / 7)  == round($temp / 7)){
              $temp = $temp / 7;
              $unit = 'W';
              $t = '';
            }
            $ical .= 'TRIGGER;VALUE=DURATION:-P' . $t . $temp . $unit . "\n";
            $ical .= 'END:VALARM' . "\n";
          }
          $ical .= "END:VEVENT\n";
        }
      }
      $ical .= "END:VCALENDAR";
      date_default_timezone_set($stz);
      return $ical;
    }
  }
  
  /**
   * Create RRULE
   *
   * @param  array Event
   * @return string RRULE in iCalendar format (http://tools.ietf.org/html/rfc5545)
   * @access public
   */
  public function rrule($event){
    $freq = false;
    if(!$event['recurring']){
      $event['recurring'] = $event['recur'];
    }
    if(!empty($event['recurring']) || !empty($event['rr'])){
      $a_weekdays = array(0=>'SU',1=>'MO',2=>'TU',3=>'WE',4=>'TH',5=>'FR',6=>'SA');
      $freq = "";
      $t = $event['expires'];
      if(!$t){
        $t = strtotime(CALEOT);
      }
      if($event['occurrences'] != 0){
        $until = "COUNT=" . $event['occurrences'];
      }
      else{
        $until = "UNTIL=" . gmdate('Ymd\THis',$t) . "Z";
      }
      $byday = '';
      if($event['byday']){
        $byday = ';BYDAY='.$event['byday'];
      }
      $bymonth = '';
      if($event['bymonth']){
        $bymonth = ';BYMONTH='.$event['bymonth'];
      }
      $bymonthday = '';
      if($event['bymonthday']){
        $bymonthday = ';BYMONTHDAY='.$event['bymonthday'];
      }
      $freq = 'DAILY';
      switch($event['rr']){
        case 'd':
          if($event['recurring'] == 86401){
            $freq = 'RRULE:FREQ=WEEKLY;' . $until . ';BYDAY=';
            foreach($this->rcmail->config->get('workdays') as $key1 => $val1){
              $freq .= $a_weekdays[$val1] . ",";
            }
            $freq = substr($freq,0,strlen($freq)-1);
          }
          else{
            $interval = max(1,$event['recurring'] / 86400);
            if($byday){
              $freq = 'RRULE:FREQ=WEEKLY;' . $until . ';INTERVAL=' . $interval . $byday;
            }
            else{
              $freq = 'RRULE:FREQ=DAILY;' . $until . ';INTERVAL=' . $interval . $byday;
            }
          }
          break;
        case 'w':
          $interval = max(1,$event['recurring'] / (86400 * 7));
          $freq = 'RRULE:FREQ=WEEKLY;' . $until . ';INTERVAL=' . $interval . $byday;
          break;
        case 'm':
          $interval = max(1,$event['recurring'] / (86400 * 30));
          $freq = 'RRULE:FREQ=MONTHLY;' . $until . ';INTERVAL=' . $interval . $byday . $bymonth . $bymonthday;
          break;
        case 'y':
          $interval = max(1,$event['recurring'] / (86400 * 365));
          $freq = 'RRULE:FREQ=YEARLY;' . $until . ';INTERVAL=' . $interval . $byday . $bymonth . $bymonthday;
          break;
      }
    }
    return $freq;
  }

  /**
   * Get events from database as JSON
   *
   * @param  integer Start time events window
   * @param  integer End time events window
   * @param  string  Category overwrite
   * @param  bool    Editable
   * @param  array   External Urls
   * @return string  array events
   * @access public
   */
  public function arrayEvents($start, $end, $category=false, $edit=true, $links=false, $returndel=false, $events=false, $client=false) {
    $rcmail = $this->rcmail;
    $public_caldavs = $rcmail->config->get('public_caldavs', array());
    foreach($public_caldavs as $category => $caldav){
      $public_caldavs[$category]['pass'] = $rcmail->encrypt($caldav['pass']);
    }
    $protected = false;
    $read = false;
    $caldavs = array_merge($rcmail->config->get('caldavs', array()), $public_caldavs);
    if($url = $caldavs[$category]['url']){
      $url = explode('?', $url, 2);
      if($url[1]){
        $query = explode('=', $url[1]);
        if($query[0] == 'access'){
          $protected = true;
          if($query[1] == 2){
            $read = true;
          }
        }
      }
    }
    if(!is_array($events))
      $events = $this->backend->getEvents($start, $end, array(), $category, false, $client);
    $arr = array();
    foreach ($events as $key => $event) {
      if($event['del'] != 1 || $returndel){
        if(!$category)
          $className = $event['categories'];
        else
          $className = $category;
        if($edit)
          $editable = $event['editable'];
        else
          $editable = $edit;
        if($read)
          $editable = false;
        $onclick = '';
        if(is_array($links)){
          $onclick = $links[$event['uid']];
        }
        $event['editable'] = $editable;
        $event['categories'] = $className;
        $event['onclick'] = $onclick;
        $event['classProtected'] = $protected;
        $arr[] = $this->eventArrayMap($event,$category);
      }
    }
    return $arr;
  }

  /**
   * Get single event from database as an associative array
   *
   * @param  integer eventid
   * @return array Associative events array
   * @access public
   */
  public function arrayEvent($eventid) {
    $event = $this->backend->getEvent($eventid);
    $ret = array();
    if($event['del'] != 1){
      $ret = $event;
    }
    return $ret;
  }
  
  /**
   * Returns UNICODE type based on BOM (Byte Order Mark)
   *
   * @param string Input string to test
   * @return string Detected encoding
   */
  public function detect_encoding($string)
  {
    if (substr($string, 0, 4) == "\0\0\xFE\xFF") return 'UTF-32BE';  // Big Endian
    if (substr($string, 0, 4) == "\xFF\xFE\0\0") return 'UTF-32LE';  // Little Endian
    if (substr($string, 0, 2) == "\xFE\xFF")     return 'UTF-16BE';  // Big Endian
    if (substr($string, 0, 2) == "\xFF\xFE")     return 'UTF-16LE';  // Little Endian
    if (substr($string, 0, 3) == "\xEF\xBB\xBF") return 'UTF-8';

    // use mb_detect_encoding()
    $encodings = array('UTF-8', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3',
      'ISO-8859-4', 'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9',
      'ISO-8859-10', 'ISO-8859-13', 'ISO-8859-14', 'ISO-8859-15', 'ISO-8859-16',
      'WINDOWS-1252', 'WINDOWS-1251', 'BIG5', 'GB2312');

    if (function_exists('mb_detect_encoding') && ($enc = mb_detect_encoding($string, $encodings)))
      return $enc;

    // No match, check for UTF-8
    // from http://w3.org/International/questions/qa-forms-utf-8.html
    if (preg_match('/\A(
        [\x09\x0A\x0D\x20-\x7E]
        | [\xC2-\xDF][\x80-\xBF]
        | \xE0[\xA0-\xBF][\x80-\xBF]
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
        | \xED[\x80-\x9F][\x80-\xBF]
        | \xF0[\x90-\xBF][\x80-\xBF]{2}
        | [\xF1-\xF3][\x80-\xBF]{3}
        | \xF4[\x80-\x8F][\x80-\xBF]{2}
        )*\z/xs', substr($string, 0, 2048)))
      return 'UTF-8';
    return rcmail::get_instance()->config->get('default_charset', 'ISO-8859-1'); # fallback to Latin-1
  }
  
  /**
   * Returns font color
   *
   * @param string Input string (hex)
   * @return string Font color (hex)
   */
  public function getFontColor($color)
  {
    if($this->rcmail->config->get('default_font_color') == 'complementary'){
      $fontcolor = substr(dechex(~hexdec($color)),-6);
    }
    else{
      $r = substr($color,0,2);
      $g = substr($color,3,2);
      $b = substr($color,5,2);
      if ( $r <= "99" && $g <= "99" && $b <= "99"){
        $fontcolor='FFFFFF';
      }
      else {
        $fontcolor='000000';
      }
    }
    return $fontcolor;
  }
  
  /**
   * Returns a darker or lighter border color
   *
   * @param string Input string (hex)
   * @return string Border color (hex)
   */
  public function getBorderColor($hex,$factor=30) 
  {
    if($this->getFontColor($hex) < $hex){
      // darker border
      $new_hex = $this->darkerColor($hex, $factor);
    }
    else{
      // lighter border
      $new_hex = $this->lighterColor($hex, $factor);
    }
    return $new_hex;
  }
  
  /**
   * Returns a darker color
   *
   * @param string Input string (hex)
   * @return string Border color (hex)
   */
  public function darkerColor($hex, $factor=30)
  {
    $new_hex = '';
    $base['R'] = hexdec($hex{0}.$hex{1}); 
    $base['G'] = hexdec($hex{2}.$hex{3}); 
    $base['B'] = hexdec($hex{4}.$hex{5}); 
    foreach ($base as $k => $v)
    {
      $amount = $v / 100;
      $amount = round($amount * $factor);
      $new_decimal = $v - $amount;
      
      $new_hex_component = dechex($new_decimal);
      if(strlen($new_hex_component) < 2)
      {
        $new_hex_component = "0".$new_hex_component;
      }
      $new_hex .= $new_hex_component;
    }
    return $new_hex;
  }
  
  /**
   * Returns a lighter color
   *
   * @param string Input string (hex)
   * @return string Border color (hex)
   */
  public function lighterColor($hex, $factor=30)
  {
    $new_hex = '';
    $base['R'] = hexdec($hex{0}.$hex{1}); 
    $base['G'] = hexdec($hex{2}.$hex{3}); 
    $base['B'] = hexdec($hex{4}.$hex{5}); 
    foreach ($base as $k => $v) 
    { 
      $amount = 255 - $v; 
      $amount = $amount / 100; 
      $amount = round($amount * $factor); 
      $new_decimal = $v + $amount; 
    
      $new_hex_component = dechex($new_decimal); 
      if(strlen($new_hex_component) < 2) 
      {
        $new_hex_component = "0".$new_hex_component;
      }
      $new_hex .= $new_hex_component; 
    }
    return $new_hex;
  }
}
?>