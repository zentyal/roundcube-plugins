<?php

require_once(INSTALL_PATH . 'plugins/calendar/program/backend/backend.php');
require_once(INSTALL_PATH . 'plugins/calendar/program/backend/caldav/caldav-client.php');

final class calendar_caldav extends Backend 
{
  private $rcmail;
  private $type;
  public  $utils;
  private $usertimezone;
  private $username;
  private $caldav;
  private $url = null;
  public $caldavs = array();
  private $account;
  private $_rule;
  private $_part;
  public $sync = true;
  
  public function __construct($rcmail, $type) {
    $this->rcmail = $rcmail;
    if(!class_exists('calendar_plus')){
      $type = 'database';
    }
    $this->type = $type;
    if ($rcmail->config->get('timezone') === "auto") {
      $tz = isset($_SESSION['timezone']) ? $_SESSION['timezone'] : date('Z')/3600;
    }
    else {
      $tz = $rcmail->config->get('timezone');
    }
    $this->usertimezone = $tz;

    $url = $this->rcmail->config->get('caldav_url', false);
    if(!$url || $type != 'caldav')
      return;

    $user = $this->rcmail->config->get('caldav_user','demo');
    $pass = $this->rcmail->config->get('caldav_password',$this->rcmail->encrypt('pass'));
    $auth = $this->rcmail->config->get('caldav_auth','basic');
    $extr = $this->rcmail->config->get('caldav_extr','1');
    $account = array(
      'user' => $user,
      'pass' => $pass,
      'url'  => unslashify($url),
      'auth' => $auth,
      'extr' => $extr,
    );
    $this->account = $account;
    $lastdetection = time() - $rcmail->config->get('collections_sync', 0);
    if(is_array($_SESSION['detected_caldavs']) || $lastdetection < $rcmail->config->get('sync_collections', 0)){
      $this->connect($account['url'], $account['user'], $account['pass'], $account['auth']);
      $public_caldavs = $rcmail->config->get('public_caldavs', array());
      foreach($public_caldavs as $category => $caldav){
        $public_caldavs[$category]['pass'] = $rcmail->encrypt($caldav['pass']);
      }
      $caldavs = array_merge($rcmail->config->get('caldavs', array()), $public_caldavs);
      $this->caldavs = $caldavs;
      return;
    }
    $parsed = parse_url($account['url']);
    $home = $parsed['scheme'] . '://' . $parsed['host'];
    $account_url = unslashify(urldecode($this->account['url']));
    list($u, $d) = explode('@', $rcmail->user->data['username']);
    $account_url = str_replace('%su', $u, $account_url);
    $account_url = str_replace('%u', $rcmail->user->data['username'], $account_url);
    $googleuser = $rcmail->config->get('googleuser', 'john.doh@gmail.com');
    $account_url = str_replace('%gu', $googleuser, $account_url);
    $principal = $rcmail->config->get('caldav_principals', '/principals/users/%u');
    $principal = str_replace('%su', $u, $principal);
    $principal = str_replace('%u', $rcmail->user->data['username'], $principal);
    $principal = str_replace('%gu', $googleuser, $principal);
    $home = $rcmail->config->get('caldav_home', $home);
    $this->connect($home . $principal, $account['user'], $account['pass'], $account['auth']);
    $calendars = $this->caldav->GetCollection();
    if(!$calendars){
      $this->connect($home, $account['user'], $account['pass'], $account['auth']);
      $calendars = $this->caldav->GetCollection();
    }
    $caldavs = array();
    $colors =array();
    $deleted = $rcmail->config->get('caldavs_removed', array());
    if(is_array($calendars)){
      foreach($calendars as $key => $calendar){
        $calendar['url'] = strtolower(unslashify($home . $calendar['url']));
        if(isset($deleted[$calendar['url']])){
          continue;
        }
        $comp1 = str_replace($home, '', $calendar['url']);
        $comp2 = str_replace($home, '', $account_url);
        $comp1 = explode('/', $comp1);
        $comp2 = explode('/', $comp2);
        if(isset($comp1[4])){
          unset($comp1[2]);
          unset($comp1[3]);
          unset($comp2[2]);
          unset($comp2[3]);
        }
        $comp1 = implode('/', $comp1);
        $comp2 = implode('/', $comp2);
        $hidden = $rcmail->config->get('caldav_hidden_collections', array());
        if(in_array($comp1, $hidden)){
          continue;
        }
        if($calendar['url'] != $account_url && $comp1 != $comp2){
          if($calendar['displayname']){
            $category = $calendar['displayname'];
          }
          else if($calendars[$key]){
            $temp = explode('/', $calendars[$key]);
            $category = ucwords($temp[count($temp) - 1]);
          }
          if($calendar['color']){
            $colors[$category] = substr($calendar['color'], 1);
          }
          $caldavs[$category] = array(
            'user' => $this->account['user'],
            'pass' => $this->account['pass'],
            'url'  => $calendar['url'],
            'auth' => $this->account['auth'],
            'extr' => $this->account['extr'],
          );
        }
      }
    }
    $_SESSION['detected_caldavs'] = $caldavs;
    $detected_caldavs = $caldavs;
    $conf = $rcmail->config->get('caldavs', array());
    foreach($conf as $key1 => $caldav1){
      foreach($caldavs as $key2 => $caldav2){
        $caldav1['url'] = str_replace('%u', $rcmail->user->data['username'], $caldav1['url']);
        $caldav2['url'] = str_replace('%u', $rcmail->user->data['username'], $caldav2['url']);
        if($caldav1['url'] == $caldav2['url']){
          if($key1 != $key2){
            $sql = 'UPDATE ' . $this->table('events') . ' SET ' . $this->q('categories'). '=? WHERE ' . $this->q('user_id') . '=? AND ' . $this->q('url') . '=?';
            $rcmail->db->query($sql, $key2, $rcmail->user->ID, $caldav2['url']);
          }
          unset($conf[$key1]);
        }
      }
    }
    $caldavs = array_merge($conf, $caldavs);
    if($_SESSION['user_id']){
      $categories = array_merge($rcmail->config->get('categories', array()), $colors);
      $rcmail->user->save_prefs(array('caldavs' => $caldavs, 'categories' => $categories, 'detected_caldavs' => $detected_caldavs, 'collections_sync' => time()));
    }
    $public_caldavs = $rcmail->config->get('public_caldavs', array());
    foreach($public_caldavs as $category => $caldav){
      $public_caldavs[$category]['pass'] = $rcmail->encrypt($caldav['pass']);
    }
    $this->caldavs = array_merge($caldavs, $public_caldavs);
    $this->connect($account['url'], $account['user'], $account['pass'], $account['auth']);
  }
  
  private function connect($url, $user, $pass, $auth = 'basic', $depth = true){
    if($this->type == 'caldav'){
      $this->url = $url;
      $rcmail = $this->rcmail;
      $googleuser = $rcmail->config->get('googleuser', false);
      $googlepass= $rcmail->config->get('googlepass', false);
      if($user == '%gu'){
        if($googleuser){
          $user = str_replace('%gu', $googleuser, $user);
        }
      }
      else if($user == '%su'){
        list($u, $d) = explode('@', $_SESSION['username']);
        $user = str_replace('%su', $u, $user);
      }
      else if($user == '%u'){
        $user = str_replace('%u', $_SESSION['username'], $user);
      }
      $pass_clear = false;
      if($pass == '%gp'){
        if($googlepass){
          $pass = str_replace('%gp', $rcmail->decrypt($googlepass), $pass);
          $pass_clear = true;
        }
      }
      else if($pass == '%p'){
        $pass = str_replace('%p', $rcmail->decrypt($_SESSION['default_account_password'] ? $_SESSION['default_account_password'] : $_SESSION['password']), $pass);
        $pass_clear = true;
      }
      if(!$pass_clear){
        $pass = $this->rcmail->decrypt($pass);
        if($pass == '%gp'){
          if($googlepass){
            $pass = str_replace('%gp', $rcmail->decrypt($googlepass), $pass);
          }
        }
        else if($pass == '%p'){
          $pass = str_replace('%p', $rcmail->decrypt($_SESSION['default_account_password'] ? $_SESSION['default_account_password'] : $_SESSION['password']), $pass);
        }
        if($user == '%su'){
          list($u, $d) = explode('@', $_SESSION['username']);
          $user = str_replace('%su', $u, $user);
        }
      }
      if(strpos($url,'%gu'))
        $url = str_replace('%gu', $googleuser, $url);
      if(strpos($url,'%u'))
        $url = str_replace('%u', $_SESSION['username'], $url);
      if(strpos($url,'%su')){
        list($u, $d) = explode('@', $_SESSION['username']);
        $url = str_replace('%su', $u, $url);
      }
      if(strpos($url, '?') === false)
        $url = slashify($url);
      if($url == "/"){
        $url = 'https://www.google.com/calendar/dav/john.doe@gmail.com/events/';
      }
      if(!$auth)
        $auth = 'detect';
      if($user != '%u' && $user != '%gu' && $user != '%su' && $pass !='%p' && $pass != '%gp' && strpos($url, '%u') === false && strpos($url, '%gu') === false)
        $ret = $this->caldav = new CalDAVClient(trim($url), trim($user), trim($pass), trim($auth), $rcmail->config->get('caldav_debug', false));
      if(!$ret){
        if(!$user)
          write_log('calendar',"CalDAV Invalid URL: $user - $url");
        $this->caldav = new CalDAVClient('', '', '', '');
      }
      if($depth){
        $this->caldav->SetDepth(1);
      }
    }
  }
  
