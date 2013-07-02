<?php
/**
 * plugin_manager
 *
 * @version 16.0.2 - 31.03.2013
 * @author Roland 'rosali' Liebl
 * @website http://myroundcube.googlecode.com
 * 
 **/
 
/**
 *
 * Requirements: #1- qtip plugin (do not register, it is loaded automatically)
 *               #2- jqueryui (don not register, it is loaded automatically)
 *               #3- global_alias (don not register, it is loaded automatically)
 *               #4- PHP 5.2.1
 *
 **/
 
class plugin_manager extends rcube_plugin{
  private $rcmail;
  private $template;
  private $admins = array();
  private $host;
  private $domain;
  private $config;
  private $lables;
  private $plugins;
  private $mirror = 'http://mirror.myroundcube.com';
  private $svn = 'http://dev.myroundcube.com';
  private $stable = '0.8.6';
  private $dev = '0.9-rc';
  private $rcurl = 'http://roundcube.net';
  private $guide = 'http://myroundcube.com/myroundcube-plugins/plugins-installation';
  private $vlength = 5;
  private $billingurl = 'http://billing.myroundcube.com/?_task=billing&_action=buycredits';
  private $dlurl = 'https://billing.myroundcube.com/pm/';
  private $delay = 8000;
  
  /* unified plugin properties */
  static private $plugin = 'plugin_manager';
  static private $author = 'myroundcube@mail4us.net';
  static private $authors_comments = '<a href="http://myroundcube.com/myroundcube-plugins/plugin-manager-installation-guide" target="_new">Installation Guide</a><br /><a href="http://myroundcube.com/myroundcube-plugins/plugin-manager#plugin_manager-configuration-parameters" target="_new">Documentation</a><br /><a href="http://mirror.myroundcube.com/docs/plugin_manager.html" target="_new"><font color="red">IMPORTANT</font></a>';
  static private $download = 'http://myroundcube.googlecode.com';
  static private $version = '16.0.2';
  static private $date = '31-03-2013';
  static private $licence = 'All Rights reserved';
  static private $requirements = array(
    'Roundcube' => '0.8.1',
    'PHP' => '5.2.1',
    'required_plugins' => array(
      'qtip' => 'require_plugin',
      'global_alias' => 'require_plugin',
      'jqueryui' => 'require_plugin',
      'settings' => 'require_plugin',
      'http_request' => 'require_plugin',
    ),
  );
  static private $prefs = array('plugin_manager_active');
  static private $config_dist = 'config.inc.php.dist';
  
  function init(){
    if(defined('OPENSSL_VERSION_TEXT')){
      $this->mirror = str_replace('http://', 'https://', $this->mirror);
      $this->svn = str_replace('http://', 'https://', $this->svn);
      $this->billingurl = str_replace('http://', 'https://', $this->billingurl);
    }
    $rcmail = rcmail::get_instance();
    if(!in_array('global_config', $rcmail->config->get('plugins'))){
      $this->load_config();
      $this->require_plugin('settings');
    }
    
    $this->require_plugin('global_alias');
    $this->admins = array_flip($rcmail->config->get('plugin_manager_admins', array()));
    if($rcmail->task == 'settings'){
      if($rcmail->config->get('plugin_manager_admins')){
        $admins = array_flip($rcmail->config->get('plugin_manager_admins', array()));
      }
      else{
        $dbtype = $rcmail->config->get('db_dsnw');
        $dbtype = parse_url($dbtype);
        $dbtype = $dbtype['scheme'];
        if($sql = @file_get_contents(INSTALL_PATH . 'plugins/plugin_manager/SQL/' . $dbtype . '.sql')){
          $rcmail->db->query($sql);
          $sql = 'SELECT * FROM . ' . get_table_name('plugin_manager') . ' WHERE conf=?';
          $res = $rcmail->db->query($sql, 'admins');
          $conf = array();
          while($c = $rcmail->db->fetch_assoc($res)){
            $conf[] = $c;
          }
          if(count($conf) < 1){
            $sql = 'INSERT INTO ' . get_table_name('plugin_manager') . ' (user_id, conf, value, type) VALUES (?,?,?,?)';
            $admins = array($rcmail->user->data['username']);
            $rcmail->db->query($sql, $rcmail->user->ID, 'admins', serialize($admins), 'array');
          }
          else{
            $admins = (array) unserialize($conf[0]['value']);
          }
          $admins = array_flip($admins);
        }
        else{
          $admins = array();
        }
      }
      $this->admins = $admins;
      if(RCMAIL_VERSION < '0.8')
        $this->require_plugin('jqueryui');
      $this->require_plugin('qtip');
    }

    $this->add_texts('localization/', false);
    
    $this->add_hook('render_page', array($this, 'render_page'));
    $this->add_hook('send_page', array($this, 'send_page'));
    $this->add_hook('preferences_sections_list', array($this, 'settings_link'));
    $this->add_hook('preferences_list', array($this, 'settings'));
    $this->add_hook('preferences_save', array($this, 'saveprefs'));
    $this->add_hook('login_after', array($this, 'redirect'));
    $this->add_hook('plugins_installed', array($this, 'plugins'));

    $this->register_action('plugin.plugin_manager', array($this, 'navigation'));
    $this->register_action('plugin.plugin_manager_save', array($this, 'save'));
    $this->register_action('plugin.plugin_manager_uninstall', array($this, 'uninstall'));
    $this->register_action('plugin.plugin_manager_update', array($this, 'update'));
    $this->register_action('plugin.plugin_manager_iframe', array($this, 'navigation'));
    $this->register_action('plugin.plugin_manager_getnew', array($this, 'getnew'));
    $this->register_action('plugin.plugin_manager_transfer', array($this, 'transfer'));
    $this->register_action('plugin.plugin_manager_getcredits', array($this, 'getcredits'));
    $this->register_action('plugin.plugin_manager_buycredits', array($this, 'buycredits'));
    $this->register_action('plugin.plugin_manager_compress', array($this, 'compress'));

    $this->include_script('plugin_manager_fixes.js');
    if(isset($this->admins[$rcmail->user->data['username']]) && ($rcmail->task == 'settings' || $rcmail->config->get('plugin_manager_show_myrc_messages', false))){
      $skin = $rcmail->config->get('skin');
      if(!file_exists($this->home . '/skins/' . $skin . '/plugin_manager_update.css')){
        $skin = 'classic';
      }
      $this->require_plugin('http_request');
      $httpConfig['method']     = 'GET';
      $httpConfig['target']     = $this->svn . '?_action=plugin.plugin_server_pmversion';
      $httpConfig['timeout']    = '2';
      $http = new MyRCHttp();
      $http->initialize($httpConfig);
      if(ini_get('safe_mode') || ini_get('open_basedir')){
        $http->useCurl(false);
      }
      $http->execute();
      if($http->error){
        $this->mirror = str_replace('https://', 'http://', $this->mirror);
        $this->svn = str_replace('https://', 'http://', $this->svn);
        $this->billingurl = str_replace('https://', 'http://', $this->billingurl);
        $httpConfig['target'] = $this->svn . '?_action=plugin.plugin_server_pmversion';
        $http->initialize($httpConfig);
        $http->execute();
      }
      if(!$http->error){
        $response = $http->result;
        $temp = explode('|', $response, 2);
        if($response == 'error'){
          $this->admins = array();
          if(!($this->api->output instanceof rcmail_output_json)){
            $this->include_stylesheet('skins/' . $skin . '/plugin_manager_update.css');
            $this->api->output->add_footer(html::tag('div', array('class' => 'myrcerror myrcmessage'), html::tag('span', null, $this->gettext('myrcerror'))));
          }
        }
        else if(self::$version != $temp[0]){
          if(self::$version < $temp[1]){
            $rcmail->session->remove('pm_update_message');
          }
          if(!$_SESSION['pm_update_message'] || ($rcmail->task == 'settings' && $rcmail->action == 'plugin.plugin_manager_update')){
            if(self::$version < $temp[1]){
              $this->admins = array();
              $this->delay = 500000;
              if(!($this->api->output instanceof rcmail_output_json)){
                $this->include_stylesheet('skins/' . $skin . '/plugin_manager_update.css');
                $this->api->output->add_footer(html::tag('div', array('class' => 'updatepmrequired myrcmessage', 'onclick' => 'document.location.href="' . slashify($this->svn) . '?_action=plugin.plugin_server_get_pm"; $(this).hide("slow");'),
                  html::tag('span', null, $this->gettext('updatepmrequired')) .
                  ((strpos($skin, 'litecube') !== false) ? '&nbsp;' : html::tag('br')) .
                  html::tag('span', array('style' => 'text-decoration:underline;'), $this->gettext('downloadnow'))
                ));
              }
            }
            else{
              $this->delay = 30000;
              $_SESSION['pm_update_message'] = true;
              if(!($this->api->output instanceof rcmail_output_json)){
                $this->include_stylesheet('skins/' . $skin . '/plugin_manager_update.css');
                $this->api->output->add_footer(html::tag('div', array('class' => 'updatepm myrcmessage', 'onclick' => 'document.location.href="' . slashify($this->svn) . '?_action=plugin.plugin_server_get_pm"; $(this).hide("slow");'),
                  html::tag('span', null, $this->gettext('updatepm')) .
                  ((strpos($skin, 'litecube') !== false) ? '&nbsp;' : html::tag('br')) .
                  html::tag('span', array('style' => 'text-decoration:underline;'), $this->gettext('downloadnow'))
                ));
              }
            }
          }
        }
      }
      else{
        $this->delay = 8000;
        $this->admins = array();
        if(!($this->api->output instanceof rcmail_output_json)){
          $this->include_stylesheet('skins/' . $skin . '/plugin_manager_update.css');
          $this->api->output->add_footer(html::tag('div', array('class' => 'myrcerror myrcmessage'), html::tag('span', null, $this->gettext('myrcerror'))));
        }
      }
      $httpConfig['target'] = $this->svn . '?_action=plugin.plugin_server_branches';
      $http->initialize($httpConfig);
      $http->execute();
      if($http->error){
        $httpConfig['target'] = $this->svn . '?_action=plugin.plugin_server_branches';
        $http->initialize($httpConfig);
        $http->execute();
      }
      if(!$http->error){
        if($branches = unserialize($http->result)){
          $this->dev = $branches['dev'];
          $this->stable = $branches['stable'];
        }
      }
      if(!$_SESSION['pm_update_message'] && $_SESSION['user_id'] && $rcmail->task != 'logout' && !get_input_value('_framed', RCUBE_INPUT_GPC)){
        $httpConfig['target'] = $this->svn . '?_action=plugin.plugin_server_motd';
        $http->initialize($httpConfig);
        $http->execute();
        if($http->error){
          $httpConfig['target'] = $this->svn . '?_action=plugin.plugin_server_motd';
          $http->initialize($httpConfig);
          $http->execute();
        }
        if(!$http->error){
          if($http->result != ''){
            $this->delay = 30000;
            $_SESSION['pm_update_message'] = true;
            if(RCMAIL_VERSION < '0.9'){
              if(!($this->api->output instanceof rcube_json_output)){
                $this->include_stylesheet('skins/' . $skin . '/plugin_manager_update.css');
                $this->api->output->add_footer(html::tag('div', array('class' => 'motd myrcmessage'),
                  html::tag('span', null, $http->result)
                ));
              }
            }
            else{
              if(!($this->api->output instanceof rcmail_output_json)){
                $this->include_stylesheet('skins/' . $skin . '/plugin_manager_update.css');
                $this->api->output->add_footer(html::tag('div', array('class' => 'motd myrcmessage'),
                  html::tag('span', null, $http->result)
                ));
              }
            }
          }
        }
      }
    }
    if(count($this->admins) == 0 && 
      ($_SERVER['QUERY_STRING'] == '_task=settings&_action=edit-prefs&_section=plugin_manager_update&_framed=1' ||$_SERVER['QUERY_STRING'] == '_task=settings&_action=edit-prefs&_section=plugin_manager_customer&_framed=1')
    ){
      $rcmail->output->add_script('parent.location.href="./?_task=settings"', 'docready');
    }
    
    /* uninstall requests */
    /* google_contacts */
    $this->register_action('plugin.google_contacts_uninstall', array($this, 'google_contacts_uninstall'));
    /* automatic_addressbook */
    $this->register_action('plugin.automatic_addressbook_uninstall', array($this, 'automatic_addressbook_uninstall'));

    $this->rcmail = $rcmail;
    $this->plugins = $this->rcmail->config->get('plugins', array());
    $this->host = strtolower($_SERVER['HTTP_HOST']);
    if($_SESSION['global_alias']){
      $temparr = explode('@', $_SESSION['global_alias']);
      $this->domain = strtolower($temparr[1]);
    }
    else{
      $temparr = explode('@', $_SESSION['username']);
      $this->domain = strtolower($temparr[1]);
    }
    if($this->domain == ''){
      $host = rcube_parse_host($this->rcmail->config->get('default_host'));
      if($host == 'localhost'){
        $host = $_SERVER['HTTP_HOST'];
      }
      $this->domain = $host;
    }
    $this->merge_config();
    $deferred = array();
    foreach($this->config as $sections => $section){
      foreach($section as $plugin => $props){
       if(isset($this->config[$sections][$plugin])){
          if($props['active']){
            $load = true;
            if(is_array($props['hosts']) && count($props['hosts'] > 0)){
              $load = false;
              foreach($props['hosts'] as $host){
                if($this->host == strtolower($host)){
                  $load = true;
                  break;
                }
              }
            }
            if($this->domain){
              if($props['domain'] === true){
                $load = true;
              }
              else if(is_array($props['domains']) && count($props['domains'] > 0)){
                $load = false;
                foreach($props['domains'] as $domain){
                  if($this->domain == strtolower($domain)){
                    $load = true;
                    break;
                  }
                }
              }
            }
            if(is_array($props['skins'])){
              $props['skins'] = array_flip($props['skins']);
              if(!isset($props['skins'][$this->rcmail->config->get('skin', 'classic')])){
                $load = false;
              }
            }
            if($load){
              if($props['browser']){
                if(!$browser)
                  $browser = new rcube_browser();
                eval($props['browser']);
                if($test){
                  if($props['defer']){
                    $deferred[] = $plugin;
                  }
                  else{
                    $this->require_plugin($plugin);
                  }
                }
              }
              else if($props['defer']){
                $deferred[] = $plugin;
              }
              else{
                $this->require_plugin($plugin);
              }
            }
          }
          else{
            if($props['eval']){
              if(!is_array($props['eval'])){
                $eval = array($props['eval']);
              }
              else{
                $eval = $props['eval'];
              }
              foreach($eval as $code){
                eval($code);
              }
            }
          }
        }
      }
    }
    foreach($deferred as $plugin){
      $this->require_plugin($plugin);
    }
  }
  
