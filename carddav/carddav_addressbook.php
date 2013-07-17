<?php

/**
 * Roundcube CardDAV addressbook extension
 *
 * @author Roland 'rosali' Liebl
 * @author Christian Putzke <christian.putzke@graviox.de>
 * @copyright Christian Putzke @ Graviox Studios
 * @since 12.09.2011
 * @link http://www.graviox.de/
 * @link https://twitter.com/graviox/
 * @version 0.5.1
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */
class carddav_addressbook extends rcube_addressbook
{
  protected $user_id;
  protected $rcpassword;
  protected $db;
  protected $db_groups = 'carddav_contactgroups';
  protected $db_groupmembers = 'carddav_contactgroupmembers';

	private $filter;
	private $result;
	private $name;
	private $carddav_server_id = false;
	private $counter = 0;
	private $table_cols = array('name', 'firstname', 'surname', 'email');
	private $fulltext_cols = array('name', 'firstname', 'surname', 'middlename', 'nickname',
		  'jobtitle', 'organization', 'department', 'maidenname', 'email', 'phone',
		  'address', 'street', 'locality', 'zipcode', 'region', 'country', 'website', 'im', 'notes');
	private $cache;
	
	public $primary_key = 'carddav_contact_id';
	public $readonly = false;
	public $addressbook = false;
	public $groups = true;
	public $group_id = 0;
	public $coltypes = array('name', 'firstname', 'surname', 'middlename', 'email', 'photo');
  public $date_cols = array('birthday', 'anniversary');
	const SEPARATOR = ',';

	/**
	 * Init CardDAV addressbook
	 *
	 * @param	string		$carddav_server_id		Translated addressbook name
	 * @param	integer		$name					CardDAV server id
	 * @return	void
	 */
	public function __construct($carddav_server_id, $name, $readonly, $addressbook)
	{
    $this->ready = true;
    $this->name = $name;
    $this->carddav_server_id = $carddav_server_id;
    $this->readonly = $readonly;
    $this->db = rcmail::get_instance()->db;
    $this->user_id = rcmail::get_instance()->user->data['user_id'];
    $password = $_SESSION['default_account_password'] ? $_SESSION['default_account_password'] : $_SESSION['password'];
    $this->rcpassword = $password;
    $this->addressbook = $addressbook;
    if(class_exists('carddav_plus')){
      $this->coltypes = carddav_plus::carddav_coltypes();
    }
	}

	/**
	 * Get translated addressbook name
	 *
	 * @return	string	$this->name	Translated addressbook name
	 */
	public function get_name()
	{
		return $this->name;
	}

