<?php
/**
 * http_auth
 *
 * @version 1.4.1 - 25.05.2013
 * @author Roland 'rosali' Liebl
 * @author Thomas Bruederli
 * @website http://myroundcube.googlecode.com
 * @based on HTTP Authentication by Thomas Bruederli
 * 
 **/
 

class http_auth extends rcube_plugin
{
  public $task = 'login|logout|settings';
  public $noajax = true;
  public $noframe = true;
  private $query;
  
  static private $plugin = 'http_auth';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = null;
  static private $download = 'http://myroundcube.googlecode.com';
  static private $version = '1.4.1';
  static private $date = '25-05-2012';
  static private $licence = 'GPL';
  static private $requirements = array(
    'Roundcube' => '0.7.1',
    'PHP' => '5.2.1'
  );
  static private $config = null;

  function init()
  {
    $this->add_hook('startup', array($this, 'startup'));
    $this->add_hook('authenticate', array($this, 'authenticate'));
    $this->add_hook('login_after', array($this, 'login'));
  }
  
  static public function about($keys = false)
  {
    $requirements = self::$requirements;
    foreach(array('required_', 'recommended_') as $prefix){
      if(is_array($requirements[$prefix.'plugins'])){
        foreach($requirements[$prefix.'plugins'] as $plugin => $method){
          if(class_exists($plugin) && method_exists($plugin, 'about')){
            /* PHP 5.2.x workaround for $plugin::about() */
            $class = new $plugin(false);
            $requirements[$prefix.'plugins'][$plugin] = array(
              'method' => $method,
              'plugin' => $class->about($keys),
            );
          }
          else{
             $requirements[$prefix.'plugins'][$plugin] = array(
               'method' => $method,
               'plugin' => $plugin,
             );
          }
        }
      }
    }
    $ret = array(
      'plugin' => self::$plugin,
      'version' => self::$version,
      'date' => self::$date,
      'author' => self::$author,
      'comments' => self::$authors_comments,
      'licence' => self::$licence,
      'download' => self::$download,
      'requirements' => $requirements,
    );
    if(is_array($keys)){
      $return = array('plugin' => self::$plugin);
      foreach($keys as $key){
        $return[$key] = $ret[$key];
      }
      return $return;
    }
    else{
      return $ret;
    }
  }

  function startup($args)
  {
    if($args['action'] != '' && $args['task'] != 'logout'){
      // change action to login
      if (empty($_SESSION['user_id'])
          && !empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])){
        $args['action'] = 'login';
        $this->query = $_SERVER['QUERY_STRING'];
      }
    }
    return $args;
  }

  function authenticate($args)
  {
    // Allow entering other user data in login form,
    // e.g. after log out (#1487953)
    if (!empty($args['user'])) {
        return $args;
    }

    if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
      $args['user'] = $_SERVER['PHP_AUTH_USER'];
      $args['pass'] = $_SERVER['PHP_AUTH_PW'];
      if(class_exists("hmail_login", false)){
        $temp = explode("@",$args['user']);
        if(count($temp) == 1 && $args['user'] != ""){
          $args['user'] = $args['user'] . "@" . rcmail::get_instance()->config->get('hmail_default_domain');
        }
        $args['user'] = hmail_login::resolve_alias($args['user']);
      }
      $args['cookiecheck'] = false;
      $args['valid'] = true;
    }
    return $args;
  }
  
  function login($args)
  {
    if($this->query){
      header('Location: ./?' . $this->query);
      exit;
    }
    return $args;
  }

}

