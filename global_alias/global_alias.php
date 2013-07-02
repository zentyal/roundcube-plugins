<?php
# 
# This file is part of Roundcube "global_alias" plugin.
# 
# Your are not allowed to distribute this file or parts of it.
# 
# This file is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# 
# Copyright (c) 2012 - 2013 Roland 'Rosali' Liebl - all rights reserved.
# dev-team [at] myroundcube [dot] com
# http://myroundcube.com
# 
class global_alias extends rcube_plugin{var$task='login';var$noframe=true;var$noajax=true;static private$plugin='global_alias';static private$author='myroundcube@mail4us.net';static private$authors_comments=null;static private$download='http://myroundcube.googlecode.com';static private$version='1.5';static private$date='19-11-2012';static private$licence='All Rights reserved';static private$requirements=array('Roundcube'=>'0.7.1','PHP'=>'5.2.1');static private$config='config.inc.php.dist';function init(){$I=rcmail::get_instance();if(!in_array('global_config',$I->config->get('plugins'))){$this->load_config();}$this->add_hook('email2user',array($this,'email2user'));if($I->config->get('global_alias_lc',false))$this->add_hook('authenticate',array($this,'authenticate'));}function email2user($B){$_SESSION['global_alias']=$B['email'];}function authenticate($B){$B['user']=strtolower($B['user']);return$B;}static function about($E=false){$C=self::$requirements;foreach(array('required_','recommended_')as$D){if(is_array($C[$D.'plugins'])){foreach($C[$D.'plugins']as$A=>$J){if(class_exists($A)&&method_exists($A,'about')){$K=new$A(false);$C[$D.'plugins'][$A]=array('method'=>$J,'plugin'=>$K->about($E),);}else{$C[$D.'plugins'][$A]=array('method'=>$J,'plugin'=>$A,);}}}}$H=array('plugin'=>self::$plugin,'version'=>self::$version,'date'=>self::$date,'author'=>self::$author,'comments'=>self::$authors_comments,'licence'=>self::$licence,'download'=>self::$download,'requirements'=>$C,);if(is_array($E)){$F=array('plugin'=>self::$plugin);foreach($E as$G){$F[$G]=$H[$G];}return$F;}else{return$H;}}}