  function plugin_manager_dummy(){

  }
  
  static public function about($keys = false){
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
    $rcmail_config = array();
    if(is_string(self::$config_dist)){
      if(is_file($file = INSTALL_PATH . 'plugins/' . self::$plugin . '/' . self::$config_dist))
        include $file;
      else{
        if($plugin)
          write_log('errors', self::$plugin . ': ' . self::$config_dist . ' is missing!');
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
    if(is_array(self::$prefs))
      $ret['config'] = array_merge($rcmail_config, array_flip(self::$prefs));
    else
      $ret['config'] = $rcmail_config;
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
  
  function plugins($plugins){
    unset($plugins['abort']);
    $conf = $this->rcmail->config->get('plugin_manager_defaults', array());
    foreach($conf as $section){
      foreach($section as $plugin => $props){
        if($props['protected']){
          if($props['active']){
            $plugins[] = $plugin;
          }
        }
        else{
          $plugins[] = $plugin;
        }
      }
    }
    $plugs = $plugins;
    foreach($plugins as $key => $plugin){
      $this->require_plugin($plugin);
      if(method_exists($plugin, 'about')){
        /* PHP 5.2.x workaround for $plugin::about() */
        $class = new $plugin(false);
        $about = $class->about();
        /*
        $name = $about['plugin'];
        $vers = $about['version'];
        $date = date('Y-m-d', strtotime($about['date']));
        if($about['licence'] == 'GPL'){
          $dl = '';
          $package = file_get_contents(slashify($this->svn) . 'plugins/plugin_server/package.gpl');
        }
        else{
          if($plugin == 'plugin_manager'){
            $dl = "\r\n  <uri>http://myroundcube.com/myroundcube-plugins/plugin-manager</uri>\r\n  ";
            $package = file_get_contents(slashify($this->svn) . 'plugins/plugin_server/package.arr');
          }
          else if($plugin == 'global_config'){
            $dl = "\r\n  <uri>http://myroundcube.com/myroundcube-plugins/global_config-plugin</uri>\r\n  ";
            $package = file_get_contents(slashify($this->svn) . 'plugins/plugin_server/package.arr');
          }
          else{
            $dl = '';
            $package = file_get_contents(slashify($this->svn) . 'plugins/plugin_server/package.arr');
          }
        }
        $repl = array('##PLUGIN##', '##DATE##', '##VERSION##', '##DOWNLOAD##');
        $replby = array($plugin, $date, $vers, $dl);
        $package = str_replace($repl, $replby, $package);
        $dirs = array(
          'c:/xampp/htdocs/mail4us.net',
          'c:/xampp/htdocs/s2.myroundcube.com',
          'c:/xampp/htdocs/svn.mail4us.net',
          'c:/xampp/htdocs/mirror.myroundcube.com',
          'c:/xampp/htdocs/dev.myroundcube.com',
        );
        foreach($dirs as $dir){
          file_put_contents($dir . '/plugins/' . $plugin . '/package.xml', $package);
        }
        */
        $requirements = $about['requirements'];
        foreach($requirements as $requirement => $props){
          if($requirement == 'required_plugins'){
            foreach($props as $plugin => $method){
              if($method['method'] == 'require_plugin'){
                $plugs[] = $plugin;
              }
            }
          }
        }
      }
    }
    return $plugs;
  }
  
  function render_page($p){
    $this->template = $p['template'];
    if(!get_input_value('_framed', RCUBE_INPUT_GET)){
      $this->rcmail->output->add_script('window.setTimeout("$(\'.myrcmessage\').hide(\'slow\');", ' . $this->delay . ')', 'docready');
      switch($p['template']){
        case 'settings':
        case 'addressbook':
        case 'mail':
          $p['content'] = str_replace('skins/' . $this->rcmail->config->get('skin', 'classic') . '/watermark.html', 'plugins/plugin_manager/skins/' . $this->rcmail->config->get('skin', 'classic') . '/myroundcube.html', $p['content']);
          $this->rcmail->output->set_env('blankpage', 'plugins/plugin_manager/skins/' . $this->rcmail->config->get('skin', 'classic') . '/myroundcube.html');
      }
    }
    if($this->rcmail->task == 'mail'){
      $this->rcmail->output->add_script('$("#messagebody").css("margin", "10px");', 'docready');
    }
    return $p;
  }
  
  function send_page($p){
    $temp = explode('.', $this->template);
    $plugin = $temp[0];
    if(count($temp == 2)){
      if(class_exists($plugin) && method_exists($plugin, 'about')){
        /* PHP 5.2.x workaround for $plugin::about() */
        $class = new $plugin(false);
        $about = $class->about(array('version', 'date'));
        $comment = '<!-- Plugin: ' . $temp[0] . ', Version: ' . $about['version'] . ' - ' . date('Y-m-d', strtotime($about['date'])) . ', Template: ' . $temp[1] . '.html -->';
        $temp = explode('<head', $p['content'], 2);
        $p['content'] = $temp[0] . $comment . "\r\n<head" . $temp[1];
      }
    }
    return $p;
  }
  
  function merge_config(){
    $this->config = $this->rcmail->config->get('plugin_manager_defaults', array());
    if($this->rcmail->user->ID && $this->rcmail->task != 'logout'){
      $active = $this->rcmail->config->get('plugin_manager_active', array());
    }
    else{
      $active = $this->rcmail->config->get('plugin_manager_unauth', array());
    }
    foreach($this->config as $sections => $section){
      foreach($section as $plugin => $props){
        if(in_array($plugin, $this->plugins)){
          $branch = $this->mirror;
          if(RCMAIL_VERSION > '0.7')
            $branch = $this->svn;
          $error = html::tag('h3', null, 'ERROR - Plugin Manager Center - Branch: ' . $branch . ' (Roundcube v' . RCMAIL_VERSION . ')<hr />') .
            html::tag('p', null, 'Misconfiguration: Unregister <b>' . $plugin . '</b> in ./config/main.inc.php or in plugin_manager configuration.') .
            html::tag('p', null, 'Either you register a plugin in main.inc.php or you register it in plugin_manager configuration. You can\'t do both.<hr />');
          die($error);
        }
        if(isset($active[$plugin])){
          $overwrite = $active[$plugin];
        }
        else{
          $overwrite = $props['active'];
        }
        if($props['protected']){
          $overwrite = $props['active'];
          if(is_array($props['protected'])){
            foreach($props['protected'] as $domain){
              if($domain == $this->domain){
                $overwrite = $props['active'];
                break;
              }
              else{
                $overwrite = $active[$plugin];
              }
            }
          }
        }
        $this->config[$sections][$plugin]['active'] = $overwrite;
      }
    }
  }
  
  function redirect(){
    $user = $_SESSION['username'];
    if(isset($_SESSION['global_alias']))
      $user = $_SESSION['global_alias'];
    $admins = $this->admins;
    if(isset($admins[strtolower($user)]) || strtolower($this->get_demo($_SESSION['username'])) == strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
      if($this->rcmail->config->get('plugin_manager_show_updates', false)){
        header('Location: ./?_task=settings&_action=plugin.plugin_manager_update&_warning=1');
        exit;
      }
    }
  }
  
  function navigation(){
    if($section = get_input_value('_section', RCUBE_INPUT_GPC)){
      $this->rcmail->output->add_script("$(document).ready(function(){ rcmail.addEventListener('init', function(){ rcmail.sections_list.select('" . $section . "') }); })", 'foot');
    }
  }
  
  function google_contacts_uninstall(){
    if($this->rcmail->user->ID){
      $db_table = $this->rcmail->config->get('db_table_collected_contacts');
      $query = "DELETE FROM $db_table WHERE user_id=?";
      $this->rcmail->db->query($query, $this->rcmail->user->ID);
    }
  }
  
  function automatic_addressbook_uninstall(){
    if($this->rcmail->user->ID){
      $db_table = $this->rcmail->config->get('db_table_google_contacts');
      $query = "DELETE FROM $db_table WHERE user_id=?";
      $this->rcmail->db->query($query, $this->rcmail->user->ID);
    }
  }
  
  function uninstall(){
    $uninstall = get_input_value('_uninstall', RCUBE_INPUT_POST);
    $config = unserialize($this->rcmail->user->data['preferences']);
    $response = '';
    foreach($this->config as $sections => $section){
      foreach($section as $plugin => $props){
        if($plugin == $uninstall){
          if($props['uninstall_request']){
            if(is_array($props['uninstall_request'])){
              if(strtolower($props['uninstall_request']['method']) == 'post'){
                $response = 'rcmail.http_post(';
              }
              else{
                $response = 'rcmail.http_request(';
              }
              $params = '';
              if($props['uninstall_request']['params'])
                $params = $props['uninstall_request']['params'];
              $response .= '"' . $props['uninstall_request']['action'] . '", "' . $params .'");';
            }
          }
          if(is_array($props['uninstall'])){
            foreach($props['uninstall'] as $prop){
              if(is_string($prop)){
                unset($config[$prop]);
              }
            }
          }
          else if($props['uninstall'] === true){
            if(method_exists($plugin, 'about')){
              /* PHP 5.2.x workaround for $plugin::about() */
              $class = new $plugin(false);
              $about = $class->about();
              if(is_array($about['config'])){
                foreach($about['config'] as $prop => $val){
                  if(is_string($prop)){
                    unset($config[$prop]);
                  }
                }
              }
            }
          }
          $a_user_prefs = $config;
          $config = serialize($config);
          $this->rcmail->db->query(
            "UPDATE ".get_table_name('users').
            " SET preferences = ?".
                ", language = ?".
            " WHERE user_id = ?",
            $config,
            $_SESSION['language'],
            $this->rcmail->user->ID
          );
          if($this->rcmail->db->affected_rows() !== false){
            $this->rcmail->config->set_user_prefs($a_user_prefs);
            $this->rcmail->data['preferences'] = $config;
            if(isset($_SESSION['preferences'])){
              $this->rcmail->session->remove('preferences');
              $this->rcmail->session->remove('preferences_time');
            }
          }
          break;
        }
      }
    }
    $this->rcmail->output->command('plugin.plugin_manager_success', $response);
  }
  
  function transfer(){
    $this->register_handler('plugin.body', array($this, 'transfer_html'));
    $user = $_SESSION['username'];
    if(isset($_SESSION['global_alias']))
      $user = $_SESSION['global_alias'];
    $admins = $this->admins;
    if(isset($admins[strtolower($user)]) || strtolower($this->get_demo($_SESSION['username'])) == strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
      $this->rcmail->output->send('plugin_manager.transfer');
    }
  }
  
  function transfer_html(){
    $customer_id = $this->rcmail->config->get('customer_id');
    if(isset($_POST['_from']) && isset($_POST['_to']) && isset($_POST['_amount'])){
      $dest = get_input_value('_to', RCUBE_INPUT_POST);
      $amount = get_input_value('_amount', RCUBE_INPUT_POST);
      $alphanum = 'a-z0-9';
      $alpha = '0-9';
      if(strlen($dest) < 32){
        $this->rcmail->output->show_message($this->gettext('invalid_customer_id'), 'error');
      }
      else if(strlen($dest) != preg_replace("/[^$alphanum]/i", '', strlen($dest))){
        $this->rcmail->output->show_message($this->gettext('invalid_customer_id'), 'error');
      }
      else if(strlen($amount) != preg_replace("/[^$alpha]/", '', strlen($amount))){
        $this->rcmail->output->show_message($this->gettext('invalid_credits'), 'error');
      }
      else{
        $httpConfig['method']     = 'POST';
        $httpConfig['target']     = $this->svn . '?_action=plugin.plugin_server_transfer';
        $httpConfig['timeout']    = '30';
        $httpConfig['params']     = array('_customer_id' => $customer_id, '_to' => $dest, '_amount' => $amount, '_ip' => $this->getVisitorIP());
        $http = new MyRCHttp();
        $http->initialize($httpConfig);
        if(ini_get('safe_mode') || ini_get('open_basedir')){
          $http->useCurl(false);
        }
        $http->execute();
        if($http->error){
          $this->rcmail->output->show_message($this->gettext('errorsaving'), 'error');
        }
        $response = $http->result;
        if($response == 'ok'){
          $this->rcmail->output->show_message($this->gettext('successfully_transferred'), 'confirmation');
        }
        else{
          $this->rcmail->output->show_message($this->gettext('errorsaving'), 'error');
        }
      }
    }
    $credits = $this->getcredits(false);
    $row = html::tag('td', array('class' => 'title'), $this->gettext('from') . ':') . html::tag('td', null, html::tag('td', null, html::tag('input', array('name' => '_from', 'size' => 32, 'readonly' => 'readonly', 'value' => $customer_id)) . html::tag('td', array('class' => 'title'), '(' . $this->gettext('customer_id') . ')')));
    $rows = html::tag('tr', null, $row);
    $row = html::tag('td', array('class' => 'title'), $this->gettext('to') . ':') . html::tag('td', null, html::tag('td', null, html::tag('input', array('name' => '_to', 'size' => 32, 'value' => $dest ? $dest : '')) . html::tag('td', array('class' => 'title'), '(' . $this->gettext('customer_id') . ')')));
    $rows .= html::tag('tr', null, $row);
    $row = html::tag('td', array('class' => 'title'), 'MyRC$:') . html::tag('td', null, html::tag('td', null, html::tag('input', array('name' => '_amount', 'size' => 3, 'value' => $credits)) . html::tag('td', array('class' => 'title'), '(' . 'MyRC$&nbsp;' . html::tag('span', array('id' =>'cdl'), $credits) . '&nbsp;' . $this->gettext('credits') . ')')));
    $rows .= html::tag('tr', null, $row);
    $content = html::tag('table', null, $rows);
    $content .= html::tag('br') . html::tag('input', array('type' => 'submit', 'value' => $this->gettext('transfer'), 'class' => 'button mainaction'));
    $content .= '&nbsp;' . html::tag('input', array('type' => 'button', 'value' => $this->gettext('cancel'), 'class' => 'button', 'onclick' => 'document.location.href="./?_task=settings&_action=edit-prefs&_section=plugin_manager_customer&_framed=1"'));
    $fieldset = html::tag('fieldset', null, html::tag('legend', null, $this->gettext('transfer')) . $content);
    $out = html::tag('form', array('action' => './?_task=settings&_action=plugin.plugin_manager_transfer&_framed=1', 'method' => 'post'), $fieldset);
    return $out;
  }
  
  function update(){
    $this->register_handler('plugin.body', array($this, 'update_html'));
    $user = $_SESSION['username'];
    if(isset($_SESSION['global_alias']))
      $user = $_SESSION['global_alias'];
    $admins = $this->admins;
    if(isset($admins[strtolower($user)]) || strtolower($this->get_demo($_SESSION['username'])) == strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
      $this->rcmail->output->send('plugin');
    }
  }
  
  function update_html(){
    $hl = get_input_value('_hl', RCUBE_INPUT_GET);
    $branch = get_input_value('_branch', RCUBE_INPUT_GET);
    if($branch == 'dev'){
      $this->mirror = $this->svn;
    }
    if($hl && $hl != $_SESSION['language']){
      $this->rcmail->load_language($hl);
      $this->add_texts('localization', false);
    }
    $this->include_script('plugin_manager_update.js');
    $this->rcmail->output->add_label(
      'plugin_manager.noupdates',
      'plugin_manager.showall',
      'plugin_manager.hideuptodate'
    );
    $skin = $this->rcmail->config->get('skin');
    if(!file_exists($this->home . '/skins/' . $skin . '/plugin_manager.css')) {
      $skin = "larry";
    }
    $this->include_stylesheet('skins/' . $skin . '/plugin_manager.css');
    $plugins = array_flip($this->rcmail->config->get('plugins', array()));
    $dtp = $this->rcmail->config->get('plugin_manager_third_party_plugins', array());
    $sections = $this->rcmail->config->get('plugin_manager_defaults', array());
    foreach($sections as $section => $plugs){
      foreach($plugs as $plug => $props){
        $plugins[$plug] = $props;
      }
    }
    $scope = array();
    foreach($plugins as $plugin => $props){
      $this->require_plugin($plugin);
      if(method_exists($plugin, 'about')){
        /* PHP 5.2.x workaround for $plugin::about() */
        $class = new $plugin(false);
        $p = $class->about();
        //$p = $plugin::about();
        $scope[$plugin] = array('version'=>$p['version'], 'date'=>$p['date']);
        if(is_array($p['requirements']['required_plugins'])){
          foreach($p['requirements']['required_plugins'] as $required => $val){
            $this->require_plugin($required);
            $p = $val['plugin'];
            if(method_exists($required, 'about')){
              /* PHP 5.2.x workaround for $plugin::about() */
              $class = new $required(false);
              $p = $class->about();
            }
            if(is_array($p)){
              $scope[$required] = array('version'=>$p['version'], 'date'=>$p['date']);
            }
            else{
              if($dtp[$required]){
                $scope[$required] = $dtp[$plugin];
              }
              else{
                $scope[$required] = 'unknown';
              }
            }
          }
        }
      }
      else{
        if($dtp[$plugin]){
          $scope[$plugin] = $dtp[$plugin];
        }
        else{
          $scope[$plugin] = 'unknown';
        }
      }
    }
    $user = $_SESSION['username'];
    if(isset($_SESSION['global_alias']))
      $user = $_SESSION['global_alias'];
    $temparr = explode('@', $user);
    if(count($temparr) == 1){
      $host = rcube_parse_host($this->rcmail->config->get('default_host'));
      if($host == 'localhost'){
        $host = $_SERVER['HTTP_HOST'];
      }
      $user = $user . '@' . $host;
    }
    if(get_input_value('_warning', RCUBE_INPUT_GET)){
      $stablechecked = 'checked';
      $devchecked = '';
      $host = $this->mirror;
      if(RCMAIL_VERSION > $this->stable){
        $stablechecked = '';
        $devchecked = 'checked';
        $host = $this->svn;
      }
      $warning = html::tag('h3', null, 'Fairness is our Mission!') . 'If you proceed the following data will be submitted to our Server (' . html::tag('span', array('id' => 'mirrorhost'), $host) . ') and saved in our Databases.';
      $form = html::tag('ul', null,
        html::tag('li', null, '_admin: ' . $user) .
        html::tag('li', null, '_hl: ' . $_SESSION['language']) .
        html::tag('li', null, '_customer_id: ' . $this->rcmail->config->get('customer_id')) .
        html::tag('li', null, '_plugins:') 
      );
      $EMAIL_PATTERN = '([a-z0-9][a-z0-9\-\.\+\_]*@[^&@"\'.][^@&"\']*\\.([^\\x00-\\x40\\x5b-\\x60\\x7b-\\x7f]{2,}|xn--[a-z0-9]{2,}))';
      $display = 'none';
      if(preg_match('/' . $EMAIL_PATTERN . '/i', $user)){
        $display = 'block';
      }
      $out = '<br />' . html::tag('div',
        array('style' => 'opacity: 0.85;text-align: center; margin-left: auto; margin-left: auto; margin-right: auto; width: 600px; padding: 15px; background-color: #F7FDCB; border: 1px solid #C2D071;'),
          $warning . html::tag('div', array('style' => "display:$display"),
          html::tag('div', array('style' => 'text-align: right; margin-right: 150px;'),
            html::tag('span', null, 'Yes, please send me MyRoundcube Newsletters') . '&nbsp;' . html::tag('input', array('type' => 'checkbox', 'name' => '_newsletter', 'id' => 'newsletter', 'value' => 1)) .
            '<br />' . html::tag('span', null, 'Download plugins for Roundcube ' . $this->dev) . '&nbsp;' . html::tag('input', array('class' => 'branch', 'onclick' =>'$("#mirrorhost").html("' . $this->svn . '")', 'type' => 'radio', 'checked' => $devchecked, 'name' => '_branch', 'id' => 'devbranch', 'value' => 'dev')) .
            '<br />' . html::tag('span', null, 'Download plugins for Roundcube ' . $this->stable) . '&nbsp;' . html::tag('input', array('class' => 'branch', 'onclick' =>'$("#mirrorhost").html("' . $this->mirror . '")', 'type' => 'radio', 'checked' => $stablechecked, 'name' => '_branch', 'id' => 'stablebranch', 'value' => 'stable'))
          ) .
          html::tag('div', array('style' => 'display:none;', 'id' => 'newletterdetails'), '<br />' . html::tag('span', null, 'First Name:&nbsp;') . html::tag('input', array('type' => 'text', 'name' => '_firstname', 'id' => 'firstname', 'maxlength' => 30)) . '<br /><br />' .
          html::tag('span', null, 'Last Name:&nbsp;') . html::tag('input', array('type' => 'text', 'name' => '_lastnamename', 'id' => 'lastname', 'maxlength' => 30)))) .
          '<br /><br />' . html::tag('a', array('href' => './?_task=settings&_action=plugin.plugin_manager_update', 'onclick' => 'return news(this);', 'target' => '_self'), 'I agree') . '&nbsp;|&nbsp;' .
          html::tag('a', array('href' => '#', 'onclick' => 'document.location.href="./?_task=settings&_action=plugin.plugin_manager_iframe"'), "I disagree")
      );
      $out .= html::tag('div', array('style' => 'margin-left: auto; margin-right: auto; width: 600px; padding: 15px;'), $form);
      ksort($scope);
      $out .= html::tag('div', array('style' => 'margin-left: auto; margin-right: auto; width: 900px;'), html::tag('center', null, html::tag('textarea', array('cols' => 90, 'rows' => 20, 'disabled' => true), print_r($scope, true))));
      $this->rcmail->output->add_script('$(document).ready(function(){$("#tabsbar").hide()});');
      return $out;
    }
    $this->require_plugin('http_request');
    if(get_input_value('_newsletter', RCUBE_INPUT_GET) == 1 && strtolower($this->get_demo($_SESSION['username'])) != strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
      $params = array('_hl' => $_SESSION['language'], '_admin' => $user, '_plugins' => serialize($scope), '_newsletter' => get_input_value('_newsletter', RCUBE_INPUT_GET), '_firstname' => get_input_value('_firstname', RCUBE_INPUT_GET), '_lastname' => get_input_value('_lastname', RCUBE_INPUT_GET));
    }
    else{
      $params = array('_hl' => $_SESSION['language'], '_admin' => $user, '_plugins' => serialize($scope));
    }
    $host = $this->mirror;
    $branch = get_input_value('_branch', RCUBE_INPUT_GET);
    if($branch == 'dev'){
      $host = $this->svn;
    }
    $httpConfig['method']     = 'POST';
    $httpConfig['target']     = $host . '?_action=plugin.plugin_server_mirror';
    $httpConfig['timeout']    = '30';
    $httpConfig['params']     = array_merge($params, array('_customer_id' => $this->rcmail->config->get('customer_id')));
    $http = new MyRCHttp();
    $http->initialize($httpConfig);
    if(ini_get('safe_mode') || ini_get('open_basedir')){
      $http->useCurl(false);
    }
    $http->execute();
    if($http->error){
      return html::tag('div',
        array('style' => 'opacity: 0.85; text-align: center; margin-left: auto; margin-right: auto; width: 600px; padding: 8px 10px 8px 46px; background: url(./skins/classic/images/display/icons.png) 6px -97px no-repeat; background-color: #EF9398; border: 1px solid #DC5757;'),
          $this->gettext('connectionerror') . '<br /><br />' . html::tag('a', array('href' => './?_task=settings&_action=plugin.plugin_manager_update', 'target' => '_self'), $this->gettext('trylater')));
    }
    $response = $http->result;
    if(!$server = unserialize($response)){
      return html::tag('div',
        array('style' => 'opacity: 0.85; text-align: center; margin-left: auto; margin-right: auto; width: 600px; padding: 8px 10px 8px 46px; background: url(./skins/classic/images/display/icons.png) 6px -97px no-repeat; background-color: #EF9398; border: 1px solid #DC5757;'),
          $this->gettext('connectionerror') . '<br /><br />' . html::tag('a', array('href' => './?_task=settings&_action=plugin.plugin_manager_update', 'target' => '_self'), $this->gettext('trylater')));
    }
    $mirror_rc = $server['roundcube'];
    $mirror = $server['scope'];
    $merge = array();
    foreach($dtp as $plugin => $props){
      if(!isset($mirror[$plugin])){
        $merge[$plugin] = $dtp[$plugin];
      }
    }
    ksort($merge);
    $mirror = array_merge($mirror, $merge);
    $temp = $mirror;
    unset($mirror['plugin_manager']);
    $ret = array();
    $ret['plugin_manager'] = $temp['plugin_manager'];
    foreach($mirror as $plugin => $props){
      $ret[$plugin] = $mirror[$plugin];
    }
    $mirror = $ret;
    $update = array();
    if(is_array($mirror)){
      foreach($mirror as $plugin => $props){
        if(is_array($props)){
          if($scope[$plugin] && $props['version']){
            if($props['version'] > $scope[$plugin]['version']){
              $update[$plugin] = $scope[$plugin];
              $update[$plugin]['notinstalled'] = false;
            }
          }
          else{
            $update[$plugin] = $props;
            $update[$plugin]['notinstalled'] = true;
          }
        }
        else{
          $update[$plugin] = $scope[$plugin];
          $update[$plugin]['notinstalled'] = false;
        }
      }
    }
    $checked = false;
    foreach($update as $plugin => $props){
      if(is_array($props)){
        $checked = true;
        break;
      }
    }
    include './program/localization/index.inc';
    $options = '';
    ksort($rcube_languages);
    foreach($rcube_languages as $abbr => $lang){
      $options .= html::tag('option', array('title' => $lang, 'selected' => ($_SESSION['language'] == $abbr)?true:false, 'value' => $abbr), $abbr); 
    }
    $select = html::tag('select', array('onchange' => 'document.location.href="./?_task=settings&_action=plugin.plugin_manager_update&_branch=dev&_hl=" + this.value'), $options);
    $thead = html::tag('tr', null,
      html::tag('th', array('width' => '220px'), $this->gettext('plugin')) .
      html::tag('th', array('width' => '150px'), $this->gettext('mirrorversion')) .
      html::tag('th', array('width' => '150px'), $this->gettext('serverversion')) .
      html::tag('th', array('width' => '90px', 'title' => $this->gettext('language')), $select) .
      html::tag('th', array('width' => '90px'), html::tag('a', array('href' => 'http://code.google.com/p/myroundcube/issues/list', 'target' => '_new'), $this->gettext('issue'))) .
      html::tag('th', array('width' => '30px', 'title' => $this->gettext('hideuptodate')), html::tag('input', array('type' => 'checkbox', 'id' => 'updatetoggle'))) .
      html::tag('th', array('width' => '30px'), html::tag('input', array('id' => 'toggle', 'title' => $this->gettext('toggle'), 'type' => 'checkbox', 'checked' => $checked))) .
      html::tag('th', null, $this->gettext('comments'))
    );
    $tbody1 = '';
    $tbody2 = '';
    $cdlcredits = $server['credits'];
    $cdlprice = 0;
    foreach($mirror as $plugin => $props){
      if($plugin == 'plugin_server')
        continue;
      $nr = false;
      if(is_array($props) && $props['version']){
        $stat = 'ok';
        $comment = '';
        $append = '';
        if($update[$plugin]){
          $stat = 'update';
        }
        if($props['lr']){
          if(file_exists(INSTALL_PATH . 'plugins/' . $plugin . '/localization/revision.inc.php')){
            $ps_localization_update = false;
            $A = false;
            include INSTALL_PATH . 'plugins/' . $plugin . '/localization/revision.inc.php';
            if(!$ps_localization_update){
              $ps_localization_update = $A;
            }
            if($ps_localization_update != $props['lr'] && $props['version'] == $scope[$plugin]['version']){
              $stat = 'update';
              $comment .= $this->gettext('languageupdate') . "<br /><font color='red'>" . $this->gettext('localizationfilesonly') . "</font>\r\n";
            }
          }
          /*else{
            $stat = 'update';
            $comment .= $this->gettext('languageupdate') . "\r\n";
          }*/
        }
        if($props['roundcube']){
          if($props['roundcube'] > RCMAIL_VERSION){
            $stat = 'error';
          }
        }
        if($props['license']){
          $license = $this->gettext('terms') . ": " . html::tag('a', array('href' => $this->svn . '?_action=plugin.plugin_server_license&_plugin=' . $plugin, 'target' => '_new', 'title' => $this->gettext('view')), $props['license']);
        }
        else{
          $license = false;
        }
        if($props['comments']){
          $props['comments'] = $this->gettext('authors_comments') . ': ' . $this->comment2ul($props['comments']);
        }
        $comment .= nl2br($props['comments']);
        $pmsv = $scope[$plugin]['version'];
        $t = explode('-', $pmsv);
        $pmsv = $t[0];
        $pmcv = $props['version'];
        $t = explode('-', $pmsc);
        $pmsc = $t[0];
        $tmsv = explode('.', $pmsv);
        $tmcv = explode('.', $pmcv);
        foreach($tmsv as $tmsvk => $tmsvp){
          while(strlen($tmsvp) < $this->vlength){
            $tmsvp = '0'. $tmsvp;
          }
          $tmsv[$tmsvk] = $tmsvp;
        }
        foreach($tmcv as $tmcvk => $tmcvp){
          while(strlen($tmcvp) < $this->vlength){
            $tmcvp = '0'. $tmcvp;
          }
          $tmcv[$tmcvk] = $tmcvp;
        }
        $s = implode('.', $tmsv);
        $p = implode('.', $tmcv);
        if($p < $s && is_numeric(substr($scope[$plugin]['version'],0,1))){
          $stat = 'error';
          $comment = $this->gettext('servernewer');
        }
        else if(!is_numeric(substr($scope[$plugin]['version'],0,1))){
          if(is_dir(INSTALL_PATH . 'plugins/' . $plugin) && $plugin != 'dblog' && $this->require_plugin($plugin)){
            if(method_exists($plugin, 'about')){
              /* PHP 5.2.x workaround for $plugin::about() */
              $class = new $plugin(false);
              $arr = $class->about(array('version'));
              $scope[$plugin]['version'] = $arr['version'];
              $pmsv = $scope[$plugin]['version'];
              $t = explode('-', $pmsv);
              $pmsv = $t[0];
              $tmsv = explode('.', $pmsv);
              foreach($tmsv as $tmsvk => $tmsvp){
                while(strlen($tmsvp) < $this->vlength){
                  $tmsvp = '0'. $tmsvp;
                }
                $tmsv[$tmsvk] = $tmsvp;
              }
              $s = implode('.', $tmsv);
              if($p == $s){
                $nr = true;
              }
            }
            else{
              $scope[$plugin]['version'] = 'unknown';
            }
          }
          else{
            $scope[$plugin]['version'] = 'unknown';
          }
          if($comment != ''){
            $stat = 'edit';
          }
          else{
            $stat = 'update';
          }
        }
        else if($p > $s && $comment != ''){
          $stat = 'edit';
        }
        else if($p > $s){
          $stat = 'update';
        }
        else if (is_array($update[$plugin]) && $stat != 'error'){
          $comment = $this->gettext('justunzip') . '<br />' . html::tag('a', array('href' => $this->guide, 'target' => '_new'), $this->gettext('guide'));;
        }
        $roundcube = '';
        if($props['roundcube']){
          $roundcube = 'Roundcube Version: ' . $props['roundcube'] . ' ' . $this->gettext('orhigher') . "\r\n";
        }
        $php = '';
        if($props['PHP']){
          $php = 'PHP: ' . $props['PHP'] . "\r\n";
          $phpversion = phpversion();
          $temparr = explode('-', $phpversion);
          if($props['PHP'] >= $temparr[0]){
            $stat = 'error';
          }
        }
        $required_plugins = '';
        if(is_array($props['requires'])){
          $required_plugins = $this->gettext('requires') . ':<br />';
          foreach($props['requires'] as $key => $val){
            $method = '&sup2';
            if($val['method'] && $val['method'] == 'require_plugin'){
              $method = '&sup1';
            }
            $required_plugins .= '-&nbsp;'.html::tag('a', array('href' => '#' . $key, 'class' => 'anchorLink'), $key) . $method . '<br />';
          }
          $required_plugins = substr($required_plugins, 0, strlen($required_plugins) - 2) . "\r\n";
        }
        $recommended_plugins = '';
        if(is_array($props['recommended'])){
          $recommended_plugins = $this->gettext('recommended') . ':<br />';
          foreach($props['recommended'] as $key => $val){
            $recommended_plugins .= '-&nbsp;'.html::tag('a', array('href' => '#' . $key, 'class' => 'anchorLink'), $key) . '&sup2<br />';
          }
          $recommended_plugins = substr($recommended_plugins, 0, strlen($recommended_plugins) - 2) . "\r\n";
        }
        if(is_array($props['required'])){
          $requiredby = '';
          foreach($props['required'] as $key){
            $requiredby .= '-&nbsp;'.html::tag('a', array('href' => '#' . $key, 'class' => 'anchorLink'), $key) . '<br />';
          }
          $requiredby = substr($requiredby, 0, strlen($requiredby) - 2) . "\r\n";
          $comment = $this->gettext('requiredby') . ':<br />' . $requiredby . "\r\n" . $comment;
        }
        $temparr = explode("\r\n", $roundcube . $php . $required_plugins . $recommended_plugins . $comment);
        $comments = '';
        foreach($temparr as $r){
          if($r)
            $comments .= html::tag('li', null, $r);
        }
        if($comments != ''){
          $changelog = html::tag('li', null, html::tag('a', array('href' => $this->svn . '?_action=plugin.plugin_server_changelog&_plugin=' . $plugin, 'target' => '_new', 'title' => $this->gettext('view')), 'CHANGELOG'));
          $comment = html::tag('ul', array('class' =>'pm_update'), ($license ? html::tag('li', null, $license) : '') . $changelog . $comments);
        }
        if($update[$plugin]['notinstalled']){
          if(is_dir('./plugins/' . $plugin)){
            $serverversion = html::tag('td', null, $this->gettext('notregistered'));
          }
          else{
            $serverversion = html::tag('td', null, $this->gettext('notinstalled'));
          }
        }
        else{
          $content = ($update[$plugin]?$update[$plugin]['version']:$scope[$plugin]['version']) . ' - ' . ($update[$plugin]?date($this->rcmail->config->get('date_format', 'm-d-Y'), strtotime($scope[$plugin]['date'])):date($this->rcmail->config->get('date_format', 'm-d-Y'), strtotime($scope[$plugin]['date'])));
          if(substr($content, 0, 1) == 'u'){
            $serverversion = html::tag('td', null, $this->gettext('unknown'));
          }
          else{
            $serverversion = html::tag('td', null, $content);
          }
        }
        $translation = html::tag('td', array('align' => 'center'), '--');
        $user = $_SESSION['username'];
        if(isset($_SESSION['global_alias']))
          $user = $_SESSION['global_alias'];
        if($mirror[$plugin]['lc'] !== false){
          $host = $_SESSION['storage_host'];
          if($host == 'localhost')
            $host = $_SERVER['SERVER_ADDR'];
          if(!$host)
            $host = $_SERVER['HTTP_HOST'];
          $host = ($_SESSION['storage_ssl']?'ssl://':'') . $host . ':' . $_SESSION['storage_port'];
          $translation = html::tag('td', array('align' => 'right', 'title' => $plugin . ' :: ' . $this->gettext('translate') . '...'), html::tag('a', array('href' => $this->mirror . '?_action=plugin.plugin_server_translate&_hl=' . $_SESSION['language'] . '&_plugin=' . $plugin . '&_translator=' . $user . '&_host=' . $host . '&_port=' . $this->rcmail->config->get('default_port'), 'target' => '_new'), ($mirror[$plugin]['lc'] * 100)) . ' %');
        }
        $db = $this->rcmail->config->get('db_dsnw');
        $db = parse_url($db);
        $db = $db['scheme'];
        $onclick = '';
        if(strtolower($this->get_demo($user)) == strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
          $onclick = 'return false';
        }
        $dlprice = 0;
        if($p > $s || substr($s, 0, 5) == '0000u'){
          $dlprice = $mirror[$plugin]['prices'][0];
          $background = 'lightgreen';
          if(is_dir(INSTALL_PATH . 'plugins/' . $plugin) && $plugin != 'dblog' && $this->require_plugin($plugin)){
            $dlprice = $mirror[$plugin]['prices'][1];
            $v = explode('.', $scope[$plugin]['version']);
            $mv = explode('.', $mirror[$plugin]['version']);
            if(($v[1] == 0 && count($mv) == 2) || $v[0] < $mv[0]){
              $dlprice = $mirror[$plugin]['prices'][1];
              $background = 'lightblue';
            }
            else if($v[0] == $mv[0] && $v[1] < $mv[1]){
              $dlprice = $mirror[$plugin]['prices'][2];
              $background = 'yellow';
            }
            else{
             if(method_exists($plugin, 'about')){
                $dlprice = 0;
                $background = 'none';
              }
            }
          }
        }
        if(!$dlprice){
          $background = 'none';
        }
        if($nr){// && $scope[$plugin]['version'] 
          $stat = 'ok';
        }
        $cdlprice = $cdlprice + $dlprice;
        $prices  = html::tag('td', array('style' => "background: lightgreen", 'title' => $this->gettext('initialdownload')), $mirror[$plugin]['prices'][0]);
        $prices .= html::tag('td', array('style' => "background: lightblue", 'title' => $this->gettext('keyfeatureaddition')), $mirror[$plugin]['prices'][1]);
        $prices .= html::tag('td', array('style' => "background: yellow", 'title' => $this->gettext('codeimprovements')), $mirror[$plugin]['prices'][2]);
        $prices .= html::tag('td', null, '&rArr;');
        $prices .= html::tag('td', array('style' => "background: " . $background, 'title' => 'MyRC$ ' . $dlprice), 'MyRC$&nbsp;' . html::tag('span', array('id' => 'pmdlp_' . $plugin), $dlprice));
        $checked = (is_array($update[$plugin]) && !$update[$plugin]['notinstalled'] && $stat != 'error' || $stat == 'edit' || $stat == 'update')?true:false;
        if(substr($plugin, 0, 6) == 'hmail_'){
          if(!$this->rcmail->config->get('pmhmail')){
            $checked = false;
            $cdlprice = $cdlprice - $dlprice;
          }
        }
        $notinstalled = '';
        if(substr($scope[$plugin]['version'], 0, 1) == 'u'){
          $notinstalled = 'notinstalled ';
        }
        $tbody1 .= html::tag('tr', null,
          html::tag('td', array('id' => 'pmu_' . $plugin, 'title' => $mirror[$plugin]['description']), html::tag('a', array('name' => '#' . $plugin, 'class' => 'anchorLink'), '&nbsp;') . $plugin . '&nbsp;' . html::tag('small', array('title' => $mirror[$plugin]['count'] . '&nbsp;' . $this->gettext('downloads')), '(' . $mirror[$plugin]['count'] . ')') . ($dlprice ? '<br />' . html::tag('table', null, html::tag('tr', null, html::tag('td', null, 'MyRC$') . $prices)) : '')) .
          html::tag('td', array('style' => 'background:' . $background), $props['version'] . ' - ' . date($this->rcmail->config->get('date_format', 'm-d-Y'), strtotime($props['date']))) .
          $serverversion .
          $translation .
          html::tag('td', array('align' => 'center', 'title' => $plugin . ' :: ' . $this->gettext('submitissue')), html::tag('a', array('onclick' => $onclick, 'href' => 'http://code.google.com/p/myroundcube/issues/entry?summary=[' . $plugin . '] - Enter one-line summary&comment=Token:%20' . $server['token'] . "%20(Don't modify this token!)%0AVersion:%20" . $scope[$plugin]['version'] . " (" . $scope[$plugin]['date'] . ")%0APHP:%20" . phpversion() . '%0ARCMAIL:%20' . RCMAIL_VERSION . '%0ADatabase:%20' . $db . '%0ASERVER:%20' . $_SERVER['SERVER_SOFTWARE'] . '%0A----%0AI.%20%20Issue%20Description:%0A%0AII.%20Steps to reproduce the Issue:%0A1.%0A2.%0A3.', 'target' => '_new'), $this->gettext('issue'))) .
          html::tag('td', array('class' => $stat, 'title' => $plugin . ' :: ' . $this->gettext('update_' . $stat)), '&nbsp;') .
          html::tag('td', array('align' => 'center'), html::tag('input', array('class' => 'chbox ' . $notinstalled . ($dlprice ? 'costs' : 'free'), 'value' => $plugin . '|' . ($scope[$plugin]['version'] ? $scope[$plugin]['version'] : '0'), 'type' => 'checkbox', 'checked'=> $checked, 'disabled' => (is_array($update[$plugin]) && $stat != 'error' || $stat == 'edit' || $stat == 'update')?false:true, 'name' => '_plugins[]', 'id' => 'chbox_' . $plugin, null))) .
          html::tag('td', array('title' => $comment?($plugin . ' :: ' . $comment):''), $comment . $append)
        );
      }
      else{
        if(is_array($mirror[$plugin])){
          if(is_array($mirror[$plugin]['required'])){
            $comments = '';
            if($mirror[$plugin]['comments']){
              $comments = html::tag('li', null, $mirror[$plugin]['comments']);
            }
            $requiredby = '';
            foreach($mirror[$plugin]['required'] as $key){
              $requiredby .= '-&nbsp;'.html::tag('a', array('href' => '#' . $key, 'class' => 'anchorLink'), $key) . '<br />';
            }
            $requiredby = substr($requiredby, 0, strlen($requiredby) - 2) . "\r\n";
            $mirror[$plugin]['comments'] = html::tag('ul', array('class' =>'pm_update'), $comments . html::tag('li', null, $this->gettext('requiredby') . ':<br />' . $requiredby));
          }
          $tbody2 .= html::tag('tr', null,
            html::tag('td', array('id' => 'pmu_' . $plugin, 'title' => $mirror[$plugin]['description']), html::tag('a', array('name' => '#' . $plugin), '&nbsp;') . $plugin) .
            html::tag('td', array('title' => $plugin . ' :: ' . html::tag('a', array('href' => $mirror[$plugin]['download'], 'target' => '_new'), $this->gettext('develsite')), 'colspan' => 2), ($mirror[$plugin] != 'unknown')?html::tag('a', array('href' => $mirror[$plugin]['download'], 'target' => '_new'), $mirror[$plugin]['download']):$this->gettext($mirror[$plugin])) .
            html::tag('td', array('align' => 'center'), '--') .
            html::tag('td', array('align' => 'center'), '--') .
            html::tag('td', array('align' => 'center', 'class' => 'thirdparty'), '--') .
            html::tag('td', array('align' => 'center'), html::tag('input', array('title' => $plugin . ' :: ' . $this->gettext('thirdpartywarning'), 'class' => 'chbox', 'name' => '_plugins[]', 'value' => $plugin, 'type' => 'checkbox', 'checked'=> false))) .
            html::tag('td', array('title' => $mirror[$plugin]['comments']?($plugin . ' :: ' . $mirror[$plugin]['comments']):''), $mirror[$plugin]['comments'])
          );
        }
        else{
          // ToDo: if link is missing: http://www.google.de/search?q=roundcube+fileapi_attachments
          $tbody2 .= html::tag('tr', null,
            html::tag('td', array('id' => 'pmu_' . $plugin), '&nbsp;' . $plugin) .
            html::tag('td', array('colspan' => 2), ($mirror[$plugin] != 'unknown')?html::tag('a', array('href' => $mirror[$plugin], 'target' => '_new', 'title' => $mirror[$plugin]), $mirror[$plugin]):$this->gettext($mirror[$plugin])) .
            html::tag('td', array('align' => 'center'), '--') .
            html::tag('td', array('align' => 'center'), '--') .
            html::tag('td', array('align' => 'center'), '--') .
            html::tag('td', array('align' => 'center'), html::tag('input', array('title' => $plugin . ' :: ' . $this->gettext('thirdpartywarning'), 'class' => 'chbox', 'value' => $plugin, 'type' => 'checkbox', 'checked'=> false))) .
            html::tag('td', null,'&nbsp;')
          );
        }
      }
    }
    $boxtitle = html::tag('div', array('id' => 'prefs-title-right'), $this->gettext('plugin_manager_center'));
    $rctitle = 'rc_ok';
    $rcclass = 'rcok';
    $append = html::tag('span', array('class' => 'vmatch'), '&nbsp;'.$this->gettext('rc_uptodate').'&nbsp;');
    if($mirror_rc > RCMAIL_VERSION){
      $rctitle = 'rc_update';
      $rcclass = 'rcupdate';
      $append = html::tag('span', array('class' => 'vupdate'), '&nbsp;'.$this->gettext('rc_update').'&nbsp;') . '&nbsp;&raquo;&nbsp;' . html::tag('a', array('href' => $this->rcurl, 'target' => '_new'), $this->gettext('roundcubeurl')) . '&nbsp;';
    }
    else if($mirror_rc < RCMAIL_VERSION){
      $rctitle = 'rc_newer';
      $rcclass = 'rcerror';
      $append = html::tag('span', array('class' => 'vmismatch'), '&nbsp;'.sprintf($this->gettext('nottested'), RCMAIL_VERSION).'&nbsp;');
    }
    $rctitle = $this->gettext($rctitle);
    $mirrorh = parse_url($this->mirror);
    $db = $this->rcmail->config->get('db_dsnw');
    $db = parse_url($db);
    $db = $db['scheme'];
    $web = 'http' . ($_SERVER['HTTPS']?'s':'') . '://'. $_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];
    $user = $_SESSION['username'];
    if(isset($_SESSION['global_alias']))
      $user = $_SESSION['global_alias'];
    if(is_numeric($cdlcredits) && is_numeric($cdlprice)){
      $remaining = $cdlcredits - $cdlprice;
    }
    else{
      $remaining = 0;
    }
    $link = html::tag('a', array('href' => './?_task=settings&_action=preferences&_buynow=1'), $this->gettext('customer_account'));
    $hmchecked = '';
    $otherchecked = 'checked';
    if($this->rcmail->config->get('pmhmail')){
      $hmchecked = 'checked';
      $otherchecked = '';
    }
    $hmail = html::tag('span', null, 'IMAP-Server:&nbsp;') .
      html::tag('input', array('type' => 'radio', 'name' => 'hmailbackend', 'id' => 'yhmail', 'checked' => $hmchecked, 'onclick' => 'pm_hmail(false)')) . html::tag('span', null, html::tag('a', array('href' => 'http://www.hmailserver.com/', 'target' => '_new'), 'hMailserver') . '&nbsp;') .
      html::tag('input', array('type' => 'radio', 'name' => 'hmailbackend', 'id' => 'nhmail', 'checked' => $otherchecked, 'onclick' => 'pm_hmail(true)')) . html::tag('span', null, 'other');
    $credits = html::tag('div', array('style' => 'display:block; border:1px solid lightgrey; background:lightyellow; padding:2px 2px 2px 2px; width:99%;'), '&nbsp;' . $hmail . '&nbsp;&rArr;&nbsp;MyRC$ ' .
      html::tag('span', array('id' => 'cdlcredits'), ($cdlcredits ? $cdlcredits : 0)) . ' (' . $this->gettext('credits') . ') &minus; MyRC$ ' . html::tag('span', array('id' => 'cdlprice'), $cdlprice) . '&nbsp(' . $this->gettext('forthisdownload') . ') = ' . 'MyRC$ ' . html::tag('span', array('id' => 'cdlremaining'), $remaining) . ($remaining > 0 ? '&nbsp;(' . $this->gettext('remainingcredits') . ')' : '&nbsp;(' . $link . ')')
    );
    $controls = html::tag('div', array('style' => 'display: inline; float: right; margin-right: 5px;'), html::tag('a', array('id' => 'buycreditslink', 'href' => './?_task=settings&_action=plugin.plugin_manager_buycredits', 'target' => '_new'), $this->gettext('buynow'))) .
      html::tag('div', array('style' => 'display: inline; float: right;'), html::tag('a', array('href' => '#', 'onclick' => 'pm_discard()'), $this->gettext('discardliabletopaycosts')) . '&nbsp;|&nbsp;' . html::tag('a', array('href' => '#', 'onclick' => 'pm_notinstalled()'), $this->gettext('unchecknotinstalledplugins')) . '&nbsp;|&nbsp;');
    $zipbutton = $credits . html::tag('br') . html::tag('input', array('type' => 'submit', 'class' => 'button mainaction', 'value' => $this->gettext('ziparchive'))) . $controls;
    /*$paypalbutton = html::tag('form', array('action' => "https://www.paypal.com/cgi-bin/webscr", 'method' => "post", 'target' => '_new'),
                      html::tag('input', array('type' => "hidden", 'name' => "cmd",  'value' => "_s-xclick")) .
                      html::tag('input', array('type' => "hidden", 'name' => "hosted_button_id", 'value' => "37WMD9TBQXRNG")) .
                      html::tag('input', array('type' => "image",  'src' => "https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif",  'border' => "0", 'name' => "submit", 'alt' => "PayPal - The safer, easier way to pay online!")) .
                      html::tag('img', array('alt' => "", 'border' => "0", 'src' => "https://www.paypalobjects.com/en_US/i/scr/pixel.gif", 'width' => "1", 'height' => "1"))
                    );*/
    if(strtolower($this->get_demo($user)) == strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
      $zipbutton = $zipbutton = html::tag('input', array('type' => 'button', 'class' => 'button mainaction', 'value' => $this->gettext('demoaccount')));;
    }
    $formcontent  = html::tag('div', array('id' => 'rcheader'), '<br />Roundcube:&nbsp;' . $this->gettext('serverversion') . '&nbsp;' . RCMAIL_VERSION . '&nbsp;&raquo;&nbsp;' . $this->gettext('mirrorversion') . '&nbsp;' . $mirror_rc . '&nbsp;&raquo;&nbsp;' . $append . '<hr />');
    $formcontent .= html::tag('p', null);
    $formcontent .= html::tag('div', array('id' => 'table-container', 'style' => 'height:0px; overflow:auto; overflow-x:hidden; margin-right:10px;'), html::tag('table', array('id' => 'table', 'border' => 0, 'cellspacing' => 0, 'cellpadding' => 0), html::tag('thead', null, $thead) . html::tag('tbody', null, $tbody1 . $tbody2)));
    $formcontent .= html::tag('div', array('id' => 'update_footer'), html::tag('p', null, null) .
      $zipbutton .
      html::tag('input', array('type' => 'button', 'onclick' => 'document.location.href="./?_task=settings&_action=plugin.plugin_manager_iframe"', 'class' => 'button', 'value' => $this->gettext('cancel'))) .
      html::tag('br') . html::tag('div', array('class' => 'asterix'), '&sup1;' . $this->gettext('donotregister') . '<br />&sup2;' . $this->gettext('register')) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_price', 'id' => 'pm_price', 'value' => '##placeholder##')) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_customer_id', 'value' => $this->rcmail->config->get('customer_id'))) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_admin', 'value' => $user)) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_serveradmin', 'value' => $_SERVER['SERVER_ADMIN'])) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_newsletter', 'value' => get_input_value('_newsletter', RCUBE_INPUT_GPC))) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_branch', 'value' => get_input_value('_branch', RCUBE_INPUT_GPC))) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_firstname', 'value' => urldecode(get_input_value('_firstname', RCUBE_INPUT_GPC)))) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_lastname', 'value' => urldecode(get_input_value('_lastname', RCUBE_INPUT_GPC)))) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_self', 'value' => $web)) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_serverip', 'value' => $server['ip'])) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_servername', 'value' => $_SERVER['SERVER_NAME'])) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_serverport', 'value' => $_SERVER['SERVER_PORT'])) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_serverprotocol', 'value' => $_SERVER['SERVER_PROTOCOL'])) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_serversoftware', 'value' => $_SERVER['SERVER_SOFTWARE'])) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_rcmail', 'value' => RCMAIL_VERSION)) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_version', 'value' => self::$version)) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_db', 'value' => $db)) .
      html::tag('input', array('type' => 'hidden', 'name' => '_pm_php', 'value' => phpversion())) .
      html::tag('p', null, null)
    );
    $formfooter = html::tag('div', array('id' => 'formfooter'), html::tag('div', array('class' => 'footerleft'), html::tag('form', array('name' => 'form', 'onsubmit' => 'return pmf();', 'method' => 'post', 'action' => $this->mirror . '?_action=plugin.plugin_server_request&_hl=' . $_SESSION['language']), $formcontent)));
    $this->rcmail->output->add_label(
      'plugin_manager.creditsupdated'
    );
    $paypalbutton = html::tag('a', array('href' => 'http://myroundcube.com/#contact', 'target' => '_new'), 'MyRoundcube ' . $this->gettext('support'));
    $this->rcmail->output->add_script('if(screen.width < 1300){$(".pm_update").html("..."); $("#settings-sections").hide();$("#pluginbody").css("left", "5px")}', 'docready');
    return html::tag('div', array('id' =>'prefs-box', 'style' => 'width: 100%; overflow: auto;'), $boxtitle . $formfooter) . html::tag('div', array('id' => 'paypalcontainer'), html::tag('div', array('id' => 'paypal'), $paypalbutton));
  }
  
  function getcredits($ajax = true){
    $this->require_plugin('http_request');
    $params = array('_customer_id' => $this->rcmail->config->get('customer_id'));
    $httpConfig['method']     = 'POST';
    $httpConfig['target']     = str_replace('buycredits', 'getcredits', $this->billingurl);
    $httpConfig['timeout']    = '30';
    $httpConfig['params']     = $params;
    $http = new MyRCHttp();
    $http->initialize($httpConfig);
    if(ini_get('safe_mode') || ini_get('open_basedir')){
      $http->useCurl(false);
    }
    $http->execute();
    if($http->error){
      $response = false;
    }
    else{
      $response = $http->result;
    }
    if($response == '-0'){
      unset($httpConfig['params']);
      $httpConfig['method']     = 'GET';
      $httpConfig['target']    .= '&_customer_id=' . $this->rcmail->config->get('customer_id');
      $http->initialize($httpConfig);
      if(ini_get('safe_mode') || ini_get('open_basedir')){
        $http->useCurl(false);
      }
      $http->execute();
      if($http->error){
        $response = false;
      }
      else{
        $response = $http->result;
      }
    }
    if($ajax)
      $this->rcmail->output->command('plugin.plugin_manager_getcredits', $response);
    else
      return $response;
  }
  
  function buycredits(){
    $this->require_plugin('http_request');
    $params = array('_customer_id' => $this->rcmail->config->get('customer_id'));
    $httpConfig['method']     = 'POST';
    $httpConfig['target']     = $this->billingurl;
    $httpConfig['timeout']    = '30';
    $httpConfig['params']     = $params;
    $http = new MyRCHttp();
    $http->initialize($httpConfig);
    if(ini_get('safe_mode') || ini_get('open_basedir')){
      $http->useCurl(false);
    }
    $http->execute();
    if($http->error){
      $this->rcmail->output->send('plugin_manager.error');
    }
    else{
      $url = explode('?', $this->billingurl, 2);
      $url = slashify($url[0]);
      $page = $http->result;
      $page = str_replace('href="plugins/', 'href="' . $url . 'plugins/', $page);
      $page = str_replace('src="skins/', 'src="' . $url . 'skins/', $page);
      $page = str_replace('<img src="plugins/', '<img src="' . $url . 'plugins/', $page);
      $page = str_replace('<script type="text/javascript" src="plugins/', '<script type="text/javascript" src="' . $url . 'plugins/', $page);
      send_nocacheing_headers();
      echo $page;
    }
    exit;
  }
  
  function getnew(){
    $this->require_plugin('http_request');
    $params = array('_customer_id' => $this->rcmail->config->get('customer_id'), '_ip' => $this->getVisitorIP());
    $httpConfig['method']     = 'POST';
    $httpConfig['target']     = $this->svn . '?_action=plugin.plugin_server_customer_id_new';
    $httpConfig['timeout']    = '30';
    $httpConfig['params']     = $params;
    $http = new MyRCHttp();
    $http->initialize($httpConfig);
    if(ini_get('safe_mode') || ini_get('open_basedir')){
      $http->useCurl(false);
    }
    $http->execute();
    if($http->error){
      $response = false;
    }
    else{
      $response = $http->result;
    }
    if(is_string($response) && strlen($response) == 32){
      $a_prefs['customer_id'] = $response;
      $this->rcmail->user->save_prefs($a_prefs);
    }
    else{
      unset($httpConfig['params']);
      $httpConfig['method']     = 'GET';
      $httpConfig['target']    .= '&_customer_id=' . $this->rcmail->config->get('customer_id') . '&_ip=' . $this->getVisitorIP();
      $http->initialize($httpConfig);
      if(ini_get('safe_mode') || ini_get('open_basedir')){
        $http->useCurl(false);
      }
      $http->execute();
      if($http->error){
        $response = false;
      }
      else{
        $response = $http->result;
      }
      if(is_string($response) && strlen($response) == 32){
        $a_prefs['customer_id'] = $response;
        $this->rcmail->user->save_prefs($a_prefs);
      }
    }
    header('Location: ./?_task=settings&_action=edit-prefs&_section=plugin_manager_customer&_framed=1');
    exit;
  }
  
  function settings_link($args){
    $args['list']['plugin_manager'] = array(
      'id'      => 'plugin_manager',
      'section' => $this->gettext('plugin_manager_title')
    );
    $user = $_SESSION['username'];
    if(isset($_SESSION['global_alias']))
      $user = $_SESSION['global_alias'];
    if($_SESSION['username'] == $this->rcmail->user->data['username']){
      foreach($this->admins as $admin => $idx){
        if((strpos($admin, '@') !== false && strpos($user, '@') !== false) || (strpos($admin, '@') === false && strpos($user, '@') === false)){
          // ok
        }
        else if($this->rcmail->config->get('plugin_manager_admins')){
          $branch = $this->mirror;
          if(RCMAIL_VERSION > '0.7')
            $branch = $this->svn;
          $error = html::tag('h3', null, 'ERROR - Plugin Manager Center - Branch: ' . $branch . ' (Roundcube v' . RCMAIL_VERSION . ')<hr />') .
            html::tag('p', null, 'Plugin Manager detected a misconfiguration.<br />Register plugin manager admins as follows:<br /><i>$rcmail_config["plugin_manager_admins"] = array("' . $_SESSION['username'] . '");</i>');
          die($error);
        }
        break;
      }
    }
    $admins = $this->admins;
    if(isset($admins[strtolower($user)]) || strtolower($this->get_demo($_SESSION['username'])) == strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
      $args['list']['plugin_manager_update'] = array(
        'id'      => 'plugin_manager_update',
        'section' => $this->gettext('submenuprefix') . $this->gettext('update_plugins')
      );
      if(!$this->rcmail->config->get('customer_id')){
        $customer_id = $this->getCustomerID();
        if(is_string($customer_id) && strlen($customer_id) == 32){
          $a_prefs['customer_id'] = $customer_id;
          $this->rcmail->user->save_prefs($a_prefs);
        }
      }
      $args['list']['plugin_manager_customer'] = array(
        'id'      => 'plugin_manager_customer',
        'section' => $this->gettext('submenuprefix') . $this->gettext('customer_account')
      );
      $customer_id = $this->rcmail->config->get('customer_id');      
      if($_GET['_buynow'] || !$customer_id){
        if(!$customer_id){
          $customer_id = $this->getCustomerID();
          $arr['customer_id'] = $customer_id;
          $this->rcmail->user->save_prefs($arr);
          $this->rcmail->output->add_script('rcmail.display_message("' . $this->gettext('getnew') . '", "notice");', 'docready');
        }
        $this->rcmail->output->add_script('rcmail.sections_list.select("plugin_manager_customer");', 'docready');
      }
    }
    return $args;
  }
  
  function settings($args){
    if($args['section'] == 'plugin_manager'){
      $this->include_script('plugin_manager.js');
      $skin = $this->rcmail->config->get('skin');
      if(!file_exists($this->home . '/skins/' . $skin . '/plugin_manager.css')) {
        $skin = "classic";
      }
      $this->include_stylesheet('skins/' . $skin . '/plugin_manager.css');
      $args['blocks']['plugin_manager'] =  array(
        'options' => array(),
        'name'    => $this->gettext('plugin_manager_title')
      );
      if(in_array(strtolower($_SESSION['username']), array_flip($this->admins))){
        $error = '';
        $content = html::tag('input', array('type' => 'button', 'value' => 'Download missing Plugins', 'onclick' => 'document.location.href="./?_task=settings&_action=plugin.plugin_manager_update&_warning=1"'));
        foreach($this->config as $section => $plugins){
          foreach($plugins as $plugin => $props){
            if(!file_exists(INSTALL_PATH . 'plugins/' . $plugin . '/' . $plugin . '.php')){
              $conf = array($plugin => $props);
              $error .= html::tag('li', null, 'Section: <b>' . $section . '</b> Plugin: <b>' . $plugin . '</b>' .html::tag('pre', null, var_export($conf, true)));
            }
          }
        }
        if($error != ''){
          $error = html::tag('h3', null, 'ERROR - Plugin Manager Center - Branch: ' . $branch . ' (Roundcube v' . RCMAIL_VERSION . ')<hr />') .
            html::tag('p', null, 'Plugin Manager detected plugins in plugin_manager configuration which are not installed on the server.<br /><br />Remove these plugins from plugin_manager configuration or download and install them properly.') .
            html::tag('ul', null, $error);
          $div = html::tag('div', null, $content);
          $error .= '<hr />' . $div . '<hr />';
          die($error);
        }
      }
      $this->merge_config();
      $content = '';
      $restore = array();
      $display_section = array();
      foreach($this->config as $section => $props) {
        if(count($props) > 0){
          $li = array();
          foreach($props as $plugin => $prop){
            $show = true;
            if($this->domain){
              if(is_array($prop['domains']) && count($prop['domains'] > 0)){
                $show = false;
                foreach($prop['domains'] as $domain){
                  if($this->domain == $domain){
                    $show = true;
                    break;
                  }
                }
              }
              if(is_array($prop['hosts']) && count($prop['hosts'] > 0)){
                $show = false;
                foreach($prop['hosts'] as $host){
                  if($this->host == strtolower($host)){
                    $show = true;
                    break;
                  }
                }
              }
              if($prop['protected']){
                if($prop['protected'] === true){
                  $show = false;
                }
                else if(is_string($prop['protected'])){
                  if($this->rcmail->config->get($prop['protected'])){
                    $show = false;
                  }
                  else{
                    $show = true;
                  }
                }
                else if(is_array($prop['protected'])  && count($prop['protected']) > 0){
                  foreach($prop['protected'] as $domain){
                    if($this->domain == strtolower($domain)){
                      $show = false;
                      break;
                    }
                  }
                }
              }
              if($prop['browser']){
                $show = false;
                if(!$browser)
                  $browser = new rcube_browser();
                eval($prop['browser']);
                if($test){
                  $show = true;
                }
              }
              if(is_array($prop['skins'])){
                $prop['skins'] = array_flip($prop['skins']);
                if(!isset($prop['skins'][$this->rcmail->config->get('skin', 'classic')])){
                  $show = false;

                }
              }
            }
            if($show){
              $display_section[$section] = true;
              $defaults = $this->rcmail->config->get('plugin_manager_defaults', array());
              $restore[$plugin] = array($plugin, $defaults[$section][$plugin]['active']);
              if($user[$section][$plugin]){
                $prop = $user[$section][$plugin];
              }
              if(is_array($prop['buttons'])){
                $this->rcmail->output->set_env('pm_buttons_' . $plugin, $prop['buttons']);
                $this->rcmail->output->set_env('pm_plugin_' . $plugin, $prop['active']);
              }
              $fconfig = 'fsavedialog';
              if($prop['config']){
                $fconfig = 'fconfig';
              }
              $funinstall = '';
              if($prop['uninstall']){
                $funinstall = 'funinstall';
              }
              $frequest = '';
              if($prop['uninstall_request']){
                if($prop['uninstall_force']){
                  $frequest = 'frequestforce';
                }
                else{
                  $frequest = 'frequest';
                }
              }

              $input = new html_checkbox(array('name' => '_plugin_manager_' . $plugin, 'class' => trim($fconfig . ' ' . $funinstall . ' ' . $frequest), 'value' => 1, 'id' => 'pm_chbox_' . $plugin));
              if(substr($this->labels($prop['label_name']), 0, 1) == '[' && substr($this->labels($prop['label_name']), strlen($this->labels($prop['label_name'])) - 1) == ']'){
                if(!is_dir('./plugins/' . $plugin)){
                  $li[$plugin].= html::tag('li', array('class' => '_plugin_manager_li', 'id' => 'pmid_' . html::tag('i', null, $plugin)), html::tag('input', array('type' => 'checkbox', 'disabled' => 'true')) . html::tag('span', null, '&nbsp;' . html::tag('i', null, $plugin) . '&nbsp;' . html::tag('font', array('color' => 'red'), '(' . $this->gettext('notinstalled') . ')')));
                }
              }
              else{
                $li[$this->labels($prop['label_name'])].= html::tag('li', array('class' => 'plugin_manager_li', 'id' => 'pmid_' . $plugin), $input->show($prop['active']?1:0) . html::tag('span', null, '&nbsp;' . $this->labels($prop['label_name'])));
              }
              if($prop['label_name']){
                $this->rcmail->output->add_script('rcmail.add_label({"' . $plugin . '.pluginname":"' . $this->labels($prop['label_name']) . '"});');
              }
              if($prop['label_description']){
                $s = '';
                if(is_array($prop['label_inject'])){
                  switch($prop['label_inject'][0]){
                    case 'string':
                      $s = $prop['label_inject'][1];
                      break;
                    case 'config':
                      $s = $this->rcmail->config->get($prop['label_inject'][1]);
                      break;
                    case 'session':
                      $s = $_SESSION($prop['label_inject'][1]);
                      break;
                    case 'eval':
                      eval($prop['label_inject'][1]);
                      break;
                  }
                }
                $this->rcmail->output->add_script('rcmail.add_label({"' . $prop['label_description'] . '":"' . $this->labels($prop['label_description'], $s) . '"});');
              }
            }
            else{
              $input = new html_hiddenfield(array('name' => '_plugin_manager_' . $plugin, 'id' => 'pm_chbox_' . $plugin, 'value' => $prop['active']?1:0));
              $li[$this->labels($prop['label_name'])].= $input->show();
            }
          }
          if($display_section[$section] && count($li) > 0){
            ksort($li);
            $li = implode('', $li);
            $content .= html::tag('div', array('id' => 'pm_section_' . $section, 'class' => 'pm_section'), html::tag('fieldset', array('class' => 'pm_fieldset'), html::tag('legend', array('class' => 'title'), $this->labels($section)) . html::tag('ul', array('id' => 'pm_' . $section, 'class' => 'plugin_manager_ul'), $li)));
          }
        }
      }
      if($content != ''){
        $args['blocks']['plugin_manager']['options'][0] = array(
          'title'   => '',
          'content' => html::tag('div', array('id' => 'pm_div'), $content)
        );
        $input_restore = new html_checkbox(array('id' => 'pm_restore_defaults'));
        $input_checkall = new html_checkbox(array('id' => 'pm_check_all'));
        $input_uncheckall = new html_checkbox(array('id' => 'pm_uncheck_all'));
        $input_config = new html_hiddenfield(array('name' => '_config_plugin', 'id' => 'plugin_manager_config_plugin'));
        $update = '';
        $admins = $this->admins;
        $user = $_SESSION['username'];
        if(isset($_SESSION['global_alias']))
          $user = $_SESSION['global_alias'];
        if(isset($admins[strtolower($user)]) || strtolower($this->get_demo($_SESSION['username'])) == strtolower(sprintf($this->rcmail->config->get('demo_user_account'),""))){
          $input_update = new html_checkbox(array('id' => 'pm_update_plugins'));
          $update = '&nbsp;&nbsp;' . $this->gettext('update_plugins') . '&nbsp;' . html::tag('span', array('class' => 'pm_control'), $input_update->show(0));
        }
        if($this->rcmail->config->get('skin') == 'larry'){
          $args['blocks']['plugin_manager']['options'][1] = array(
            'title' => '',
            'content' => html::tag('hr', array('height' => '2px'))
          );
        }
        $args['blocks']['plugin_manager']['options'][2] = array(
          'title'   => '',
          'content' => $this->gettext('restoredefaults') . '&nbsp;' . html::tag('span', array('class' => 'pm_control'), $input_restore->show(0)) .
                       '&nbsp;&nbsp;' . $this->gettext('checkall') . '&nbsp;' . html::tag('span', array('class' => 'pm_control'), $input_checkall->show(0)) .
                       '&nbsp;&nbsp;' . $this->gettext('uncheckall') . '&nbsp;' . html::tag('span', array('class' => 'pm_control'), $input_uncheckall->show(0)) .
                       $input_config->show() .
                       $update .
                       html::tag('div', array('id' => 'jqdialog', 'style' => 'display: none;'))
        );
        $this->rcmail->output->set_env('pm_restore', $restore);
        $this->rcmail->output->add_label(
          'plugin_manager.furtherconfig',
          'plugin_manager.successfullydeleted',
          'plugin_manager.successfullysaved',
          'plugin_manager.errorsaving',
          'plugin_manager.uninstall',
          'plugin_manager.uninstallconfirm',
          'plugin_manager.savewarning',
          'plugin_manager.areyousure',
          'plugin_manager.yes',
          'plugin_manager.no',
          'plugin_manager.disable',
          'plugin_manager.remove'
        );
      }
      else{
        $user = $_SESSION['username'];
        if(isset($_SESSION['global_alias']))
          $user = $_SESSION['global_alias'];
        $admins = $this->admins;
        if(isset($admins[strtolower($user)])){
          $input_update = new html_checkbox(array('id' => 'pm_update_plugins'));
          $args['blocks']['plugin_manager']['options'][1] = array(
            'title'   => $this->gettext('update_plugins'),
            'content' => $input_update->show(0)
          );
        }
      }
    }
    else if($args['section'] == 'plugin_manager_update'){
      $args['blocks']['plugin_manager_update']['options'][0] = array(
        'title'   => html::tag('script', array('type' => 'text/javascript'), '$("body").hide();parent.location.href="./?_task=settings&_action=plugin.plugin_manager_update&_warning=1"'),
        'content' => ''
      );
    }
    else if($args['section'] == 'plugin_manager_customer'){
      $this->include_script('plugin_manager.js');
      $this->rcmail->output->add_label(
        'plugin_manager.creditsupdated'
      );
      $customer_id = $this->rcmail->config->get('customer_id');
      if(!$customer_id){
        $customer_id = $this->getCustomerID();
        if(is_string($customer_id) && strlen($customer_id) == 32){
          $a_prefs['customer_id'] = $customer_id;
          $this->rcmail->user->save_prefs($a_prefs);
        }
        else{
          $args['blocks']['plugin_manager_customer']['options'][0] = array(
            'title'   => $this->gettext('servicenotavailable'),
            'content' => ''
          );
          $this->rcmail->output->add_script('if(self.location.href != parent.location.href){$(".mainaction").remove()}', 'docready');
        }
      }
      if($_GET['_framed']){
        $this->require_plugin('http_request');
        $params = array('_customer_id' => $this->rcmail->config->get('customer_id'));
        $httpConfig['method']     = 'POST';
        $httpConfig['target']     = $this->svn . '?_action=plugin.plugin_server_account';
        $httpConfig['timeout']    = '30';
        $httpConfig['params']     = $params;
        $http = new MyRCHttp();
        $http->initialize($httpConfig);
        if(ini_get('safe_mode') || ini_get('open_basedir')){
          $http->useCurl(false);
        }
        $http->execute();
        $content  = $this->gettext('customer_id') . ': ' . html::tag('input', array('name' => '_customer_id', 'id' => 'customer_id', 'size' => 32, 'readonly' => 'readonly', 'value' => $customer_id)) . 
          '&nbsp;' . html::tag('a', array('href' => './?_task=settings&_action=plugin.plugin_manager_getnew', 'style' => 'font-size:11px;', 'title' => $this->gettext('getnew_hint')), $this->gettext('getnew')) .
          html::tag('br') . html::tag('br') .
          html::tag('input', array('name' => '_home', 'id' => 'home', 'type' => 'hidden', 'value' => ''));
        $this->rcmail->output->add_script('if(document.getElementById("home")){ $("#home").val(document.location.href) };', 'docready');
        if($http->error){
          $content .= html::tag('span', array('style' => 'font-weight: normal; font-size: 11px'), $this->gettext('trylater'));
        }
        else{
          $response = $http->result;
          $account = unserialize($response);
          if(is_array($account) && !$account['credits'] == '-0'){
            unset($httpConfig['params']);
            $httpConfig['method']     = 'GET';
            $httpConfig['target']    .= '&_customer_id=' . $this->rcmail->config->get('customer_id');
            $http->initialize($httpConfig);
            if(ini_get('safe_mode') || ini_get('open_basedir')){
              $http->useCurl(false);
            }
            $http->execute();
            $response = $http->result;
            $account = unserialize($response);
          }
          if(is_array($account)){
            $rows = '';
            $sum = 0;
            if(is_array($account['history'])){
              $head = html::tag('tr', array('style' => 'font-weight: bold; font-size: 12px;'),
                html::tag('td', array('style' => 'border: 2px solid lightgrey;'), $this->gettext('date')) . 
                html::tag('td', array('style' => 'border: 2px solid lightgrey;'), 'IP') .
                html::tag('td', array('style' => 'border: 2px solid lightgrey;', 'align' => 'center'), $this->gettext('download')) .
                html::tag('td', array('style' => 'border: 2px solid lightgrey;', 'align' => 'center'), $this->gettext('receipt')) .
                html::tag('td', array('style' => 'border: 2px solid lightgrey;'), 'MyRC$') .
                html::tag('td', array('style' => 'border: 2px solid lightgrey;', 'align' => 'center'), $this->gettext('plugins'))
              );
              foreach($account['history'] as $entry){
                $list = '';
                $plugins = unserialize($entry['plugins']);
                if(is_array($plugins)){
                  foreach($plugins as $plugin){
                    $list .= html::tag('li', null, $plugin[0] . '&nbsp;(' . $plugin[1] . ')');
                  }
                }
                if($entry['action'] == 'd'){
                  $dllink = $this->dlurl . $entry['dl'] . '.zip';
                  $dllabel = $this->gettext('clickhere');
                  if(substr($entry['dl'], 0, 1) == '_'){
                    $dllink = 'javascript:void(0)';
                    $dllabel = $this->gettext('expired');
                  }
                  $rows .= html::tag('tr', null,
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), str_replace(' ', '&nbsp;', date($this->rcmail->config->get('date_format', 'Y-m-d') . ' ' . $this->rcmail->config->get('time_format', 'H:i:s') . ':s', strtotime($entry['date'])))) . 
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), $entry['ip']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'center'), html::tag('a', array('href' => $dllink), $dllabel)) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'),
                      html::tag('a', array('href' => 'javascript:void(0)', 'onclick' => '$(".' . $entry['dl'] . '").show()'), $this->gettext('show')) . '&nbsp;|&nbsp;' .
                      html::tag('a', array('href' => 'javascript:void(0)', 'onclick' => '$(".' . $entry['dl'] . '").hide()'), $this->gettext('hide')) . '&nbsp;|&nbsp;' .
                      html::tag('a', array('href' => 'javascript:void(0)', 'onclick' => 'var win = window.open(); win.document.write("<pre>" + $(".' . $entry['dl'] . '").html() + "</pre>"); win.print(); win.close()'), $this->gettext('print')) .
                      html::tag('pre', array('class' => 'expand ' . $entry['dl'], 'style' => 'display: none;'), base64_decode($entry['receipt']))) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey; color: red;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'right'), $entry['myrcd']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), html::tag('ul', null, $list))
                  );
                  $sum = $sum + $entry['myrcd'];
                }
                else if($entry['action'] == 'b'){
                  $rows .= html::tag('tr', null,
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), str_replace(' ', '&nbsp;', date($this->rcmail->config->get('date_format', 'Y-m-d') . ' ' . $this->rcmail->config->get('time_format', 'H:i:s') . ':s', strtotime($entry['date'])))) . 
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), $entry['ip']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'center', 'colspan' => 2), 'MyRC$ bought - Thank you!') .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey; color: green;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'right'), '+' . $entry['myrcd']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), '&nbsp;')
                  );
                  $sum = $sum + $entry['myrcd'];
                }
                else if($entry['action'] == 'c'){
                  $rows .= html::tag('tr', null,
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), str_replace(' ', '&nbsp;', date($this->rcmail->config->get('date_format', 'Y-m-d') . ' ' . $this->rcmail->config->get('time_format', 'H:i:s') . ':s', strtotime($entry['date'])))) . 
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), $entry['ip']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'center', 'colspan' => 2), 'Customer ID changed') .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), '&nbsp;') .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), '&nbsp;')
                  );
                }
                else if($entry['action'] == 't'){
                  $rows .= html::tag('tr', null,
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), str_replace(' ', '&nbsp;', date($this->rcmail->config->get('date_format', 'Y-m-d') . ' ' . $this->rcmail->config->get('time_format', 'H:i:s') . ':s', strtotime($entry['date'])))) . 
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), $entry['ip']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'center', 'colspan' => 2), 'Credits transferred') .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey; color:' . ($entry['myrcd'] > 0 ? ' green;' : ' red;'), 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'right'), ($entry['myrcd'] > 0 ? '+' : '') . $entry['myrcd']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), '&nbsp;')
                  );
                  $sum = $sum + $entry['myrcd'];
                }
                else if($entry['action'] == 'a'){
                  $color = $entry['myrcd'] > 0 ? 'green' : 'red';
                  $rows .= html::tag('tr', null,
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), str_replace(' ', '&nbsp;', date($this->rcmail->config->get('date_format', 'Y-m-d') . ' ' . $this->rcmail->config->get('time_format', 'H:i:s') . ':s', strtotime($entry['date'])))) . 
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), $entry['ip']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'center', 'colspan' => 2), 'Account details compressed') .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey; color: ' . $color . ';', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'right'), ($entry['myrcd'] > 0 ? '+' : '') . $entry['myrcd']) .
                    html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), '&nbsp;')
                  );
                  $sum = $sum + $entry['myrcd'];
                }
              }
            }
            $free = '';
            if($account['credits'] > $sum){
              $free = html::tag('tr', null,
                html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'center'), '--') .
                html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'colspan' => 3), 'Free&nbsp;MyRC$&nbsp;granted&nbsp;-&nbsp;Enjoy!') .
                html::tag('td', array('style' => 'border: 1px solid lightgrey; color: green;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'right'), '+'. ($account['credits'] - $sum)) .
                html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top'), '&nbsp;')
              );
            }
            $rows .= html::tag('tr', null,
              html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'colspan' => 4), 'MyRC$ (' . $this->gettext('credits') . ')') . 
              html::tag('td', array('style' => 'border: 1px solid lightgrey; font-weight: bold; color: ' . ($account['credits'] > 0 ? 'green' : 'red'), 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'right'), ($account['credits'] > 0 ? '+' : '') . html::tag('span', null, $account['credits'])) .
              html::tag('td', array('style' => 'border: 1px solid lightgrey;', 'nowrap' => 'nowrap', 'valign' => 'top', 'align' => 'right'), html::tag('a', array('href' => 'javascript:document.forms.form.target="_new";document.forms.form.action="' . $this->billingurl .'";document.forms.form.submit()', 'style' => 'font-weight:normal; font-size: 11px'), str_replace(' ', '&nbsp;', $this->gettext('buynow'))))
            );
            $print = '$(".expand").show(); $("a").hide(); var content = $("#accountdetails").html(); while(content.indexOf("|") > -1){content = content.replace("|", "")}; ' .
              'var win = window.open(); win.document.write("<html><head><title>MyRoundcube ' . $this->gettext('customer_account') . ' - ' . $this->gettext('print') . '</title></head><body><table>" + content + "</table></body></html>"); ' .
              '$("a").show(); $(".expand").hide(); win.print(); win.close(); document.location.href="./?_task=settings&_action=plugin.plugin_manager_compress";';
            $content .= html::tag('fieldset', array('style' => 'border: 1px solid lightgrey; padding: 5px; margin-left: 0'),
              html::tag('legend', array('style' => 'font-weight: normal; padding-bottom: 0;'), $this->gettext('details')) .
              html::tag('ul', null,
                html::tag('li', null, html::tag('a', array('href' => 'javascript:document.forms.form.target="_new";document.forms.form.action="' . $this->billingurl .'";document.forms.form.submit()', 'style' => 'font-weight:normal; font-size: 12px'), str_replace(' ', '&nbsp;', $this->gettext('buynow')))) .
                html::tag('li', null, html::tag('a', array('href' => './?_task=settings&_action=plugin.plugin_manager_transfer&_framed=1', 'style' => 'font-weight:normal; font-size: 12px'), str_replace(' ', '&nbsp;', $this->gettext('transfer')))) .
                html::tag('li', null, html::tag('a', array('href' => 'javascript:document.forms.form.target="_new";document.forms.form.action="' . str_replace('buycredits', 'mergecredits', $this->billingurl) .'";document.forms.form.submit()', 'style' => 'font-weight:normal; font-size: 12px'), str_replace(' ', '&nbsp;', $this->gettext('merge')))) .
                html::tag('li', null, html::tag('span', array('style' => 'font-weight: bold; font-size: 12px;'), 'MyRC$ ' . ' ' . html::tag('span', array('id' => 'cdlcredits'), $account['credits']) . ' ' . html::tag('span', array('style' => 'font-weight: normal;'), '(' . $this->gettext('credits') . ')'))) . 
                html::tag('li', null, html::tag('span', array('style' => 'font-size: 12px;'), $this->gettext('history'))) . html::tag('br') .
                  html::tag('div', array('style' => 'float:left;padding:3px;'), html::tag('a', array('href' => '#', 'onclick' => 'document.location.href=document.location.href + "&_ts=' . time() . '"', 'style' => 'font-size:11px;'), $this->gettext('refresh'))) .
                  html::tag('div', array('style' => 'float:right;padding:3px;'), html::tag('a', array('href' => '#', 'onclick' => 'window.open("' . str_replace('?_task=billing&_action=buycredits', 'plugins/billing/prices.php?_ts=' . time(), $this->billingurl) . '")', 'style' => 'font-size:11px;'), $this->gettext('pricelist'))) .
                  html::tag('div', array('style' => 'float:right;padding:3px;'), html::tag('a', array('href' => '#', 'onclick' => $print, 'style' => 'font-size:11px;'), $this->gettext('printcompress')) . '&nbsp;') .
                  html::tag('table', array('id' => 'accountdetails', 'style' => 'font-weight: normal; font-size: 11px; border: 1px solid lightgrey;', 'border' => '0', 'cellpadding' => '0', 'cellspacing' => '0', 'width' => '100%'), $head . $free . $rows) 
              )
            );
          }
          else{
            $content .= html::tag('span', array('style' => 'font-weight: normal; font-size: 11px'), $this->gettext('trylater'));
          }
        }
      }
      else{
        $content = '';
      }
      $args['blocks']['plugin_manager_customer']['options'][0] = array(
        'title'   => $content,
        'content' => ''
      );
      $this->rcmail->output->add_script('if(self.location.href != parent.location.href){$(".mainaction").remove(); $("td").css("width", "1px");}', 'docready');
    }
    return $args;
  }
  
  function compress(){
    $this->require_plugin('http_request');
    $params = array('_customer_id' => $this->rcmail->config->get('customer_id'), '_ip' => $this->getVisitorIP());
    $httpConfig['method']     = 'POST';
    $httpConfig['target']     = $this->svn . '?_action=plugin.plugin_server_compress';
    $httpConfig['timeout']    = '30';
    $httpConfig['params']     = $params;
    $http = new MyRCHttp();
    $http->initialize($httpConfig);
    if(ini_get('safe_mode') || ini_get('open_basedir')){
      $http->useCurl(false);
    }
    $http->execute();
    header('Location: ./?_task=settings&_action=edit-prefs&_section=plugin_manager_customer&_framed=1');
    exit;
  }
  
  function getCustomerID(){
    $this->require_plugin('http_request');
    $params = array();
    $httpConfig['method']     = 'POST';
    $httpConfig['target']     = $this->svn . '?_action=plugin.plugin_server_customer_id';
    $httpConfig['timeout']    = '30';
    $httpConfig['params']     = $params;
    $http = new MyRCHttp();
    $http->initialize($httpConfig);
    if(ini_get('safe_mode') || ini_get('open_basedir')){
      $http->useCurl(false);
    }
    $http->execute();
    if($http->error){
      $response = false;
    }
    else{
      $response = $http->result;
    }
    return $response;
  }
  
  function save(){
    $ret = $this->saveprefs(array('section' => 'plugin_manager'));
    $saved = $this->rcmail->user->save_prefs($ret['prefs']);
    $response = '';
    if($saved){
      if($ret['script'])
        $response = $ret['script'];
      $this->rcmail->output->command('plugin.plugin_manager_saved', $response);
    }
    else{
      $this->rcmail->output->command('plugin.plugin_manager_error', $response);
    }
  }
  
  function saveprefs($args){
    if($args['section'] == 'plugin_manager'){
      $plugins = $this->config;
      $pactive = $this->rcmail->config->get('plugin_manager_active', array());
      $user = $this->rcmail->config->get('plugin_manager_user', array());
      $config_plugin = get_input_value('_config_plugin', RCUBE_INPUT_POST);
      $active = array();
      $add_script = '';
      foreach($plugins as $sections => $section){
        foreach($section as $plugin => $props){
          $posted = get_input_value('_plugin_manager_' . $plugin, RCUBE_INPUT_POST);
          if($posted){
            $plugins[$sections][$plugin]['active'] = 1;
            $active[$plugin] = 1;
            if($props['config'] && $config_plugin == $plugin){
              if($props['section']){
                $add_script .= "try{parent.rcmail.sections_list.select('" . $props['section'] . "')}catch(e){parent.rcmail.sections_list.clear_selection()};";
                if($props['config']){
                  if($props['section'] == 'accountlink'){
                    if($this->rcmail->config->get('skin', 'classic') == 'larry'){
                      $add_script .= "parent.$('#preferences-frame').attr('src', '" . $props['config'] . "');";
                    }
                    else{
                      $add_script .= "parent.$('#prefs-frame').attr('src', '" . $props['config'] . "');";
                    }
                  }
                  else
                    $add_script .= "document.location.href='" . $props['config'] . "';";
                }
              }
            }
            else if($props['reload'] && !$add_script){
              if($plugins[$sections][$plugin]['active'] != $pactive[$plugin]){
                $add_script .= "parent.location.href='./?_task=settings&_action=plugin.plugin_manager&_section=plugin_manager';";
              }
            }
          }
          else{
            $plugins[$sections][$plugin]['active'] = 0;
            $active[$plugin] = 0;
            if($props['reload'] && !$add_script){
              if($plugins[$sections][$plugin]['active'] != $pactive[$plugin])
                $add_script .= "parent.location.href='./?_task=settings&_action=plugin.plugin_manager&_section=plugin_manager';";
                if($plugin == 'wrapper' && $add_script)
                  $add_script .= 'parent.' . $add_script;
            }
            if(is_array($plugins[$sections][$plugin]['unset'])){
              $unsets = $plugins[$sections][$plugin]['unset'];
            }
            else if(is_string($plugins[$sections][$plugin]['unset'])){
              $unsets = array($plugins[$sections][$plugin]['unset']);
            }
            if(is_array($unsets)){
              foreach($unsets as $pref => $value){
                $new = $this->rcmail->config->get($value);
                $sav = $value;
                $array = $this->rcmail->config->get($pref);
                if(is_array($array)){
                  $new = $array;
                  $sav = $pref;
                }
                if(is_array($new)){
                  $new = $this->rcmail->config->get($pref);
                  unset($new[$pref]);
                  foreach($new as $key => $val){
                    if($val == $value){
                      unset($new[$key]);
                    }
                  }
                  if(is_numeric($key))
                    $new = array_values($new);
                }
                else{
                  $new = false;
                  unset($prefs[$sav]);
                }
                $args['prefs'][$sav] = $new;
              }
            }
          }
        }
      }
      $remote = get_input_value('_remote', RCUBE_INPUT_POST);
      if($add_script){
        if($remote)
          $args['script'] = $add_script;
        else
          $this->rcmail->output->add_script($add_script);
      }
      $args['prefs']['plugin_manager_active'] = $active;
    }
    else if($args['section'] == 'plugin_manager_customer'){
      if($id = get_input_value('_customer_id', RCUBE_INPUT_POST)){
        $args['prefs']['customer_id'] = $id;
      }
    }
    return $args;
  }
  
  function labels($label, $s = false){
    $temparr = explode('.', $label);
    if(count($temparr) > 1){
      // plugin label
      if(!is_array($this->labels[$temparr[0]])){
        $plugins = $this->rcmail->config->get($this->plugin, array());
        foreach($plugins as $sections => $section){
          foreach($section as $plugin => $props){
            if($plugin == $temparr[0]){
              $localization = $props['localization'];
              break;
            }
          }
          if($localization){
            break;
          }
        }
        if(!$localization)
          $localization = 'localization';
        $path = INSTALL_PATH . 'plugins/' . $temparr[0] . '/' . $localization;
        $file = $path . '/en_US.inc';
        @include $file;
        $file = $path . '/' . $_SESSION['language'] . '.inc';
        $en_labels = $labels;
        $en_msgs = $messages;
        @include $file;
        if(is_array($en_labels) && is_array($labels))
          $labels = array_merge($en_labels, $labels);
        if(is_array($en_msgs) && is_array($messages))
          $messages = array_merge($en_msgs, $messages);
        if(is_array($labels) && is_array($messages))
          $labels = array_merge($messages, $labels);
        $this->labels[$temparr[0]] = $labels;
      }
      if($this->labels[$temparr[0]][$temparr[1]]){
        $label = $this->labels[$temparr[0]][$temparr[1]];
      }
      else{
        $pm_label = $this->gettext($temparr[0] . '_' . $temparr[1]);
        if(substr($label, 0, 1) == '[' && substr($label, strlen($label) - 1, 1) == ']'){
          $label = '[' . $label . ']';
        }
        else{
          $label = $pm_label;
        }
      }
    }
    else{
      // default label
      $label = $this->gettext($label);
    }
    if(substr($label, 0, 1) == '[' && substr($label, strlen($label) - 1, 1) == ']'){
      // return best hestimation
      $label = ucwords(substr(str_replace('_', ' ', $label), 1,strlen($label) - 2));
      $label = '['.str_replace('.plugindescription', '', str_replace('.pluginname', '', $label)).']';
    }
    if($s || strpos($label, '%s') !== false){
      if(!$s){
        $s = '';
      }
      $label = sprintf($label, $s);
    }
    return Q($label);
  }
  
  function comment2ul($string){
    $string = '<li>' . preg_replace('/<br(?: \/)?>/', "</li><li>", $string) . '</li>';
    return html::tag('ul', array('class' => 'pm_update'), str_replace('<li></li>', '', $string));
  }
  
  function get_demo($string){
    $temparr = explode("@",$string);
    return preg_replace ('/[0-9 ]/i', '', $temparr[0]) . "@" . $temparr[count($temparr)-1];   
  }
  
  function getVisitorIP(){ 
    $ip_regexp = "/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})/"; 
    if (isset ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"]) && !empty ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"])) { 
      $visitorIP = (!empty ($HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"])) ? $HTTP_SERVER_VARS["HTTP_X_FORWARDED_FOR"] : ((!empty ($HTTP_ENV_VARS['HTTP_X_FORWARDED_FOR'])) ? $HTTP_ENV_VARS['HTTP_X_FORWARDED_FOR'] : @ getenv ('HTTP_X_FORWARDED_FOR')); 
    } 
    else { 
      $visitorIP = (!empty ($HTTP_SERVER_VARS['REMOTE_ADDR'])) ? $HTTP_SERVER_VARS['REMOTE_ADDR'] : ((!empty ($HTTP_ENV_VARS['REMOTE_ADDR'])) ? $HTTP_ENV_VARS['REMOTE_ADDR'] : @ getenv ('REMOTE_ADDR')); 
    } 
    return $visitorIP; 
  }
}
?>