	/**
	 * Get all CardDAV adressbook contacts
	 *
	 * @param	array $limit	Limits (limit, offset)
	 * @return	array 			CardDAV adressbook contacts
	 */
	private function get_carddav_addressbook_contacts($limit = array())
	{
		$rcmail = rcmail::get_instance();
		$carddav_addressbook_contacts	= array();
    
    $order = $rcmail->config->get('addressbook_sort_col', 'name');
		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			AND
				carddav_server_id = ?
				".$this->get_search_set()."
			ORDER BY
				" . $order . " ASC
		";
		if (empty($limit))
		{
			$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $this->carddav_server_id);
		}
		else
		{
			$result = $rcmail->db->limitquery($query, $limit['start'], $limit['length'], $rcmail->user->data['user_id'], $this->carddav_server_id);
		}
  	while ($contact = $rcmail->db->fetch_assoc($result))
		{
			$carddav_addressbook_contacts[$contact['vcard_id']] = $contact;
		}
		return $carddav_addressbook_contacts;
	}

	/**
	* Get one CardDAV adressbook contact
	*
	* @param	integer	$carddav_contact_id		CardDAV contact id
	* @return	array 							CardDAV adressbook contact
	*/
	private function get_carddav_addressbook_contact($carddav_contact_id)
	{
		$rcmail = rcmail::get_instance();

		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			AND
				carddav_contact_id = ?
		";

		$result = $rcmail->db->query($query, $rcmail->user->data['user_id'], $carddav_contact_id);

		return $rcmail->db->fetch_assoc($result);
	}

	/**
	* Get count of CardDAV contacts specified CardDAV addressbook
	*
	* @return	integer		Count of the CardDAV contacts
	*/
	private function get_carddav_addressbook_contacts_count()
	{

		$query = "
			SELECT
				carddav_contact_id
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			AND
				carddav_server_id = ?
				".$this->get_search_set()."
			ORDER BY
				name ASC
		";
		$result = $this->db->query($query, $this->user_id, $this->carddav_server_id);
    $count = 0;
    if($this->group_id != 0){
       while ($result && ($sql_arr = $this->db->fetch_assoc($result))) {
         $id = $sql_arr['carddav_contact_id'];
         $query = "SELECT * FROM " . get_table_name('carddav_contactgroupmembers') . " WHERE contact_id=? AND contactgroup_id=?";
         $nums = $this->db->query($query, $id, $this->group_id);
         if($this->db->num_rows($nums) > 0){
           $count ++;
         }
       }
       return $count;
		}
		else{
  		return $this->db->num_rows($result);
    }
	}

	/**
	 * Get result set
	 *
	 * @return	$this->result	rcube_result_set	Roundcube result set
	 */
	public function get_result()
	{
		return $this->result;
	}

	/**
	 *
	 *
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	boolean		$assoc					Define if result should be an assoc array or rcube_result_set
	 * @return	mixed								Returns contact as an assoc array or rcube_result_set
	 */
	public function get_record($carddav_contact_id, $assoc = false)
	{
		$contact		= $this->get_carddav_addressbook_contact($carddav_contact_id);
		$contact['ID']	= $contact[$this->primary_key];

		unset($contact['email']);

		$vcard		= new rcube_vcard($contact['vcard']);
		$contact	+= $vcard->get_assoc();

		$this->result = new rcube_result_set(1);
		$this->result->add($contact);
		if ($assoc === true)
		{
			return $contact;
		}
		else
		{
			return $this->result;
		}
	}

	/**
	* Getter for saved search properties
	*
	* @return	$this->filter	array	Search properties
	*/
	public function get_search_set()
	{
		return $this->filter;
	}

	/**
	 * Save a search string for future listings
	 *
	 * @param	string	$filter		SQL params to use in listing method
	 * @return	void
	 */
	public function set_search_set($filter)
	{
		$this->filter = $filter;
	}

	/**
	 * Set database search filter
	 *
	 * @param mixed   $fields   Database field names
	 * @param string  $value    Searched value
	 * @param array   $required Required field names
	 * @return	void
	 */
	public function set_filter($fields, $value, $required = array())
	{
    if(!is_array($fields) && $fields == $this->primary_key){
      $filter = ' AND ' . $this->db->quoteIdentifier($this->primary_key) . '=' . $this->db->quote($value);
		  $this->set_search_set($filter);
		  return;
		}
		
		if(class_exists('carddav_plus') && isset($_POST['_adv'])){
      $version = carddav_plus::about(array('version'));
      if($version['version'] <= '2.0.5'){
        $filter = ' AND ' . $this->db->quoteIdentifier('carddav_server_id') . '=' . $this->db->quote('false');
        $this->set_search_set($filter);
        return;
      }
    }

    if(!is_array($fields))
      $fields = array($fields);

    $where = $and_where = array();
    $WS = ' ';
    foreach ($fields as $idx => $col){
      if($col == '*'){
        $words = array();
        foreach(explode($WS, rcube_utils::normalize_string($value)) as $word){
          $words[] = $this->db->ilike('words', '%' . $word . '%');
        
        }
        $where[] = '(' . join(' AND ', $words) . ')';
      }
      else{
        $val = is_array($value) ? $value[$idx] : $value;
        // table column
        if(in_array($col, $this->table_cols)){
          $where[] = $this->db->ilike($col, '%' . $val . '%');
        }
        // fulltext/vcard field
        else{
          if(in_array($col, $this->fulltext_cols)){
            $dbcol = 'words';
          }
          else{
            $dbcol = 'vcard';
          }
          foreach(rcube_utils::normalize_string($val, true) as $word){
            if(in_array($col, $this->date_cols)){
              $word = date('Y-m-d', rcube_utils::strtotime($word));
            }
            $words[] = $this->db->ilike($dbcol, '%' . $word . '%');
          }
          $where[] = '(' . join(' AND ', $words) . ')';
        }
      }
    }
    if(is_array($required) && is_array($this->table_cols)){
      $required = array_intersect($required, $this->table_cols);
      foreach($required as $col){
        $and_where[] = $this->db->quoteIdentifier($col).' <> '.$this->db->quote('');
      }
    }
    if(!empty($where)){
      // use AND operator for advanced searches
      $where = join(is_array($value) ? ' AND ' : ' OR ', $where);
    }
    if(!empty($and_where)){
      $where = ($where ? "($where) AND " : '') . join(' AND ', $and_where);
    }
    $this->set_search_set(' AND ' . $where);
  }

	/**
	 * Sets internal addressbook group id
	 *
	* @param	string	$group_id	Internal addressbook group id
	* @return	void
	*/
	public function set_group($group_id)
	{
		$this->group_id = $group_id;
	}

	/**
	 * Reset cached filters and results
	 *
	 * @return	void
	 */
	public function reset()
	{
		$this->result = null;
		$this->filter = null;
	}

	/**
	 * Synchronize CardDAV-Addressbook
	 *
	 * @param	array		$server					CardDAV server array
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	string		$vcard_id				vCard id
	 * @return	boolean								if no error occurred "true" else "false"
	 */
	public function carddav_addressbook_sync($server, $carddav_contact_id = null, $vcard_id = null)
	{
		$rcmail = rcmail::get_instance();
		$any_data_synced = false;
		self::write_log('Starting CardDAV-Addressbook synchronization');

		$carddav_backend = new carddav_backend($server['url']);
    if($rcmail->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));
		if ($carddav_backend->check_connection())
		{
			self::write_log('Connected to the CardDAV-Server ' . $server['url']);
			if ($vcard_id !== null)
			{
				// server does not support REPORT request
				$noreport = $rcmail->config->get('carddav_noreport', array());
				if($noreport[$server['url']]){
				  $elements = $carddav_backend->get_xml_vcard($vcard_id);
				}
				else{
				  $elements = false;
				}
				try
			  {
				  $xml = new SimpleXMLElement($elements);
				  if(count($xml->element) < 1){
				    $elements = false;
				  }
				}
			  catch (Exception $e)
			  {
				  $elements = false;
				}
				if($elements === false){
				  $elements = $carddav_backend->get(false);
				  $this->filter = null;
				  $carddav_addressbook_contacts = $this->get_carddav_addressbook_contacts();
          if(!isset($noreport[$server['url']])){
            $a_prefs['carddav_noreport'][$server['url']] = 1;
            $rcmail->user->save_prefs($a_prefs);
          }
				}
				else if($carddav_contact_id !== null)
				{
					$carddav_addressbook_contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
					$carddav_addressbook_contacts = array(
						$carddav_addressbook_contact['vcard_id'] => $carddav_addressbook_contact
					);
				}
			}
			else
			{
				$elements = $carddav_backend->get(false);
				$carddav_addressbook_contacts = $this->get_carddav_addressbook_contacts();
			}
			try
			{
				$xml = new SimpleXMLElement($elements);

				if (!empty($xml->element))
				{
					foreach ($xml->element as $element)
					{
						$element_id = (string) $element->id;
						$element_etag = (string) $element->etag;
						$element_last_modified = (string) $element->last_modified;
						if (isset($carddav_addressbook_contacts[$element_id]))
						{
							if ($carddav_addressbook_contacts[$element_id]['etag'] != $element_etag ||
								$carddav_addressbook_contacts[$element_id]['last_modified'] != $element_last_modified)
							{
								$carddav_content = array(
									'vcard' => $carddav_backend->get_vcard($element_id),
									'vcard_id' => $element_id,
									'etag' => $element_etag,
									'last_modified' => $element_last_modified
								);
								if ($this->carddav_addressbook_update($carddav_content))
								{
									$any_data_synced = true;
								}
							}
						}
						else
						{
							$carddav_content = array(
								'vcard' => $carddav_backend->get_vcard($element_id),
								'vcard_id' => $element_id,
								'etag' => $element_etag,
								'last_modified' => $element_last_modified
							);
							if (!empty($carddav_content['vcard']))
							{
								if ($this->carddav_addressbook_add($carddav_content))
								{
									$any_data_synced = true;
								}
							}
						}

						unset($carddav_addressbook_contacts[$element_id]);
					}
				}
				else
				{
					$logging_message = 'No CardDAV XML-Element found!';
					if ($carddav_contact_id !== null && $vcard_id !== null)
					{
						self::write_log($logging_message . ' The CardDAV-Server does not have a contact with the vCard id ' . $vcard_id);
					}
					else
					{
						self::write_log($logging_message . ' The CardDAV-Server seems to have no contacts');
					}
				}

				if (!empty($carddav_addressbook_contacts))
				{
					foreach ($carddav_addressbook_contacts as $vcard_id => $etag)
					{
						if ($this->carddav_addressbook_delete($vcard_id))
						{
							$any_data_synced = true;
						}
					}
				}

				if ($any_data_synced === false)
				{
					self::write_log('all CardDAV-Data are synchronous, nothing todo!');
				}

				self::write_log('Syncronization complete!');
			}
			catch (Exception $e)
			{
				self::write_log('CardDAV-Server XML-Response is malformed. Synchronization aborted!');
				return false;
			}
		}
		else
		{
			self::write_log('Couldn\'t connect to the CardDAV-Server ' . $server['url']);
			return false;
		}

		return true;
	}

	/**
	 * Adds a vCard to the CardDAV addressbook
	 *
	 * @param	array	$carddav_content	CardDAV contents (vCard id, etag, last modified, etc.)
	 * @return	boolean
	 */
	private function carddav_addressbook_add($carddav_content)
	{
		$rcmail = rcmail::get_instance();
		if(stripos($carddav_content['vcard'], "\nUID:") === false){
		  $carddav_content['vcard'] = str_replace("\nEND:VCARD","\nUID:" . $carddav_content['vcard_id'] . "\r\nEND:VCARD", $carddav_content['vcard']);
		}
		$vcard = new rcube_vcard();
    $vcard->extend_fieldmap(array('categories' => 'CATEGORIES'));
    $vcard->load($carddav_content['vcard']);
		$query = "
			INSERT INTO
				".get_table_name('carddav_contacts')." (carddav_server_id, user_id, etag, last_modified, vcard_id, vcard, words, firstname, surname, name, email)
			VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		";
		$save_data = $vcard->get_assoc();
		$database_column_contents = $this->get_database_column_contents($save_data);

		$result = $rcmail->db->query(
			$query,
			$this->carddav_server_id,
			$rcmail->user->data['user_id'],
			$carddav_content['etag'],
			$carddav_content['last_modified'],
			$carddav_content['vcard_id'],
			$vcard->cleanup($carddav_content['vcard']),
			$database_column_contents['words'],
			$database_column_contents['firstname'],
			$database_column_contents['surname'],
			$database_column_contents['name'],
			$database_column_contents['email']
		);
		if ($rcmail->db->affected_rows($result))
		{
			self::write_log('Added CardDAV-Contact to the local database with the vCard id ' . $carddav_content['vcard_id']);
			$this->carddav_categories_sync($save_data, $carddav_content);
			return true;
		}
		else
		{
			self::write_log('Couldn\'t add CardDAV-Contact to the local database with the vCard id ' . $carddav_content['vcard_id']);
			return false;
		}
	}
	
	/**
	 * Updates a vCard in the CardDAV-Addressbook
	 *
	 * @param	array	$carddav_content	CardDAV contents (vCard id, etag, last modified, etc.)
	 * @return	boolean
	 */
	private function carddav_addressbook_update($carddav_content)
	{
		$rcmail = rcmail::get_instance();
		$vcard = new rcube_vcard();
    $vcard->extend_fieldmap(array('categories' => 'CATEGORIES'));
    $vcard->load($carddav_content['vcard']);
		$save_data = $vcard->get_assoc();
		$database_column_contents = $this->get_database_column_contents($save_data);

		$query = "
			UPDATE
				".get_table_name('carddav_contacts')."
			SET
				etag = ?,
				last_modified = ?,
				vcard = ?,
				words = ?,
				firstname = ?,
				surname = ?,
				name = ?,
				email = ?
			WHERE
				vcard_id = ?
			AND
				carddav_server_id = ?
			AND
				user_id = ?
		";

		$result = $rcmail->db->query(
			$query,
			$carddav_content['etag'],
			$carddav_content['last_modified'],
			$vcard->cleanup($carddav_content['vcard']),
			$database_column_contents['words'],
			$database_column_contents['firstname'],
			$database_column_contents['surname'],
			$database_column_contents['name'],
			$database_column_contents['email'],
			$carddav_content['vcard_id'],
			$this->carddav_server_id,
			$rcmail->user->ID
		);
		if ($rcmail->db->affected_rows($result))
		{
			self::write_log('CardDAV-Contact updated in the local database with the vCard id ' . $carddav_content['vcard_id']);
			$this->carddav_categories_sync($save_data, $carddav_content);
			return true;
		}
		else
		{
			self::write_log('Couldn\'t update CardDAV-Contact in the local database with the vCard id ' . $carddav_content['vcard_id']);
			return false;
		}
	}
	
	/**
	 * Sync Categories
	 *
	 * @param array $save_data
	 * @param array $carddav_content
	 */
	private function carddav_categories_sync($save_data, $carddav_content)
	{
		if(isset($save_data['categories']) && is_array($save_data['categories'])){
		  $rcmail = rcmail::get_instance();
		  $categories = explode(',', $save_data['categories'][0]);
      $sql = 'SELECT * FROM ' . get_table_name('carddav_contacts') . ' WHERE user_id=? AND vcard_id=?';
      $res = $rcmail->db->query($sql, $rcmail->user->ID, $carddav_content['vcard_id']);
      $tmp = $rcmail->db->fetch_assoc($res);
      $carddav_contact_id = $tmp['carddav_contact_id'];
      foreach($categories as $category){
        $sql = 'SELECT * FROM ' . get_table_name('carddav_contactgroups') . ' WHERE user_id=? AND name=?';
		    $res = $rcmail->db->query($sql, $rcmail->user->ID, $category, 1);
		    $exists = $rcmail->db->fetch_assoc($res);
		    if(!is_array($exists)){
          $sql = 'INSERT INTO ' . get_table_name('carddav_contactgroups') . ' (user_id, changed, del, name, addressbook) VALUES (?, ?, ?, ?, ?)';
          $rcmail->db->query($sql, $rcmail->user->ID, date('Y-m-d H:i:s'), 0, $category, 'carddav_addressbook' . $this->carddav_server_id);
          $contactgroup_id = $rcmail->db->insert_id(get_table_name('carddav_contactgroups'));
          $sql = 'INSERT INTO ' . get_table_name('carddav_contactgroupmembers') . ' (contactgroup_id, contact_id, created) VALUES (?, ?, ?)';
          $rcmail->db->query($sql, $contactgroup_id, $carddav_contact_id, date('Y-m-d H:i:s'));
        }
        else if($exists['del'] == 0){
          $sql = 'SELECT * FROM ' . get_table_name('carddav_contactgroups') . ' WHERE user_id=? AND name=?';
          $res = $rcmail->db->query($sql, $rcmail->user->ID, $category);
          $tmp = $rcmail->db->fetch_assoc($res);
          $contactgroup_id = $tmp['contactgroup_id'];
          $sql = 'SELECT * FROM ' . get_table_name('carddav_contactgroupmembers') . ' WHERE contactgroup_id=? AND contact_id=?';
          $res = $rcmail->db->query($sql, $contactgroup_id, $carddav_contact_id);
          if(!$rcmail->db->fetch_assoc($res)){
            $sql = 'INSERT INTO ' . get_table_name('carddav_contactgroupmembers') . ' (contactgroup_id, contact_id, created) VALUES (?, ?, ?)';
            $rcmail->db->query($sql, $contactgroup_id, $carddav_contact_id, date('Y-m-d H:i:s'));
          }
        }
        else if($exists['del'] == 1){
          $save_data['categories'][0] = str_replace($category, '', $save_data['categories'][0]);
          $save_data['categories'][0] = str_replace(',,', '', $save_data['categories'][0]);
          if(substr($save_data['categories'][0], strlen($save_data['categories'][0]) - 1, 1) == ','){
            $save_data['categories'][0] = substr($save_data['categories'][0], 0, strlen($save_data['categories'][0]) - 1);
          }
          $this->update($carddav_contact_id, $save_data);
        }
		  }
		}
	}
	
	/**
	 * Deletes a vCard from the CardDAV addressbook
	 *
	 * @param	string	$vcard_id	vCard id
	 * @return	boolean
	 */
	private function carddav_addressbook_delete($vcard_id)
	{
    if(!$vcard_id)
      return true;
      
		$rcmail = rcmail::get_instance();
		$query = "
			DELETE FROM
				".get_table_name('carddav_contacts')."
			WHERE
				vcard_id = ?
			AND
				carddav_server_id = ?
			AND
				user_id = ?
		";

		$result = $rcmail->db->query($query, $vcard_id, $this->carddav_server_id, $rcmail->user->data['user_id']);

		if ($rcmail->db->affected_rows($result))
		{
			self::write_log('CardDAV-Contact deleted from the local database with the vCard id ' . $vcard_id);
			return true;
		}
		else
		{
			self::write_log('Couldn\'t delete CardDAV-Contact from the local database with the vCard id ' . $vcard_id);
			return false;
		}
	}

	/**
	 * Adds a Name field to vcard if missing
	 *
	 * @param	string	$vcard	vCard
	 * @return	string
	 */
  private function vcard_check($vcard)
  {
    if($vcard !== null){
      if(stripos($vcard, "\nN:") === false){
        if(stripos($vcard, "\nFN:") !== false){
          $temp = explode("\nFN:", $vcard);
          $temp = explode("\n", $temp[1]);
          $n = trim($temp[0]);
          $vcard = str_ireplace("\nFN:", "\nN:;" . $n . ";;;\r\nFN:", $vcard);
        }
      }
    }
    return $vcard;
  }

	/**
	 * Adds a CardDAV server contact
	 *
	 * @param	string	$vcard	vCard
	 * @return	boolean
	 */
	private function carddav_add($vcard)
	{
		$rcmail = rcmail::get_instance();
		$sync = true;
		if($rcmail->action == 'copy'){
      $this->counter ++;
      $cids = get_input_value('_cid', RCUBE_INPUT_POST);
      $cids = explode(',', $cids);
      if($this->counter < count($cids)){
        $sync = false;
      }
    }
		$vcard = $this->vcard_check($vcard);
		$server = current(carddav::get_carddav_server($this->carddav_server_id));
		$arr = parse_url($server['url']);
    $carddav_backend = new carddav_backend($server['url']);
    if($rcmail->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));
		if ($carddav_backend->check_connection())
		{
			$vcard_id = $carddav_backend->add($vcard);
      if($sync){
        if($rcmail->action == 'copy'){
          $vcard_id = false;
        }
			  $this->carddav_addressbook_sync($server, false, $vcard_id);
			  $cid = $rcmail->db->insert_id(get_table_name('carddav_contacts'));
			  return $cid;
			}
			else{
			  return true;
			}
		}

		return false;
	}

	/**
	 * Updates a CardDAV server contact
	 *
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	string		$vcard					The new vCard
	 * @return	boolean
	 */
	private function carddav_update($carddav_contact_id, $vcard)
	{
		$rcmail = rcmail::get_instance();
		$vcard = $this->vcard_check($vcard);
		$contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
		$server = current(carddav::get_carddav_server($this->carddav_server_id));
		$arr = parse_url($server['url']);
		$carddav_slow = $rcmail->config->get('carddav_slow_backends', array());
		if(isset($carddav_slow[strtolower($arr['host'])])){
		  $carddav_backend = new carddav_backend($server['url'], '', (int) $carddav_slow[strtolower($arr['host'])], false);
		}
		else{
		  $carddav_backend = new carddav_backend($server['url']);
		}
    if($rcmail->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

		if ($carddav_backend->check_connection())
		{
			$carddav_backend->update($vcard, $contact['vcard_id']);
			$this->carddav_addressbook_sync($server, $carddav_contact_id, $contact['vcard_id']);

			return true;
		}

		return false;
	}

	/**
	 * Deletes the CardDAV server contact
	 *
	 * @param	array	$carddav_contact_ids	CardDAV contact ids
	 * @return	mixed							affected CardDAV contacts or false
	 */
	private function carddav_delete($carddav_contact_ids)
	{
		$rcmail = rcmail::get_instance();
		$server = current(carddav::get_carddav_server($this->carddav_server_id));
		$carddav_backend = new carddav_backend($server['url']);
    if($rcmail->decrypt($server['password']) == '%p'){
      $server['password'] = $this->rcpassword;
    }
		$carddav_backend->set_auth($server['username'], $rcmail->decrypt($server['password']));

		if ($carddav_backend->check_connection())
		{
			foreach ($carddav_contact_ids as $carddav_contact_id)
			{
				$contact = $this->get_carddav_addressbook_contact($carddav_contact_id);
				$carddav_backend->delete($contact['vcard_id']);
				$this->counter ++;
				if($this->counter < count($carddav_contact_ids)){
				  continue;
				}
				if($this->counter > 1){
				  $contact['vcard_id'] = false;
				}
				$this->carddav_addressbook_sync($server, $carddav_contact_id, $contact['vcard_id']);
			}

			return count($carddav_contact_ids);
		}

		return false;
	}

	/**
	 * @see     rcube_addressbook::list_groups()
	 * @param   string  $search
	 * @param   integer $mode
	 * @return  array
	 */
	public function list_groups($search = null, $mode = 0)
	{
    $results = array();

    if (!$this->groups)
      return $results;

    $mode = intval($mode);
    switch($mode){
      case 1:
       $sql_filter = $search ? " AND " . $this->db->ilike('name', $search) : '';
       break;
      case 2:
        $sql_filter = $search ? " AND " . $this->db->ilike('name', $search.'%') : '';
        break;
      default:
        $sql_filter = $search ? " AND " . $this->db->ilike('name', '%'.$search.'%') : '';
    }

    $sql_result = $this->db->query(
      "SELECT * FROM ".get_table_name($this->db_groups).
      " WHERE del<>1".
      " AND user_id=?".
      " AND addressbook=?".
      $sql_filter.
      " ORDER BY name",
      $this->user_id,
      $this->addressbook);

     while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
       $sql_arr['ID'] = $sql_arr['contactgroup_id'];
       $results[]     = $sql_arr;
     }

    return $results;
	}
	
  /**
   * Get group properties such as name and email address(es)
   *
   * @param string Group identifier
   * @return array Group properties as hash array
   */
  function get_group($group_id)
  {
    $sql_result = $this->db->query(
      "SELECT * FROM ".get_table_name($this->db_groups).
      " WHERE del<>1".
      " AND contactgroup_id=?".
      " AND user_id=?".
      " AND addressbook=?",
      $group_id, $this->user_id, $this->addressbook);

    if ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
      $sql_arr['ID'] = $sql_arr['contactgroup_id'];
      return $sql_arr;
    }

    return null;
  }
  
	/**
	 * Returns a list of CardDAV adressbook contacts
	 *
	 * @param	string				$columns		Database columns
	 * @param	integer				$subset			Subset for result limits
	 * @return	rcube_result_set	$this->result	List of CardDAV adressbook contacts
	 */
	public function list_records($columns = null, $subset = 0)
	{
    if(rcmail::get_instance()->action == 'search'){
      return $this->result;
    }
		$this->result = $this->count();
		if($this->group_id == 0){
      $limit = array(
        'start' => ($subset < 0 ? $this->result->first + $this->page_size + $subset : $this->result->first),
        'length' => ($subset != 0 ? abs($subset) : $this->page_size)
      );
    }
    else{
      $limit = array();
    }
		$contacts = $this->get_carddav_addressbook_contacts($limit);

		if (!empty($contacts))
		{
			foreach ($contacts as $contact)
			{
				$record = array();
				$record['ID'] = $contact[$this->primary_key];

				if ($columns === null)
				{
					$vcard = new rcube_vcard($contact['vcard']);
					$record += $vcard->get_assoc();
				}
				else
				{
					$record['name'] = $contact['name'];
					$record['email'] = explode(', ', $contact['email']);
					if($contact['firstname'])
					  $record['firstname'] = $contact['firstname'];
					if($contact['surname'])
					  $record['surname'] = $contact['surname'];
				}
        if($this->group_id != 0){
          $query = "
            SELECT
              *
            FROM
              ".get_table_name('carddav_contactgroupmembers')."
            WHERE
              contactgroup_id=?
            AND
              contact_id=?
          ";
          $result = $this->db->query($query, $this->group_id, $record['ID']);
          if ($this->db->num_rows($result)) {
            $this->result->add($record);
          }
        }
        else{
				  $this->result->add($record);
				}
			}
		}
		return $this->result;
	}

	/**
	 * Search and autocomplete contacts in the mail view
	 *
	 * @return	rcube_result_set	$this->result	List of searched CardDAV adressbook contacts
	 */
	private function search_carddav_addressbook_contacts()
	{
		$rcmail = rcmail::get_instance();
		$this->result = $this->count();

		$query = "
			SELECT
				*
			FROM
				".get_table_name('carddav_contacts')."
			WHERE
				user_id = ?
			AND ".$this->db->quoteIdentifier('carddav_server_id') . " = ?
			".$this->get_search_set()."
			ORDER BY
				name ASC
		";
		$result = $this->db->query($query, $this->user_id, $this->carddav_server_id);
		while ($contact = $this->db->fetch_assoc($result))
		{
			$record['name'] = $contact['name'];
			$record['firstname'] = $contact['firstname'];
			$record['surname'] = $contact['surname'];
			$record['email'] = explode(', ', $contact['email']);
			$record['ID'] = $contact['carddav_contact_id'];
			$record['vcard'] = $contact['vcard'];
      if($rcmail->action == 'autocomplete'){
        $query = "
          SELECT
            *
          FROM
            ".get_table_name('carddav_server')."
          WHERE
            carddav_server_id = ?
            AND
              autocomplete = ?
          AND
            user_id = ?
        ";
        $autocomplete = $this->db->query($query, $contact['carddav_server_id'], 1, $this->user_id);
        if($this->db->num_rows($autocomplete)){
          $this->result->add($record);
        }
      }
      else{
        if(rcmail::get_instance()->action == 'photo'){
          $vcard = new rcube_vcard();
          $vcard->load($record['vcard']);
          $contact = $vcard->get_assoc();
          $record['photo'] = $contact['photo'];
        }
        $this->result->add($record);
      }
		}
		return $this->result;
	}

	/**
	 * Search method (autocomplete, addressbook)
	 *
	 * @param   array   $fields Search in these fields
	 * @param   mixed   $value  Search value
	 * @param   int     $mode
	 * @param   boolean $select
	 * @param   boolean $nocount
	 * @param   array   $required
	 * @return  object  List of searched CardDAV-Adressbook contacts
	 */
  public function search($fields, $value, $mode = 0, $select = true, $nocount = false, $required = array())
  {
    $this->set_filter($fields, $value, $required);
    $contacts = $this->search_carddav_addressbook_contacts($fields);
    if(count($contacts->records) > 0){
      $mode = intval($mode);
      // advanced search
      if(isset($_POST['_adv'])){
        foreach($contacts->records as $key => $contact){
          $vcard = new rcube_vcard();
          $vcard->extend_fieldmap(array('categories' => 'CATEGORIES'));
          $vcard->load($contact['vcard']);
          $contact = $vcard->get_assoc();
          foreach($_POST as $search => $search_value){
            if(substr($search, 0, strlen('_search_')) == '_search_'){
              $search_for = substr($search, strlen('_search_'));
              $search_value = get_input_value($search, RCUBE_INPUT_POST);
              if($search_value){
                if(strpos($search, $search_for) !== false){
                  foreach($contact as $field => $value){
                    if(is_array($value)){
                      $value = implode('|', $value);
                    }
                    $field = explode(':', $field);
                    $field = $field[0];
                    $contact[$field] = $value;
                  }
                  foreach($contact as $field => $value){
                    if(isset($contact[$search_for])){
                      switch($mode){
                        case 1:
                          if(strtolower($contact[$search_for]) != strtolower($search_value)){
                            unset($contacts->records[$key]);
                            break 2;
                          }
                          break;
                        case 2:
                          if(stripos($contact[$search_for], $search_value) !== 0){
                            unset($contacts->records[$key]);
                            break 2;
                          }
                          break;
                        default:
                          if(stripos($contact[$search_for], $search_value) === false){
                            unset($contacts->records[$key]);
                            break 2;
                          }
                      }
                    }
                    else{
                      unset($contacts->records[$key]);
                      break 2;
                    }
                  }
                }
              }
            }
          }
        }
        $contacts->count = count($contacts->records);
        $records = $contacts->records;
        $contacts->records = array();
        foreach($records as $record){
          unset($record['vcard']);
          $contacts->records[] = $record;
        }
        $this->result = $contacts;
      }
      // quick search
      else if((rcmail::get_instance()->action == 'search' && isset($_GET['_q'])) ||(rcmail::get_instance()->action == 'autocomplete' && isset($_POST['_search']))){
        $search_value = get_input_value('_q', RCUBE_INPUT_GET);
        if(!$search_value){
          $search_value = get_input_value('_search', RCUBE_INPUT_POST);
        }
        if($fields == '*'){
          $fields = array ('name', 'firstname', 'surname', 'email');
        }
        foreach($contacts->records as $key => $contact){
          foreach($contact as $field => $value){
            foreach($fields as $search_field){
              $match = false;
              if(is_array($contact[$search_field])){
                foreach($contact[$search_field] as $value){
                  switch($mode){
                    case 1:
                      if(strtolower($value) == strtolower($search_value)){
                        $match = true;
                        break 3;
                      }
                    case 2:
                      if(stripos($value, $search_value) === 0){
                        $match = true;
                        break 3;
                      }
                      break;
                    default:
                      if(stripos($value, $search_value) !== false){
                        $match = true;
                        break 3;
                    }
                  }
                }
              }
              else{
                switch($mode){
                  case 1:
                    if(strtolower($contact[$search_field]) == strtolower($search_value)){
                      $match = true;
                      break 2;
                    }
                  case 2:
                    if(stripos($contact[$search_field], $search_value) === 0){
                      $match = true;
                      break 2;
                    }
                    break;
                  default:
                    if(stripos($contact[$search_field], $search_value) !== false){
                      $match = true;
                      break 2;
                    }
                }
              }
            }
            if(!$match){
              unset($contacts->records[$key]);
            }
          }
        }
        $contacts->count = count($contacts->records);
        $records = $contacts->records;
        $contacts->records = array();
        foreach($records as $record){
          unset($record['vcard']);
          $contacts->records[] = $record;
        }
        $this->result = $contacts;
      }
      else{
        $this->result = null;
      }
    }
    return $contacts;
  }

	/**
	 * Count CardDAV contacts for a specified CardDAV addressbook and return the result set
	 *
	 * @return	rcube_result_set
	 */
	public function count()
	{
		$count = $this->get_carddav_addressbook_contacts_count();
		return new rcube_result_set($count, ($this->list_page - 1) * $this->page_size);
	}

	/**
	 * @see		rcube_addressbook::get_record_groups()
	 * @param	integer		$id
	 * @return	array
	 */
	public function get_record_groups($id)
	{
    $results = array();
    if (!$this->groups)
      return $results;

    $sql_result = $this->db->query(
      "SELECT cgm.contactgroup_id, cg.name FROM " . get_table_name($this->db_groupmembers) . " AS cgm" .
      " LEFT JOIN " . get_table_name($this->db_groups) . " AS cg ON (cgm.contactgroup_id = cg.contactgroup_id AND cg.del<>1)" .
      " WHERE cgm.contact_id=?",
      $id
    );
    while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
      $results[$sql_arr['contactgroup_id']] = $sql_arr['name'];
    }
    return $results;
	}

	/**
	 * @see		rcube_addressbook::create_group()
	 * @param	string		$name
	 * @return	array
	 */
	public function create_group($name)
	{
    $result = false;

    // make sure we have a unique name
    $name = $this->unique_groupname($name);
    $this->db->query(
      "DELETE FROM ".get_table_name($this->db_groups).
      " WHERE ".
      " del=? AND name=? AND user_id=?", 1, $name, $this->user_id
    );
    $this->db->query(
      "INSERT INTO ".get_table_name($this->db_groups).
      " (user_id, changed, name, addressbook)".
      " VALUES (".intval($this->user_id).", ".$this->db->now().", ".$this->db->quote($name).", ".$this->db->quote($this->addressbook) .")"
    );

    if ($insert_id = $this->db->insert_id($this->db_groups))
      $result = array('id' => $insert_id, 'name' => $name);

    return $result;
	}

	/**
	 * @see		rcube_addressbook::delete_group()
	 * @param	integer		$gid
	 * @return	boolean
	 */
	public function delete_group($gid)
	{
    // flag group record as deleted
    $sql_result = $this->db->query(
      "UPDATE ".get_table_name($this->db_groups).
      " SET del=1, changed=".$this->db->now().
      " WHERE contactgroup_id=?".
      " AND user_id=?",
      $gid, $this->user_id
    );
    $this->cache = null;
    $ret = $this->db->affected_rows();
    if($ret){
      $this->group_membership_sync($gid);
    }
    return $ret;
	}

	/**
	 * @see	rcube_addressbook::rename_group()
	 * @param	integer		$gid
	 * @param	string		$newname
	 * @return	boolean
	 */
	public function rename_group($gid, $newname, &$new_gid)
	{
    $rcmail = rcmail::get_instance();
    // make sure we have a unique name
    $name = $this->unique_groupname($newname);

    $sql_result = $this->db->query(
      "UPDATE ".get_table_name($this->db_groups).
      " SET name=?, changed=".$this->db->now().
      " WHERE contactgroup_id=?".
      " AND user_id=?",
      $name, $gid, $this->user_id
    );
    $ret = $this->db->affected_rows() ? $name : false;
    if($ret){
      $this->group_membership_sync($gid);
    }
    return $ret;
	}
	
	/**
	 * sync categories
	 * @ param integer $gid (group id)
	 */
	public function group_membership_sync($gid)
	{
    $rcmail = rcmail::get_instance();
    $sql = 'SELECT * FROM ' . get_table_name('carddav_contactgroupmembers') . ' WHERE contactgroup_id=?';
    $res = $this->db->query($sql, $gid);
    while($member = $this->db->fetch_assoc($res)){
      $members[] = $member;
    }
    if(is_array($members)){
      foreach($members as $member){
        $sql = 'SELECT * FROM ' . get_table_name('carddav_contacts') . ' WHERE user_id=? AND carddav_contact_id=?';
        $res = $this->db->query($sql, $rcmail->user->ID, $member['contact_id']);
        $contact = $this->db->fetch_assoc($res);
        $vcard = new rcube_vcard();
        $vcard->extend_fieldmap(array('categories' => 'CATEGORIES'));
        $vcard->load($contact['vcard']);
        $save_data = $vcard->get_assoc();
        $save_data['categories'][0] = array();
        $sql = 'SELECT contactgroup_id FROM ' . get_table_name('carddav_contactgroupmembers') . ' WHERE contact_id=?';
        $res = $this->db->query($sql, $member['contact_id']);
        while($group_id = $rcmail->db->fetch_assoc($res)){
          $group_ids[] = $group_id;
        }
        if(count($group_ids) > 0){
          $categories = '';
          foreach($group_ids as $group_id){
            $sql = 'SELECT name FROM ' . get_table_name('carddav_contactgroups') . ' WHERE contactgroup_id=? AND del=?';
            $res = $this->db->query($sql, $group_id['contactgroup_id'], 0);
            if($category = $rcmail->db->fetch_assoc($res)){
              $categories .= $category['name'] .',';
            }
          }
          if($categories){
            $save_data['categories'] = substr($categories, 0, strlen($categories) - 1);
          }
        }
        $this->update($member['contact_id'], $save_data);
      }
    }
	}

	/**
	 * @see		rcube_addressbook::add_to_group()
	 * @param	integer		$group_id
	 * @param	array		$ids
	 * @return	boolean
	 */
	public function add_to_group($group_id, $ids)
	{
    if(!$group_id){
      $group_id = get_input_value('_gid', RCUBE_INPUT_POST);
      if(!$group_id){
        return 0;
      }
    }
    if (!is_array($ids))
      $ids = explode(self::SEPARATOR, $ids);

    $added = 0;
    $exists = array();

    // get existing assignments ...
    $sql_result = $this->db->query(
      "SELECT contact_id FROM ".get_table_name($this->db_groupmembers).
      " WHERE contactgroup_id=?".
      " AND contact_id IN (".$this->db->array2list($ids, 'integer').")",
      $group_id
    );
    while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
      $exists[] = $sql_arr['contact_id'];
    }
    // ... and remove them from the list
    $ids = array_diff($ids, $exists);

    foreach ($ids as $contact_id) {
      $this->db->query(
        "INSERT INTO ".get_table_name($this->db_groupmembers).
        " (contactgroup_id, contact_id, created)".
        " VALUES (?, ?, ".$this->db->now().")",
        $group_id,
        $contact_id
      );

      if ($error = $this->db->is_error())
        $this->set_error(self::ERROR_SAVING, $error);
      else
        $added++;
        $this->update($contact_id);
      }

    return $added;
	}

	/**
	 * @see		rcube_addressbook::remove_from_group()
	 * @param	integer		$group_id
	 * @param	array		$ids
	 * @return	boolean
	 */
	public function remove_from_group($group_id, $ids)
	{
    if (!is_array($ids))
      $ids = explode(self::SEPARATOR, $ids);

    $ids = $this->db->array2list($ids, 'integer');

    $sql_result = $this->db->query(
      "DELETE FROM ".get_table_name($this->db_groupmembers).
      " WHERE contactgroup_id=?".
      " AND contact_id IN ($ids)",
      $group_id
    );
    $rows = $this->db->affected_rows();
    $ids = explode(',', $ids);
    foreach ($ids as $contact_id) {
      $this->update($contact_id);
    }
    return $rows;
	}

  /**
  * Check for existing groups with the same name
  *
  * @param string Name to check
  * @return string A group name which is unique for the current use
  */
  private function unique_groupname($name)
  {
    $checkname = $name;
    $num = 2; $hit = false;
    do {
      $sql_result = $this->db->query(
        "SELECT 1 FROM ".get_table_name($this->db_groups).
        " WHERE del<>1".
            " AND user_id=?".
            " AND name=?",
        $this->user_id,
        $checkname);

      // append number to make name unique
      if ($hit = $this->db->num_rows($sql_result))
        $checkname = $name . ' ' . $num++;
      } while ($hit > 0);

    return $checkname;
  }

	/**
	 * Creates a new CardDAV addressbook contact
	 *
	 * @param	array		$save_data	Associative array with save data
	 * @param	boolean		$check		Check if the e-mail address already exists
	 * @return	mixed					The created record ID on success or false on error
	 */
	function insert($save_data, $check = false)
	{
		$rcmail = rcmail::get_instance();

		if (!is_array($save_data))
		{
			return false;
		}

		if ($check !== false)
		{
			foreach ($save_data as $col => $values)
			{
				if (strpos($col, 'email') === 0)
				{
					foreach ((array)$values as $email)
					{
						if ($existing = $this->search('email', $email, false, false))
						{
							break 2;
						}
					}
				}
			}
		}
		$group_id = get_input_value('_gid', RCUBE_INPUT_POST);
		if($group_id){
      $sql = 'SELECT * FROM ' . get_table_name('carddav_contactgroups') . ' WHERE contactgroup_id=? AND user_id=? AND del=?';
      $res = $rcmail->db->query($sql, $group_id, $rcmail->user->ID, 0);
      $tmp = $rcmail->db->fetch_assoc($res);
      $save_data['categories'] = $tmp['name'];
    }
		$database_column_contents = $this->get_database_column_contents($save_data);
		return $this->carddav_add($database_column_contents['vcard']);
	}

	/**
	 * Updates a CardDAV addressbook contact
	 *
	 * @param	integer		$carddav_contact_id		CardDAV contact id
	 * @param	array		$save_data				vCard parameters
	 * @return	boolean
	 */
	public function update($carddav_contact_id, $save_data = false)
	{
		$rcmail = rcmail::get_instance();
		$record = $this->get_record($carddav_contact_id, true);
		if(!$save_data){
		  $save_data = $record;
		}
		$sql = 'SELECT contactgroup_id FROM ' . get_table_name('carddav_contactgroupmembers') . ' WHERE contact_id=?';
		$res = $rcmail->db->query($sql, $carddav_contact_id);
		while($group_id = $rcmail->db->fetch_assoc($res)){
		  $group_ids[] = $group_id;
		}
		if(count($group_ids) > 0){
		  $categories = '';
      foreach($group_ids as $group_id){
		    $sql = 'SELECT name FROM ' . get_table_name('carddav_contactgroups') . ' WHERE contactgroup_id=? AND del=?';
		    $res = $rcmail->db->query($sql, $group_id['contactgroup_id'], 0);
		    if($category = $rcmail->db->fetch_assoc($res)){
		      $categories .= $category['name'] .',';
		    }
      }
      if($categories){
        $save_data['categories'] = substr($categories, 0, strlen($categories) - 1);
      }
    }
		$database_column_contents = $this->get_database_column_contents($save_data, $record);
		return $this->carddav_update($carddav_contact_id, $database_column_contents['vcard']);
	}

	/**
	 * Deletes one or more CardDAV addressbook contacts
	 *
	 * @param	array   	$carddav_contact_ids	Record identifiers
	 * @param	boolean		$force
	 * @return	boolean
	 */
	public function delete($carddav_contact_ids, $force = true)
	{
	if (!is_array($carddav_contact_ids))
		{
			$carddav_contact_ids = explode(self::SEPARATOR, $carddav_contact_ids);
		}
		return $this->carddav_delete($carddav_contact_ids);
	}

	/**
	 * Convert vCard changes and return database relevant fileds including contents
	 *
	 * @param	array	$save_data					New vCard values
	 * @param	array 	$record						Original vCard
	 * @return	array	$database_column_contents	Database column contents
	 */
	private function get_database_column_contents($save_data, $record = array())
	{
		$words = '';
		$database_column_contents = array();
		$vcard = new rcube_vcard($record['vcard'] ? $record['vcard'] : $save_data['vcard']);
		$vcard->extend_fieldmap(array('categories' => 'CATEGORIES'));
		$vcard->reset();

		foreach ($save_data as $key => $values)
		{
			list($field, $section) = explode(':', $key);
			$fulltext = in_array($field, $this->fulltext_cols);

			foreach ((array)$values as $value)
			{
				if (isset($value))
				{
					$vcard->set($field, $value, $section);
				}

				if ($fulltext && is_array($value))
				{
					$words .= ' ' . self::normalize_string(join(" ", $value));
				}
				else if ($fulltext && strlen($value) >= 3)
				{
					$words .= ' ' . self::normalize_string($value);
				}
			}
		}

		$database_column_contents['vcard'] = $vcard->export(false);
		
		foreach ($this->table_cols as $column)
		{
			$key = $column;

			if (!isset($save_data[$key]))
			{
				$key .= ':home';
			}
			if (isset($save_data[$key]))
			{
				$database_column_contents[$column] = is_array($save_data[$key]) ? implode(',', $save_data[$key]) : $save_data[$key];
			}
		}

		$database_column_contents['email'] = implode(', ', $vcard->email);
		$database_column_contents['words'] = trim(implode(' ', array_unique(explode(' ', $words))));

		return $database_column_contents;
	}
	
	/**
	 * Extended write log with pre defined logfile name and add version before the message content
	 *
	 * @param	string	$message	Log message
	 * @return	void
	 */
	public function write_log($message)
	{
		carddav::write_log(' carddav_server_id: ' . $this->carddav_server_id . ' | ' . $message);
	}
	
  /**
   * Check the given data before saving.
   * If input not valid, the message to display can be fetched using get_error()
   *
   * @param array Assoziative array with data to save
   * @param boolean Try to fix/complete record automatically
   * @return boolean True if input is valid, False if not.
   */
  public function validate(&$save_data, $autofix = false)
  {
    // validate e-mail addresses
    $valid = parent::validate($save_data, $autofix);

    // require at least one e-mail address (syntax check is already done)
    /* https://code.google.com/p/myroundcube/issues/detail?id=534
    if ($valid && !array_filter($this->get_col_values('email', $save_data, true))) {
      $this->set_error(self::ERROR_VALIDATE, 'noemailwarning');
      $valid = false;
    }
    */
    return $valid;
  }
}