  private function q($str) {
    return $this->rcmail->db->quoteIdentifier($str);
  }
  
  private function table($str) {
    return $this->rcmail->db->quoteIdentifier(get_table_name($str));
  }
  
  private function generateId(){
    $letters="abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    for($i = 0; $i<=6; $i++){
      $rndStr .= $letters[(rand() % strlen($letters))];
    }
    $icalStamp = date("Ymd\THis\Z");
    return $icalStamp."-".$rndStr;
  }

  private function syncCalDAV($events,$request='PUT',$categories=false){
    if(!$this->sync){
      return true;
    }
    $return = false;
    if($this->type == 'caldav'){
      $event = $this->utils->exportEvents(0,0,$events);
      $caldav_props = unserialize($events[0]['caldav']);
      $depth = true;
      if($request == 'DELETE'){
        $depth = false;
      }
      $this->connect($this->account['url'], $this->account['user'], $this->account['pass'], $this->account['auth'], $depth);
      if($categories){
        $caldavs = $this->caldavs;
        if(!empty($caldavs[$categories])){
          $url = $caldavs[$categories]['url'];
          $url = explode('?', $url, 2);
          if($url[1]){
            $url = explode('=', $url[1]);
            if($url[0] == 'access'){
              $url = explode('?', $caldavs[$categories]['url'], 2);
              $url = explode('/', $url[0]);
              $oldcategory = ucwords($url[count($url) - 1]);
              $event = str_replace("\nCATEGORIES:", "\nCATEGORIES:$oldcategory\nX-CATEGORIES:", $event);
            }
          }
          $this->connect($caldavs[$categories]['url'],$caldavs[$categories]['user'],$caldavs[$categories]['pass'],$caldavs[$categories]['auth']);
        }
      }
      if($request == 'DELETE'){
        $code = $this->caldav->DoDELETERequest($caldav_props[0]);
        if(substr($code, 0, 1) != 2){
          return false;
        }
        else{
          return true;
        }
      }
      else if($request == 'PUT'){
        if(!$caldav_props[1]){
          $caldav_props[0] = str_ireplace('.ics', '', $caldav_props[2]) . '.ics';
        }
        $ret = $this->caldav->DoPUTRequest($caldav_props[0], $event, $caldav_props[1]);
        if($ret){
          if(stripos($ret, 'HTTP/1.1') !== false){
            $code = $this->caldav->resultcode;
            if($code == 403 || $code == 404 || $code == 412){
              return false;
            }
            if($code == 201 || $code == 204 || $caldav_props[2] == '*'){
              $ret = $this->caldav->GetEntryByHref($caldav_props[0]);
              $count = preg_match('/Etag:(.*?)\n/i',$ret,$temp);
              if($count > 0){
                $etag = trim(str_replace('"','',$temp[1]));
                $caldav_props = array($caldav_props[0], $etag, $caldav_props[2]);
              }
            }
          }
          else{
            $caldav_props = array($caldav_props[0], $ret, $caldav_props[2]);
          }
          if(is_array($caldav_props)){
            $query = $this->rcmail->db->query(
              "UPDATE " . $this->table('events') . " 
              SET ".$this->q('caldav')."=?
              WHERE ".$this->q('uid')."=?
              AND ".$this->q('user_id')."=?",
              serialize($caldav_props),
              $events[0]['uid'],
              $this->rcmail->user->ID
            );
            $return = true;
          }
        }
      }
    }
    else{
      $return = true;
    }
    return $return;
  }
  
  private function getCtag(){
    $return = false;
    if($this->type == 'caldav'){
      $xmlC = <<<PROPP
<?xml version="1.0" encoding="utf-8" ?>
 <D:propfind xmlns:D="DAV:" xmlns:C="http://calendarserver.org/ns/">
     <D:prop>
             <D:displayname />
             <C:getctag />
             <D:resourcetype />
     </D:prop>
 </D:propfind>
PROPP;
      $this->caldav->SetDepth(1);
      $folder_xml = $this->caldav->DoXMLRequest("PROPFIND", $xmlC);
      $xml = false;
      $temparr = explode("\r\n\r\n", $folder_xml);
      foreach($temparr as $idx => $part){
        $xml = false;
        if(strtolower(substr($part, 0, 5)) == '<?xml'){
          $xml = $temparr[$idx];
          break;
        }
      }
      if(!$xml){
        $return = false;
      }
      $p = xml_parser_create();
      xml_parse_into_struct($p, $xml, $vals, $index);
      // sabredav
      if($ctag = $vals[$index['CS:GETCTAG'][0]]['value']){
        $return = $ctag;
      }
      // davical
      else if($ctag = $vals[$index['C:GETCTAG'][0]]['value']){
        $return = $ctag;
      }
      else{
        $ctag = false;
        foreach($vals as $val){
          if($val['tag'] == 'NS2:GETCTAG' && $val['value']){
            $ctag = $val['value'];
            break;
          }
        }
        $return = $ctag;
      }
    }
    else{
      $return = false;
    }
    if($return){
      return $return;
    }
    else{
      return false;
    }
  }
  
  public function getCtags() {
    $ctags = array();
    if($this->type == 'caldav'){
      $this->connect($this->account['url'], $this->account['user'], $this->account['pass'], $this->account['auth']);
      $ctags[md5($this->account['url'])] = $this->getCtag();
      $caldavs = $this->caldavs;
      foreach($caldavs as $key => $caldav){
        $this->connect($caldav['url'],$caldav['user'],$caldav['pass'],$caldav['auth']);
        if($ctag = $this->getCtag()){
          $ctags[md5($caldav['url'])] = $ctag;
        }
        else{
          $ctags[md5($caldav['url'])] = false;
        }
      }
      $this->connect($this->account['url'], $this->account['user'], $this->account['pass'], $this->account['auth']);
    }
    return $ctags;
  }
  
  public function newCalendar($account, $displayname, $color) {
    if($this->type == 'caldav'){
      $account['pass'] = $this->rcmail->decrypt($account['pass']);
      if($account['pass'] == 'SESSION'){
        $account['pass'] = $_SESSION['default_account_password'] ? $_SESSION['default_account_password'] : $_SESSION['password'];
      }
      else{
        $account['pass'] = $this->rcmail->encrypt($account['pass']);
      }
      $this->connect($account['url'], $account['user'], $account['pass'], $account['auth'], false);
      $xml = '<?xml version="1.0" encoding="utf-8" ?>
<propfind xmlns="DAV:">
  <prop>
    <resourcetype/>
  </prop>
</propfind>';
      $this->caldav->SetDepth(0);
      $rsp = $this->caldav->DoXMLRequest("PROPFIND", $xml);
      $temp = explode('http/1.1 ', strtolower($rsp));
      foreach($temp as $idx => $code){
        $code = substr($code, 0, 3);
        if(!is_numeric($code)){
          $code = '403';
        }
      }
      if(substr($code, 0, 1) == 2){
        return true;
      }
      $xml = '<?xml version="1.0" encoding="utf-8" ?>
<D:mkcol xmlns:D="DAV:"xmlns:C="urn:ietf:params:xml:ns:caldav">
  <D:set>
    <D:prop>
      <D:resourcetype>
        <D:collection/> 
        <C:calendar/>
      </D:resourcetype>
      <D:displayname>' . $displayname . '</D:displayname>
    </D:prop>
  </D:set>
</D:mkcol>';
      $this->caldav->SetDepth(1);
      $rsp = $this->caldav->DoXMLRequest("MKCOL", $xml);
      $this->connect($this->account['url'], $this->account['user'], $this->account['pass'], $this->account['auth'], true);
      $temp = explode('http/1.1 ', strtolower($rsp));
      foreach($temp as $idx => $code){
        $code = substr($code, 0, 3);
        if(!is_numeric($code)){
          $code = '403';
        }
      }
      if(substr($code, 0, 1) != 2){
        return false;
      }
      else{
        return true;
      }
    }
  }
  
  public function removeCalendar($account) {
    if($this->type == 'caldav'){
      $account['pass'] = $this->rcmail->decrypt($account['pass']);
      if($account['pass'] == 'SESSION' || !$account['pass']){
        $account['pass'] = $_SESSION['default_account_password'] ? $_SESSION['default_account_password'] : $_SESSION['password'];
      }
      else{
        $account['pass'] = $this->rcmail->encrypt($account['pass']);
      }
      $this->connect($account['url'], $account['user'], $account['pass'], $account['auth'], false);
      $code = $this->caldav->DoDELETERequest('');
      $this->connect($this->account['url'], $this->account['user'], $this->account['pass'], $this->account['auth'], true);
      if(substr($code, 0, 1) != 2){
        return false;
      }
      else{
        return true;
      }
    }
  }
  
  public function searchEvents($str, $label) {
    if(!empty($this->rcmail->user->ID)) {
      $cal_searchset = $this->rcmail->config->get('cal_searchset', array('summary'));
      $str = str_replace(array('\\'),array(''),$str);
      $str = str_replace('%','\%',$str);
      $str = str_replace('*','%',$str);
      $method = 'LIKE';
      $wildcard = '%';
      if(strlen($str) > 2){
        $sql_filter = " AND (" . $this->rcmail->db->ilike($cal_searchset[0], $wildcard.$str.$wildcard);
        if(count($cal_searchset) > 1){
          for($i=1;$i<count($cal_searchset);$i++){
            if($cal_searchset[$i] != 'all_day'){
              $sql_filter .= " OR " . $this->rcmail->db->ilike($cal_searchset[$i], $wildcard.$str.$wildcard);
              if($cal_searchset[$i] == 'categories' && stripos($this->rcmail->config->get('default_category_label', $label), $str) !== false){
                $sql_filter .= " OR " . $this->q('categories') . "=''";
              }
            }
          }
          $sql_filter .= ")";
        }
        $sql_result = $this->rcmail->db->query(
          "SELECT * FROM ".$this->table('events').
          " WHERE " . $this->q('del') . "<>1".
          " AND " . $this->q('user_id') . "=?".
          $sql_filter.
          " ORDER BY " . $this->q('summary'),
          $this->rcmail->user->ID);
        while ($sql_result && ($sql_arr = $this->rcmail->db->fetch_assoc($sql_result))) {
          $key = $sql_arr;
          unset($key['event_id']);
          $key = md5(serialize($key));
          $results[$key] = $sql_arr;
        }
        $events_table = $this->rcmail->config->get('db_table_events', 'events');
        $this->rcmail->config->set('db_table_events',$this->rcmail->config->get('db_table_events_cache', 'events_cache'));
        $sql_result = $this->rcmail->db->query(
          "SELECT * FROM ".$this->table('events').
          " WHERE " . $this->q('del') . "<>1".
          " AND " . $this->q('user_id') . "=?".
          $sql_filter.
          " ORDER BY " . $this->q('summary'),
          $this->rcmail->user->ID);
        while ($sql_result && ($sql_arr = $this->rcmail->db->fetch_assoc($sql_result))) {
          $key = $sql_arr;
          unset($key['event_id']);
          $key = md5(serialize($key));
          $results[$key] = $sql_arr;
        }
        $feeds = (array)$this->rcmail->config->get('feeds_subscribed',array());
        foreach($feeds as $url => $category){
          $arr = parse_url($url);
          if($arr['path'] == './'){
            if($_SERVER['HTTPS'])
              $https = 's';
            else
              $https = '';
            $url = 'http' . $https . "://" . $_SERVER['HTTP_HOST'] . ':' . $_SERVER['SERVER_PORT'] . substr($url,1);
            $arr = parse_url($url);
          }
          $con = '?';
          if(strstr($url,'?'))
            $con = '&';
          if(stripos($arr['query'],'plugin.calendar_showlayer') && strtolower($arr['host']) == strtolower($_SERVER['HTTP_HOST'])){
            $user_id = $this->rcmail->user->ID;
            $temparr = explode('&',$arr['query']);
            foreach($temparr as $key => $val){
              if(strpos($val,'_userid=') === 0){
                $temp = explode("=",$val);
                $remote_user = $temp[1];
                $this->rcmail->user->ID = $remote_user;
                $sql_result = $this->rcmail->db->query(
                  "SELECT * FROM ".$this->table('events').
                  " WHERE " . $this->q('del') . "<>1".
                  " AND " . $this->q('user_id') . "=?".
                  $sql_filter.
                  " ORDER BY " . $this->q('summary'),
                  $this->rcmail->user->ID);
                while ($sql_result && ($sql_arr = $this->rcmail->db->fetch_assoc($sql_result))) {
                  $key = $sql_arr;
                  unset($key['event_id']);
                  $key = md5(serialize($key));
                  $results[$key] = $sql_arr;
                }
                $this->rcmail->user->ID = $user_id;
                break;
              }
            }
          }
          $this->rcmail->config->set('db_table_events', $events_table);
          $arr = $this->utils->getUser($remote_user);
          $prefs = unserialize($arr['preferences']);
          $events_table = $this->rcmail->config->get('db_table_events', 'events');
          $db_table = str_replace('_caldav','',$events_table);
          $default = array(
            'database' => '', // default db table
            'caldav' => '_caldav', // caldav db table (= default db table) extended by _caldav
          );
          $map = $this->rcmail->config->get('backend_db_table_map', $default);
          if($prefs['backend'] == 'caldav'){
            $db_table .= $map['caldav'];
          }
          else{
            $db_table .= $map['database'];
          }
          foreach($feeds as $url => $category){
            $arr = parse_url($url);
            $con = '?';
            if(strstr($url,'?'))
              $con = '&';
            if(stripos($arr['query'],'plugin.calendar_showlayer') && strtolower($arr['host']) == strtolower($_SERVER['HTTP_HOST'])){
              $user_id = $this->rcmail->user->ID;
              $temparr = explode('&',$arr['query']);
              foreach($temparr as $key => $val){
                if(strpos($val,'_userid=') === 0){
                  $temp = explode("=",$val);
                  $remote_user = $temp[1];
                  $this->rcmail->user->ID = $remote_user;
                  $sql_result = $this->rcmail->db->query(
                    "SELECT * FROM ".$db_table.
                    " WHERE " . $this->q('del') . "<>1".
                    " AND " . $this->q('user_id') . "=?".
                    $sql_filter.
                    " ORDER BY " . $this->q('summary'),
                    $this->rcmail->user->ID);
                  while ($sql_result && ($sql_arr = $this->rcmail->db->fetch_assoc($sql_result))) {
                    $key = $sql_arr;
                    unset($key['event_id']);
                    $key = md5(serialize($key));
                    $results[$key] = $sql_arr;
                  }
                  $this->rcmail->user->ID = $user_id;
                  break;
                }
              }
            }
          }
          $this->rcmail->config->set('db_table_events', $events_table);
        }
      }
    }
    if(is_array($results)){
      for($i=1;$i<count($cal_searchset);$i++){
        if($cal_searchset[$i] == 'all_day'){
          $ret = array();
          foreach($results as $result){
            if(!$result['end']){
              $ret[] = $result;
            }
            else if($result['end'] - $result['start'] >= 86340){
              $ret[] = $result;
            }
          }
          $results = $ret;
          break;
        }
      }
      $ret = $results;
    }
    else
      $ret = false;
    return $ret;
  }
  
  public function scheduleReminders($event){
    if (!empty($this->rcmail->user->ID) && $event) {
      $default = array(
        'database' => '', // default db table
        'caldav' => '_caldav', // caldav db table (= default db table) extended by _caldav
      );
      $map = $this->rcmail->config->get('backend_db_table_map', $default);
      if(stripos($this->table('events'),$map['caldav'])){
        $col = 'caldav';
      }
      else{
        if($this->rcmail->config->get('db_table_events_cache') == get_table_name('events')){
          $col = 'cache';
        }
        else{
          $col = 'events';
        }
      }
      $query = $this->rcmail->db->query(
        "DELETE FROM " . $this->table('reminders') . "
        WHERE ".$this->q($col)."=? AND ".$this->q('user_id')."=?",
        $event['event_id'],
        $this->rcmail->user->ID
      );
      if($event['del'] == '1'){
        return;
      }
      if($event['recurring'] != 0){
        $events = $this->getEvents(
          time() - 86400,
          time() + 86400,
          array(),
          false,
          array('uid',$event['uid'])
        );
      }
      else{
        $events[0] = $event;
      }
      if(is_array($events)){
        $start = time();
        $props = array();
        $props['user_id'] = $this->rcmail->user->ID;
        if($_SESSION['username'])
          $props['username'] = $_SESSION['username'];
        else{
          $user = $this->utils->getUser($props['user_id']);
          $props['username'] = $user['username'];
        }
        $props['user_data'] = $this->rcmail->user->data;
        $props['lang'] = $this->rcmail->user->language;
        foreach($events as $key => $event){
          if($event['reminderservice'] != '0'){
            $next = $event['start'] - $event['reminder'];
            $schedule = false;
            if($event['reminderservice'] == 'popup'){
              //if(!$event['remindersent'])
                //$schedule = true; // schedule missed reminders
              if($next > $start)
                $schedule = true; // always schedule future reminders
            }
            else if($event['reminderservice'] == 'email'){
              if($next > $start && $event['clone'])
                $schedule = true; // schedule future reminders for recurring events
              if(!$event['remindersent'] && !$event['clone'] && $next > $start)
                $schedule = true; // schedule future reminders for single events
              if(!empty($this->caldavs[$event['categories']])){
                if($this->caldavs[$event['categories']]['extr'] == 'external')
                  $schedule = false;
              }
              else{
                if($this->account['extr'] == 'external')
                  $schedule = false;
              }
            }
            if($schedule){
              $mapped = $this->utils->eventArrayMap($event);
              $props['event'] = $mapped;
              $props['ics'] = $this->utils->exportEvents(0,0,array(0=>$event), false, true);
              $query = $this->rcmail->db->query(
                "INSERT INTO " . $this->table('reminders') . "
                (".
                  $this->q('user_id').", ".
                  $this->q($col).", ".
                  $this->q('type').", ".
                  $this->q('props').", ".
                  $this->q('runtime').")
                VALUES (?, ?, ?, ?, ?)",
                $this->rcmail->user->ID,
                $event['event_id'],
                $event['reminderservice'],
                serialize($props),
                $next
              );
              if($event['reminderservice'] == 'email'){
                break;
              }
            }
          }
        }
      }
    }
  }

  public function newEvent(
    $start,
    $end,
    $summary,
    $description,
    $location,
    $categories,
    $allDay,
    $recur,
    $expires,
    $occurrences,
    $byday=false,
    $bymonth=false,
    $bymonthday=false,
    $recurrence_id=false,
    $exdates=false,
    $reminderbefore=false,
    $remindertype=false,
    $remindermailto=false,
    $uid=false,
    $client=false,
    $adjust = true
  ) {
    if (!empty($this->rcmail->user->ID)) {
      $srecur = (string) $recur;
      $description = addcslashes($description, "\n\r"); 
      $rr = substr($recur,0,1);
      $recur = substr($recur,1);
      // PostgreSQL sets 'f' instead of '0' for false, which messes up our conditionals!
      if ($byday==false) $byday='0';
      if ($bymonth==false) $bymonth='0';
      if ($bymonthday==false) $bymonthday='0';
      if ($recurrence_id==false) $recurrence_id='0';
      if ($exdates==false) $exdates='0';
      if ($recur==false) $recur='0';
      if ($reminderbefore==false) $reminderbefore='0';
      if ($remindertype==false) $remindertype='0';
      if ($remindermailto==false) $remindermailto='0';
      if ($uid==false) $uid='0';
      if ($client==false) $client='0';
      //
      if($this->type == 'caldav'){
        if(is_array($this->caldavs[$categories])){
          $this->url = $this->caldavs[$categories]['url'];
        }
        else{
          $this->url = $this->rcmail->config->get('caldav_url');
        }
      }
      if(is_array($uid)){
        $etag = $uid['etag'] ? $uid['etag'] : false;
        $href = $uid['href'] ? $uid['href'] : $uid['uid'];
        $uid = $uid['uid'] ? $uid['uid'] : '*';
      }
      else{
        $etag = false;
        $href = $uid;
      }
      if(!$uid){
        $uid = $this->generateId();
      }
      if($adjust){
        $offset = $this->offset($start);
        $start = $start + $offset;
        $offset = $this->offset($end);
        $end = $end + $offset;
        $offset = $this->offset($expires);
        $expires = $expires + $offset;
      }
      $exists = $this->getEventByUID($uid, $recurrence_id);
      if(is_array($exists)){
        if($exists['del'] != '0'){
          $query = $this->rcmail->db->query(
            "UPDATE " . $this->table('events') . " 
              SET ".$this->q('del')."=?
              WHERE ".$this->q('event_id')."=?
              AND ".$this->q('user_id')."=?",
            0,
            $exists['event_id'],
            $this->rcmail->user->ID
          );
        }
        if(
          $start != $exists['start'] ||
          $end != $exists['end'] ||
          $summary != $exists['summary'] ||
          $description != $exists['description'] ||
          $location != $exists['location'] ||
          $categories != $exists['categories'] ||
          $recurrence_id != $exists['recurrence_id'] ||
          $exdates != $exists['exdates'] ||
          $reminderbefore != $exists['reminder'] ||
          $remindertype != $exists['reminderservice'] ||
          $remindermailto != $exists['remindermailto'] ||
          $srecur != $exists['rr'] . $exists['recurring'] ||
          $expires != $exists['expires'] ||
          $occurences != $exists['occurences'] ||
          $byday != $exists['byday'] ||
          $bymonth != $exists['bymonth'] ||
          $bymonthday != $exists['bymonthday']
        ){
          $ret = $this->editEvent(
            $exists['event_id'],
            $start,
            $end,
            $summary,
            $description,
            $location,
            $categories,
            $srecur,
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
            $allDay,
            false,
            serialize(array(0 => $href, 1 => $etag, 2 => $uid)),
            $adjust
          );
        }
        else{
          $ret = $exists;
        }
        $ret['edit'] = 1;
        return $ret;
      }
      $query = $this->rcmail->db->query(
        "INSERT INTO " . $this->table('events') . "
         (".
           $this->q('user_id').", ".
           $this->q('start').", ".
           $this->q('end').", ".
           $this->q('summary').", ".
           $this->q('description').", ".
           $this->q('location').", ".
           $this->q('categories').", ".
           $this->q('recurring').", ".
           $this->q('rr').", ".
           $this->q('expires').", ".
           $this->q('occurrences').", ".
           $this->q('byday').", ".
           $this->q('bymonth').", ".
           $this->q('bymonthday').", ".
           $this->q('reminder').", ".
           $this->q('reminderservice').", ".
           $this->q('remindermailto').", ".
           $this->q('remindersent').", ".
           $this->q('recurrence_id').", ".
           $this->q('exdates').", ".
           $this->q('uid').", ".
           $this->q('client').", ".
           $this->q('caldav').", ".
           $this->q('url').", ".
           $this->q('timestamp').")
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        $this->rcmail->user->ID,
        $start,
        $end,
        $summary,
        $description,
        $location,
        $categories,
        $recur,
        $rr,
        $expires,
        $occurrences,
        $byday,
        $bymonth,
        $bymonthday,
        $reminderbefore,
        $remindertype,
        $remindermailto,
        0,
        $recurrence_id,
        $exdates,
        $uid,
        $client,
        serialize(array(0 => $href, 1 => $etag, 2 => $uid)),
        $this->url,
        date('Y-m-d H:i:s', time())
      );
      $events = $this->getEventsByUID($uid);
      //find me: investigate why this is here ...
      if(
        $this->rcmail->action == 'plugin.newEvent' ||
        $this->rcmail->action == 'plugin.editEvent' ||
        $this->rcmail->action == 'plugin.saveical' ||
        $this->rcmail->action == 'plugin.calendar_upload' ||
        $this->rcmail->action == 'plugin.moveEvent'||
        $this->rcmail->action == 'plugin.resizeEvent' ||
        $this->rcmail->action == 'plugin.calendar_showlayer'
      ){
        $events[0]['sync'] = $this->syncCalDAV($events,'PUT',$categories);
      }
      $this->scheduleReminders($events[0]);
      return $events[0];
    }
  }

  public function editEvent(
    $id,
    $start,
    $end,
    $summary,
    $description,
    $location,
    $categories,
    $recur,
    $expires,
    $occurrences,
    $byday=false,
    $bymonth=false,
    $bymonthday=false,
    $recurrence_id=false,
    $exdates=false,
    $reminderbefore=false,
    $remindertype=false,
    $remindermailto=false,
    $allDay=false,
    $old_categories=false,
    $caldav=false,
    $adjust = true
  ) {
    if (!empty($this->rcmail->user->ID)) {
      $srecur = $recur;
      $rr = substr($recur,0,1);
      $recur = substr($recur,1);
      $description = addcslashes($description, "\n\r");
      // PostgreSQL sets 'f' instead of '0' for false, which messes up our conditionals! 
      if ($byday==false) $byday='0';
      if ($bymonth==false) $bymonth='0';
      if ($bymonthday==false) $bymonthday='0';
      if ($recurrence_id==false) $recurrence_id='0';
      if ($exdates==false) $exdates='0';
      if ($reminderbefore==false) $reminderbefore='0';
      if ($remindertype==false) $remindertype='0';
      if ($remindermailto==false) $remindermailto='0';
      if ($allday==false) $allday='0';
      if ($recurrence_id==false) $recurrence_id='0';
      if ($recur==false) $recur='0';
      if ($uid==false) $uid='0';
      if ($client==false) $client='0';
      //
      if($this->type == 'caldav'){
        if(is_array($this->caldavs[$categories])){
          $this->url = $this->caldavs[$categories]['url'];
        }
        else{
          $this->url = $this->rcmail->config->get('caldav_url');
        }
      }
      if($adjust){
        $offset = $this->offset($start);
        $start = $start + $offset;
        $offset = $this->offset($end);
        $end = $end + $offset;
        $offset = $this->offset($expires);
        $expires = $expires + $offset;
      }
      $event = $this->getEvent($id);
      if(!$caldav){
        $caldav = $event['caldav'];
      }
      $query = $this->rcmail->db->query(
        "UPDATE " . $this->table('events') . " 
         SET ".
           $this->q('summary')."=?, ".
           $this->q('start')."=?, ".
           $this->q('end')."=?, ".
           $this->q('description')."=?, ".
           $this->q('location')."=?, ".
           $this->q('categories')."=?, ".
           $this->q('rr')."=?, ".
           $this->q('recurring')."=?, ".
           $this->q('expires')."=?, ".
           $this->q('occurrences')."=?, ".
           $this->q('byday')."=?, ".
           $this->q('bymonth')."=?, ".
           $this->q('bymonthday')."=?, ".
           $this->q('recurrence_id')."=?, ".
           $this->q('exdates')."=?, ".
           $this->q('url')."=?, ".
           $this->q('reminder')."=?, ".
           $this->q('reminderservice')."=?, ".
           $this->q('remindermailto')."=?, ".
           $this->q('remindersent')."=?, ".
           $this->q('timestamp')."=?, ".
           $this->q('del')."=?, ".
           $this->q('notified')."=?, ".
           $this->q('caldav')."=?
         WHERE ".$this->q('event_id')."=?
         AND ".$this->q('user_id')."=?",
        $summary,
        $start,
        $end,
        $description,
        $location,
        $categories,
        $rr,
        $recur,
        $expires,
        $occurrences,
        $byday,
        $bymonth,
        $bymonthday,
        $recurrence_id,
        $exdates,
        $this->url,
        $reminderbefore,
        $remindertype,
        $remindermailto,
        0,
        date('Y-m-d H:i:s', time()),
        0,
        0,
        $caldav,
        $id,
        $this->rcmail->user->ID
      );
      // find me: get uid from GUI and calendar.php
      $events = $this->getEventsByUID($event['uid']);
      $this->scheduleReminders($events[0]);
      if($this->type == 'caldav'){
        //find me: investigate why this is here ...
        if($this->rcmail->action == 'plugin.editEvent' || $this->rcmail->action == 'plugin.newEvent' || $this->rcmail->action == 'plugin.removeEvent' || $this->rcmail->action == 'plugin.calendar_upload' || $this->rcmail->action == 'plugin.calendar_showlayer'){
          $caldavs = $this->caldavs;
          if($categories != $old_categories){
            if(!empty($caldavs[$old_categories]) || !empty($caldavs[$categories])){
              $sync = $this->newEvent($start, $end, $summary, $description, $location, $categories, $allDay, $srecur, $expires, $occurrences, $byday, $bymonth, $bymonthday, $recurrence_id, $exdates, $reminderbefore, $remindertype, $remindermailto);
              if($sync){
                if(!$old_categories){
                  $old_categories = md5(time());
                }
                $events[0] = $this->removeEvent($id, $old_categories);
              }
            }
            else{
              $events[0]['sync'] = $this->syncCalDAV($events);
            }
          }
          else{
            $events[0]['sync'] = $this->syncCalDAV($events, 'PUT', $categories);
          }
        }
      }
      else{
        $events[0]['sync'] = true;
      }
      return $events[0];
    }
  }

  public function moveEvent(
    $id,
    $start,
    $end,
    $allDay,
    $reminder
  ) {
    if (!empty($this->rcmail->user->ID)) {
      $offset = $this->offset($start);
      $start = $start + $offset;
      $offset = $this->offset($end);
      $end = $end + $offset;
      $query = $this->rcmail->db->query(
        "UPDATE " . $this->table('events') . " 
         SET ".$this->q('start')."=?, ".
               $this->q('end')."=?, ".
               $this->q('remindersent')."=?, ".
               $this->q('timestamp')."=?, ".
               $this->q('notified')."=?
         WHERE ".$this->q('event_id')."=?
         AND ".$this->q('user_id')."=?",
        $start,
        $end,
        0,
        date('Y-m-d H:i:s', time()),
        0,
        $id,
        $this->rcmail->user->ID
      );
      $event = $this->getEvent($id);
      // find me: get uid from GUI and calendar.php
      $events = $this->getEventsByUID($event['uid']);
      if($this->rcmail->action == 'plugin.moveEvent'){
        $events[0]['sync'] = $this->syncCalDAV($events,'PUT',$events[0]['categories']);
      }
      $this->scheduleReminders($events[0]);
      return $events[0];
    }
  }
  
  public function resizeEvent(
    $id,
    $start,
    $end,
    $reminder
  ) {
    if (!empty($this->rcmail->user->ID)) {
      $offset = $this->offset($start);
      $start = $start + $offset;
      $offset = $this->offset($end);
      $end = $end + $offset;
      $query = $this->rcmail->db->query(
        "UPDATE " . $this->table('events') . " 
         SET ".$this->q('start')."=?, ".
               $this->q('end')."=?, ".
               $this->q('remindersent')."=?, ".
               $this->q('timestamp')."=?, ".
               $this->q('notified')."=?
         WHERE ".$this->q('event_id')."=?
         AND ".$this->q('user_id')."=?",
        $start,
        $end,
        0,
        date('Y-m-d H:i:s', time()),
        0,
        $id,
        $this->rcmail->user->ID
      );
      $event = $this->getEvent($id);
      // find me: get uid from GUI and calendar.php
      $events = $this->getEventsByUID($event['uid']);
      if($this->rcmail->action == 'plugin.resizeEvent'){
        $events[0]['sync'] = $this->syncCalDAV($events,'PUT',$events[0]['categories']);
      }
      $this->scheduleReminders($events[0]);
      return $events[0];
    }
  }

  public function removeEvent($id, $categories=false) {
    if (!empty($this->rcmail->user->ID)) {
      $query = $this->rcmail->db->query(
        "UPDATE " . $this->table('events') . " 
         SET ".$this->q('del')."=?, ".$this->q('timestamp')."=?
         WHERE ".$this->q('event_id')."=?
         AND ".$this->q('user_id')."=?",
        1,
        date('Y-m-d H:i:s', time()),
        $id,
        $this->rcmail->user->ID
      );
      $event = $this->getEvent($id);
      // find me: get uid from GUI and calendar.php
      $events = $this->getEventsByUID($event['uid']);
      //find me: investigate why this is here ...
      if($this->rcmail->action == 'plugin.editEvent' || $this->rcmail->action == 'plugin.removeEvent' || $this->rcmail->action == 'plugin.calendar_showlayer'){
        $caldavs = $this->caldavs;
        if($event['recurrence_id'] != 0){
          $initialevent = $this->getEventByUID($event['uid']);
          if($initialevent['exdates']){
            $exdates = (array) @unserialize($initialevent['exdates']);
            $exdates[] = (int) $event['start'];
          }
          else{
            $exdates = array((int) $event['start']);
          }
          $initialevent['exdates'] = serialize($exdates);
          $initialevent['recurrence_id'] = null;
          $events[0]['sync'] = $this->syncCalDAV(array(0 => $initialevent),'PUT',$initialevent['categories']);
        }
        else{
          if(!$categories)
            $categories = $events[0]['categories'];
          $events[0]['sync'] = $this->syncCalDAV($events,'DELETE',$categories);
        }
      }
      $this->scheduleReminders($events[0]);
      return $events[0];
    }
  }
  
  public function purgeEvents() {
   if (!empty($this->rcmail->user->ID)) {
      $query = $this->rcmail->db->query(
        "DELETE FROM " . $this->table('events') . "
         WHERE ".$this->q('user_id')."=?
         AND ".$this->q('del')."<>?",
         $this->rcmail->user->ID,
         0
      );
    } 
  }
  
  public function removeDuplicate($id) {
    if (!empty($this->rcmail->user->ID)) {
      $query = $this->rcmail->db->query(
        "DELETE FROM " . $this->table('events') . "
         WHERE ".$this->q('user_id')."=? AND ".$this->q('event_id')."=?",
         $this->rcmail->user->ID,
         $id
      );
    }
  }
  
  public function uninstall() {
    if (!empty($this->rcmail->user->ID)) {
      $query = $this->rcmail->db->query(
        "DELETE FROM " . $this->table('events') . "
        WHERE ".$this->q('user_id')."=?",
        $this->rcmail->user->ID
      );
      $default = array(
        'database' => '', // default db table
        'caldav' => '_caldav', // caldav db table (= default db table) extended by _caldav
      );
      $map = $this->rcmail->config->get('backend_db_table_map', $default);
      $table = str_replace('`','',str_replace($map['caldav'], '', $this->table('events')));
      $this->rcmail->config->set('db_table_events', $table);
      $query = $this->rcmail->db->query(
        "DELETE FROM " . $this->table('events') . "
        WHERE ".$this->q('user_id')."=?",
        $this->rcmail->user->ID
      );
      $query = $this->rcmail->db->query(
        "DELETE FROM " . $this->table('events_cache') . "
        WHERE ".$this->q('user_id')."=?",
        $this->rcmail->user->ID
      );
      $query = $this->rcmail->db->query(
        "DELETE FROM " . $this->table('reminders') . "
        WHERE ".$this->q('user_id')."=?",
        $this->rcmail->user->ID
      );
    }
  }
  
  public function truncateEvents($mode=0) {
    if (!empty($this->rcmail->user->ID)) {
      if($mode == 0){
        $query = $this->rcmail->db->query(
          "DELETE FROM " . $this->table('events') . "
          WHERE ".$this->q('user_id')."=? AND ".$this->q('del')."=?",
          $this->rcmail->user->ID,
          1
        );
      }
      else if($mode == 1){
        $query = $this->rcmail->db->query(
          "UPDATE " . $this->table('events') . " 
          SET ".$this->q('del')."=?, ".$this->q('timestamp')."=?
          WHERE ".$this->q('user_id')."=?",
          2,
          date('Y-m-d H:i:s', time()),
          $this->rcmail->user->ID
        );
      }
      else if($mode == 2){
        $query = $this->rcmail->db->query(
          "UPDATE " . $this->table('events') . " 
          SET ".$this->q('del')."=?, ".$this->q('timestamp')."=?
          WHERE ".$this->q('user_id')."=?",
          0,
          date('Y-m-d H:i:s', time()),
          $this->rcmail->user->ID
        );
      }
      else if($mode == 3){
        $query = $this->rcmail->db->query(
          "DELETE FROM " . $this->table('events') . "
          WHERE ".$this->q('user_id')."=?",
          $this->rcmail->user->ID
        );
      }
      else if($mode == 4){
        $query = $this->rcmail->db->query(
          "UPDATE " . $this->table('events') . " 
          SET ".$this->q('del')."=?, ".$this->q('timestamp')."=?
          WHERE ".$this->q('user_id')."=? AND " . $this->q('url') . "IS NULL",
          2,
          date('Y-m-d H:i:s', time()),
          $this->rcmail->user->ID
        );
        $ctags = $this->getCtags();
        $ctags_saved = $this->rcmail->config->get('ctags', array());
        foreach($ctags as $hash => $ctag){
          if($ctag !== $ctags_saved[$hash]){
            if(md5($this->account['url']) == $hash){
              $query = $this->rcmail->db->query(
                "UPDATE " . $this->table('events') . " 
                SET ".$this->q('del')."=?, ".$this->q('timestamp')."=?
                WHERE ".$this->q('user_id')."=? AND ".$this->q('url')."=?",
                2,
                date('Y-m-d H:i:s', time()),
                $this->rcmail->user->ID,
                $this->rcmail->config->get('caldav_url')
              );
            }
            $caldavs = $this->caldavs;
            foreach($caldavs as $key => $caldav){
              if(md5($caldav['url']) == $hash){
                $query = $this->rcmail->db->query(
                  "UPDATE " . $this->table('events') . " 
                  SET ".$this->q('del')."=?, ".$this->q('timestamp')."=?
                  WHERE ".$this->q('user_id')."=? AND ".$this->q('url')."=?",
                  2,
                  date('Y-m-d H:i:s', time()),
                  $this->rcmail->user->ID,
                  $caldav['url']
                );
                break;
              }
            }
          }
        }
      }
    }
  }
  
  public function exportEvents($categories=false){
    if($this->type == 'caldav'){
      if($categories){
        $caldavs = $this->caldavs;
        if(is_array($caldavs[$categories])){
          $this->connect($caldavs[$categories]['url'],$caldavs[$categories]['user'],$caldavs[$categories]['pass'],$caldavs[$categories]['auth']);
        }
      }
      else{
        $this->connect($this->account['url'], $this->account['user'], $this->account['pass'], $this->account['auth']);
      }
      $response = trim($this->caldav->DoRequest());
      $temparr = explode("\r\n\r\n", $response);
      if(strtoupper(substr($temparr[count($temparr) - 1], 0, strlen('BEGIN:VCALENDAR'))) != 'BEGIN:VCALENDAR'){
        $append = '?export';
        if($categories){
          $caldavs = $this->caldavs;
          if(is_array($caldavs[$categories])){
            $this->connect($caldavs[$categories]['url'].$append,$caldavs[$categories]['user'],$caldavs[$categories]['pass'],$caldavs[$categories]['auth']);
          }
        }
        else{
          $this->connect($this->account['url'].$append, $this->account['user'], $this->account['pass'], $this->account['auth']);
        }
        $response = trim($this->caldav->DoRequest());
        $temparr = explode("\r\n\r\n", $response);
      }
      if(strtoupper(substr($temparr[count($temparr) - 1], 0, strlen('BEGIN:VCALENDAR'))) == 'BEGIN:VCALENDAR'){
        $return = $temparr[count($temparr) - 1];
      }
      else{
        $return = "BEGIN:VCALENDAR\nEND:VCALENDAR";
      }
      return $return;
    }
  }
  
  private function _getEvents(
    $estart,
    $eend,
    $category=false,
    $type='events'
  ) {
    if($this->type == 'caldav'){
      $events = array();
      $default = array(
        'database' => '', // default db table
        'caldav' => '_caldav', // caldav db table (= default db table) extended by _caldav
      );
      $map = $this->rcmail->config->get('backend_db_table_map', $default);
      $ctags = $this->rcmail->config->get('ctags', array());
      if(stripos($this->table('events'),$map['caldav'])){
        $startYear  = date('Y',$estart);
        $startMonth = date('m',$estart);
        $startDay   = date('d',$estart);
        $endYear    = date('Y',$eend  );
        $endMonth   = date('m',$eend  );
        $endDay     = date('d',$eend  );
        $caldavs = $this->caldavs;
        if(!$category || ($category && !$caldavs[$category])){
          if($type == 'events'){
            $events = (array) $this->caldav->GetEvents($startYear.$startMonth.$startDay."T000000Z",$endYear.$endMonth.$endDay."T000000Z");
          }
          else if($type == 'alarms'){
            // cron login successful ?
            if(class_exists('CalDAVClient') && method_exists('CalDAVClient', 'GetEventAlarms')){
              $events = (array) $this->caldav->GetEventAlarms($startYear.$startMonth.$startDay."T000000Z",$endYear.$endMonth.$endDay."T000000Z");
            }
          }
        }
        else{
          if($category && $caldavs[$category]){
            $caldav = $caldavs[$category];
            $this->connect($caldav['url'],$caldav['user'],$caldav['pass'],$caldav['auth']);
            $startYear  = date('Y',$estart);
            $startMonth = date('m',$estart);
            $startDay   = date('d',$estart);
            if($type == 'events'){
              $layers = (array) $this->caldav->GetEvents($startYear.$startMonth.$startDay."T000000Z",$endYear.$endMonth.$endDay."T000000Z");
            }
            else if($type == 'alarms'){
              $layers = (array) $this->caldav->GetEventAlarms($startYear.$startMonth.$startDay."T000000Z",$endYear.$endMonth.$endDay."T000000Z");
            }
            foreach($layers as $key => $layer){
              $layers[$key]['data'] = str_replace("\nCATEGORIES:","\nX-CATEGORIES:",$layers[$key]['data']);
              $insert = "\nCATEGORIES:" . $category;
              $layers[$key]['data'] = str_replace("\nBEGIN:VEVENT", "\nBEGIN:VEVENT" . $insert, $layers[$key]['data']);
            }
            $events = $layers;
          }
        }
        foreach($events as $key => $val){
          $insert = "\nX-HREF:".$val['href']."\nX-ETAG:".$val['etag'];
          $val['data'] = str_replace("\nBEGIN:VEVENT", "\nBEGIN:VEVENT" . $insert, $val['data']);
          $this->utils->importEvents($val['data'], false, false, false, false, false, false, $val['href'], $val['etag']);
          $import = true;
        }
        if($import){
          $query = $this->rcmail->db->query(
            "UPDATE " . $this->table('events') . " 
            SET ".$this->q('timestamp')."=?, ".$this->q('notified')."=?
            WHERE ".$this->q('user_id')."=?",
            '0000-00-00 00:00:00',
            '1',
            $this->rcmail->user->ID
          );
        }
      }
    }
  }
  
  public function removeReminder(
    $id,
    $event_id,
    $ts
  ) {
    if (!empty($this->rcmail->user->ID)) {
      if(!$id && !$event_id){
        $reminders = (array) $this->getReminders($ts);
        $query = $this->rcmail->db->query(
          "DELETE FROM " . $this->table('reminders') . "
          WHERE " . $this->q('type') . "=? AND ".$this->q('user_id')."=? AND " . $this->q('runtime') . "<?",
          'popup',
          $this->rcmail->user->ID,
          $ts
        );
        foreach($reminders as $reminder){
          $query = $this->rcmail->db->query(
            "UPDATE " .$this->table('events') . " 
            SET ". $this->q('remindersent')."=?
            WHERE ".$this->q('event_id')."=?
            AND ".$this->q('user_id')."=?",
            $ts,
            $reminder['id'],
            $this->rcmail->user->ID
          );
        }
      }
      else{
        $query = $this->rcmail->db->query(
          "DELETE FROM " . $this->table('reminders') . "
          WHERE " . $this->q('reminder_id') . "=? AND ".$this->q('user_id')."=?",
          $id,
          $this->rcmail->user->ID
        );
        $query = $this->rcmail->db->query(
          "UPDATE " .$this->table('events') . " 
          SET ". $this->q('remindersent')."=?
          WHERE ".$this->q('event_id')."=?
          AND ".$this->q('user_id')."=?",
          $ts,
          $event_id,
          $this->rcmail->user->ID
        );
      }
    }
  }
  
  public function getReminders(
    $start,
    $type='popup'
  ) {
    $ret = array();
    if (!empty($this->rcmail->user->ID)) {
      $result = $this->rcmail->db->query(
        "SELECT * FROM " . $this->table('reminders') . " 
         WHERE ".$this->q('user_id')."=? AND ".$this->q('runtime')."<? AND ".$this->q('type')."=?",
         $this->rcmail->user->ID,
         $start,
         $type
      );
      $reminders = array();
      while ($result && ($reminder = $this->rcmail->db->fetch_assoc($result))) {
        $reminders[] = $reminder;
      }
      foreach($reminders as $key => $val){
        $col = 'events';
        if($val['caldav'])
          $col = 'caldav';
        else if($val['cache'])
          $col = 'cache';
        $result = $this->rcmail->db->query(
          "SELECT * FROM " . $this->table('events') . " 
          WHERE ".$this->q('user_id')."=? AND ".$this->q('event_id')."=?",
          $this->rcmail->user->ID,
          $val[$col]
        );
        if($result){
          $event = $this->rcmail->db->fetch_assoc($result);
          if(is_numeric($event['event_id'])){
            if($event['recurring'] != '0'){
              $duration = 0;
              if($event['end'] != '0'){
                $duration = (int) $event['end'] - (int) $event['start'];
              }
              $start = (int) $val['runtime'] + (int) $event['reminder'];
              $end = $start + $duration;
              $event['start'] = $start;
              if($event['end'] != '0'){
                $event['end'] = $start + $duration;
              }
            }
            $event['reminder_id'] = $val['reminder_id'];
            $ret[$event['start'] . $event['uid'] . $event['event_id']] = $this->utils->eventArrayMap($event);
          }
        }
      }
    }
    return $ret;
  }
  
  public function replicateEvents(
    $estart,
    $eend,
    $category=false,
    $type='events'
  ){
    if (!empty($this->rcmail->user->ID)) {
      $this->_getEvents($estart, $eend, $category, $type);
    }
  }
  
  public function getEvents(
    $estart,
    $eend,
    $labels=array(),
    $category=false,
    $filter=false,
    $client=false,
    $type='events'
  ) {
    if (!empty($this->rcmail->user->ID)) {
      // find me: memory exhaustion?
      $start = strtotime(date('Y', strtotime('-100 years')) . '-01-01') - 1;
      $end = $eend + 1;
      if($filter){
        $filterfield = $filter[0];
        $filtercomp = '=?';
        $filterval = $filter[1];
      }
      else{
        $filterfield = 'del';
        $filtercomp = '<>?';
        $filterval = 1;
      }
      $result = $this->rcmail->db->query(
        "SELECT * FROM " . $this->table('events') . " 
         WHERE ".$this->q('user_id')."=? AND ".
                 //$this->q('start').">? AND ". // find me: memory exhaustion?
                 $this->q('start')."<? AND ".
                 $this->q($filterfield) . $filtercomp,
         $this->rcmail->user->ID,
         //$start, // find me: memory exhaustion?
         $end,
         $filterval
      );
      $events = array();
      while ($result && ($event = $this->rcmail->db->fetch_assoc($result))) {
        if($this->rcmail->action == 'plugin.getEvents'){
          $db_category = $event['categories'];
          if($category){
            if($db_category != $category){
              continue;
            }
          }
          else{
            $caldavs = $this->caldavs;
            if(!empty($caldavs[$db_category])){
              continue;
            }
          }
        }
        // backwards compatibility
        if(empty($event['rr'])){
          switch($event['recurring']){
            case    86400:
            case    86401:
              $event['rr'] = 'd';
              break;
            case   604800:
            case  1209600:
            case  1814400:
            case  2419200:
              $event['rr'] = 'w';
              break;
            case  2592000:
              $event['rr'] = 'm';
              break;
            case 31536000:
              $event['rr'] = 'y';
              break;
          }
        }
        $append = $event['start'];
        $add = true;
        if($event['exdates']){
          $exdates = @unserialize($event['exdates']);
          if(is_array($exdates) && count($exdates) > 0){
            $exdates = (array) $this->utils->array_flatten($exdates);
            $exdates = array_flip($exdates);
            if(isset($exdates[$event['start']])){
              $add = false;
            }
          }
        }
        if($event['recurrence_id']){
          $append = $event['recurrence_id'];
        }
        if($event['start'] >= $estart || $event['end'] >= $estart || $event['recurring'] != 0){
          if($event['start'] >= $estart || $event['end'] >= $estart){
            if($add){
              $events[md5($event['uid'].$append)]=array( 
               'event_id'        => (int)    $event['event_id'],
                'uid'             => (string) $event['uid'], 
                'start'           => (int)    $event['start'], 
                'end'             => (int)    $event['end'],
                'expires'         => (string) $event['expires'],
                'rr'              => (string) $event['rr'],
                'recur'           => (string) $event['recurring'],
                'occurrences'     => (int)    $event['occurrences'],
                'recurrence_id'   => (string) $event['recurrence_id'],
                'summary'         => (string) $event['summary'], 
                'description'     => (string) $event['description'],
                'location'        => (string) $event['location'],
                'categories'      => (string) $event['categories'],
                'timestamp'       => (string) $event['timestamp'],
                'del'             => (int)    $event['del'],
                'notified'        => (int)    $event['notified'],
                'byday'           => (string) $event['byday'],
                'bymonth'         => (string) $event['bymonth'],
                'bymonthday'      => (string) $event['bymonthday'],
                'reminder'        => (int)    $event['reminder'],
                'reminderservice' => (string) $event['reminderservice'],
                'remindermailto'  => (string) $event['remindermailto'],
                'editable'        => true,
                'clone'           => false 
              );
            }
          }
          if($event['recurring'] != 0 && !$client){
            $stz = date_default_timezone_get();
            date_default_timezone_set($_SESSION['tzname']);
            if($event['end']){
              $clone_duration = $event['end'] - $event['start'];
              $dst_adjust = 0;
              if(date('I', $event['start']) != date('I', $event['end'])){
                if(date('I',$event['start']) == 0 && date('I',$event['end']) == 1)
                  $dst_adjust = 3600;
                if(date('I',$event['start']) == 1 && date('I',$event['end']) == 0)
                  $dst_adjust = -3600;
              }
              $clone_duration = $clone_duration + $dst_adjust;
            }
            $rrule = $this->utils->rrule($event);
            if($rrule){
              $basedate = date('Ymd\THis',$event['start']);
              /* find me: Davical rrule parser uses YEARYL => MONTHLY;INTERVAL = 12
                 This failes f.e. for:
                   RRULE:FREQ=YEARLY;UNTIL=20291231T230000Z;INTERVAL=1;BYDAY=1SU;BYMONTH=1
                 ToDo: Calculate base date for first recurring event
              */
              $this->_rule = preg_replace( '/\s/m', '', $rrule);
              if(substr($this->_rule, 0, 6) == 'RRULE:'){
                $this->_rule = substr($this->_rule, 6);
              }
              $parts = explode(';', $this->_rule);
              $this->_part = array( 'INTERVAL' => 1 );
              foreach( $parts AS $k => $v ){
                list( $type, $value ) = explode( '=', $v, 2);
                $this->_part[$type] = $value;
              }
              if($this->_part['FREQ'] == 'YEARLY' && $this->_part['BYMONTH']){
                if(date('n', $event['start']) != $this->_part['BYMONTH']){
                  $year = date('Y', $event['start']);
                  $month = substr(date('n', $this->_part['BYMONTH']) + 100, 1);
                  $basedate = date($year . $month . 'd\THis', $event['start']);
                }
              }
              $rule = new RRule(new iCalDate($basedate), $rrule);
              $hasoccurred = 0;
              do{
                $date = $rule->GetNext();
                if(isset($date)){
                  $clone_date = $date->Render();
                  $clone_start = strtotime($clone_date);
                  if($clone_duration){
                    $clone_end = strtotime($clone_date) + $clone_duration;
                  }
                  else{
                    $clone_end = null;
                  }
                  if($clone_start > $event['start'] && $clone_start >= $estart){
                    if(!isset($exdates[$clone_start])){
                      if($add){
                        $set_clone = (string) $event['start'];
                        $set_clone_end = (string) $event['end'];
                      }
                      else{
                        $set_clone = false;
                        $set_clone_end = false;
                        $add = true;
                      }
                      $hasoccurred ++;
                      $event['editable'] = true;
                      $event['recur'] = $event['recurring'];
                      $events[md5($event['uid'].$clone_start)] = array( 
                        'event_id'        => (int) $event['event_id'],
                        'uid'             => (string) $event['uid'],
                        'start'           => (int) $clone_start,
                        'end'             => (int) $clone_end,
                        'expires'         => (string) $event['expires'],
                        'rr'              => (string) $event['rr'],
                        'recur'           => (string) $event['recurring'],
                        'occurrences'     => (int)    $event['occurrences'],
                        'hasoccurred'     => (int)   $hasoccurred,
                        'recurrence_id'   => (string) $event['recurrence_id'],
                        'summary'         => (string) $event['summary'], 
                        'description'     => (string) $event['description'],
                        'location'        => (string) $event['location'],
                        'categories'      => (string) $event['categories'],
                        'timestamp'       => (string) $event['timestamp'],
                        'del'             => (int)    $event['del'],
                        'byday'           => (string) $event['byday'],
                        'bymonth'         => (string) $event['bymonth'],
                        'bymonthday'      => (string) $event['bymonthday'],
                        'reminder'        => (int)    $event['reminder'],
                        'reminderservice' => (string) $event['reminderservice'],
                        'remindermailto'  => (string) $event['remindermailto'],
                        'editable'        => (bool)   $event['editable'],
                        'clone'           => $set_clone,
                        'clone_end'       => $set_clone_end,
                        'caldav'          => (string) $event['caldav'],
                        'notified'        => (int) $event['notified'],
                        'initial'         => (array) $this->utils->eventArrayMap($event)
                      );
                    }
                  }
                }
              }
              while(isset($date) && strtotime($date->Render()) <= $eend);
            }
            date_default_timezone_set($stz);
          }
        }
      }
      $public_caldavs = $this->rcmail->config->get('public_caldavs', array());
      foreach($events as $key => $event){
        $category = $event['categories'];
        if(!empty($public_caldavs[$category]) && $public_caldavs[$category]['readonly']){
          $events[$key]['editable'] = false;
        }
      }
      return $events;
    }
  }
  
  public function removeTimestamps() {
    if (!empty($this->rcmail->user->ID)) {
      $query = $this->rcmail->db->query(
        "UPDATE " . $this->table('events') . " 
         SET ".$this->q('timestamp')."=?
         WHERE ".$this->q('user_id')."=?",
        '0000-00-00 00:00:00',
        $this->rcmail->user->ID
      );
    }
  }
  
  public function removeDuplicates($table = 'events'){
    if (!empty($this->rcmail->user->ID)) {
      $result = $this->rcmail->db->query(
        "SELECT * FROM " . $this->table($table) . " 
         WHERE ".$this->q('user_id')."=?",
         $this->rcmail->user->ID
      );
      while ($result && ($event = $this->rcmail->db->fetch_assoc($result))){
        if(isset($events[$event['uid']])){
          $this->rcmail->db->query(
            "DELETE FROM " . $this->table($table) . "
            WHERE ".$this->q('user_id')."=? AND ".$this->q('event_id')."=?",
            $this->rcmail->user->ID,
            $event['event_id']
          );
        }
        else{
          $events[$event['uid']] = true;
        }
      }
    }
  }
  
  public function getEvent($eventid){
    if (!empty($this->rcmail->user->ID)) {
      $result = $this->rcmail->db->query(
        "SELECT * FROM " . $this->table('events') . " 
         WHERE ".$this->q('user_id')."=? AND ".$this->q('event_id')."=?",
         $this->rcmail->user->ID,
         $eventid
      );
      $event = $this->rcmail->db->fetch_assoc($result);
      if($event){
        $event['recur'] = $event['recurring'];
        return $event;
      }
    }
    return array();
  }
  
  public function getEventByUID($uid, $recurrence_id=0){
    $event = array();
    if (!empty($this->rcmail->user->ID)) {
      $result = $this->rcmail->db->query(
        "SELECT * FROM " . $this->table('events') . " 
         WHERE ".$this->q('user_id')."=? AND ".$this->q('uid')."=? AND ".$this->q('recurrence_id')."=?",
         $this->rcmail->user->ID,
         $uid,
         $recurrence_id
      );
      while ($result && ($event = $this->rcmail->db->fetch_assoc($result))){
        $events[] = $event;
      }
      /*if(is_array($events) && count($events) > 1)
        write_log('calendar', "ERROR: Event with UID " . $event['uid'] . " is not unique");*/
      $event = $events[0];
    }
    return $event;
  }
  
  public function getEventsByUID($uid){
    if (!empty($this->rcmail->user->ID)) {
      $result = $this->rcmail->db->query(
        "SELECT * FROM " . $this->table('events') . " 
         WHERE ".$this->q('user_id')."=? AND ".$this->q('uid')."=?",
         $this->rcmail->user->ID,
         $uid
      );
      while ($result && ($event = $this->rcmail->db->fetch_assoc($result))){
        $temparr[] = $event;
      }
    }
    $i = 0;
    $events = array();
    if(is_array($temparr)){
      foreach($temparr as $key => $event){
        if(!$event['recurrence_id']){
          /*if(!empty($events[0]))
            write_log('calendar', "ERROR: Event with UID " . $event['uid'] . " is not unique");*/
          $events[0] = $event;
        }
        else{
          $events[$i] = $event;
        }
        $i++;
      }
    }
    return $events;
  }
  
  private function offset($time = 0){
    $stz = date_default_timezone_get();
    if($_SESSION['tzname'])
      $tz = $_SESSION['tzname'];
    else if(get_input_value('_btz', RCUBE_INPUT_GPC))
      $tz = get_input_value('_btz', RCUBE_INPUT_GPC);
    else if(get_input_value('_tz', RCUBE_INPUT_GPC))
      $tz = get_input_value('_tz', RCUBE_INPUT_GPC);
    else
      $tz = $stz;
    if($this->rcmail->config->get('timezone') != 'auto'){
      $ctz = $this->rcmail->config->get('timezone');
    }
    else{
      $ctz = $tz;
    }
    date_default_timezone_set($tz);
    $offset = - date('Z', $time);
    date_default_timezone_set($ctz);
    $offset = $offset + date('Z', $time);
    return - $offset;
  }
}
?>