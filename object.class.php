<?php

//require_once("base.class.php");

class ObjectAttributeNotFoundException extends Exception {}
class ObjectNotFoundException extends Exception {}

class object extends base
{
  public $id = NULL;
  public $ring = NULL;
  public $type = NULL;
  public $value = NULL;
  public $created = NULL;
  public $modified = NULL;
  public $children = NULL;
  public $parents = NULL;
  public $associations = NULL;
  public $attributes = NULL;
  public $dbpdo = NULL;
  public $unsaved = false;
  public $new = true;

  function __autoload($class)
  {
    require_once("$class.class.php");
  }

  function __construct($dbpdo, $id = NULL, $attribute_type = NULL)
  {
    parent::__construct($dbpdo->config);
    $this->dbpdo = $dbpdo;
    if($id !== NULL)
      {
	if($attribute_type !== NULL)
	  {
	    $this->lookup_with_attribute($id, $attribute_type);
	  }
	else
	  {
	    $this->lookup($id);
	  }
      }
  }

  function use_connection($mh)
  {
    $this->mh = $mh;
  }

  function lookup($id)
  {
    if(config::use_memcache)
      {
	$data = $this->memcache_get('v3_object_' . $id);
	if(!$data)
	  {
	    $data = $this->dbpdo->query("SELECT * FROM `objects` WHERE `id` = ?", array($id));
	    if(count($data) == 0)
	      throw new ObjectNotFoundException;
	    $data = $data[0];
	    $this->memcache_set('v3_object_' . $id, $data);
	  }
      }
    else
      {
	$data = $this->dbpdo->query("SELECT * FROM `objects` WHERE `id` = ?", array($id));
	if(count($data) == 0)
	  throw new ObjectNotFoundException;
	$data = $data[0];
      }

    $this->new = false;

    $this->id = $id;
    $this->type = $data['type'];
    $this->value = $data['value'];
    $this->ring = $data['ring'];
    $this->created = $data['creation'];
    $this->modified = $data['modification'];
    $this->unsaved = false;
  }

  function lookup_with_attribute($id, $attribute_type)
  {
    $q = "SELECT o.id AS o_id, o.type AS o_type, o.value AS o_value, o.ring AS o_ring, o.creation AS o_creation, o.modification AS o_modification, oa.id AS oa_id, oa.type AS oa_type, oa.value AS oa_value, oa.ring AS oa_ring FROM objects AS o LEFT OUTER JOIN object_attributes AS oa ON oa.object_id = o.id WHERE oa.type = ? AND o.id = ?";
    if(config::use_memcache)
      {
	$data = $this->memcache_get('v3_object_' . $id . '_with_attribute_' . $attribute_type);
	if(!$data)
	  {
	    $data = $this->dbpdo->query($q, array($attribute_type, $id));
	    if(count($data) == 0)
	      throw new ObjectNotFoundException;
	    $this->memcache_set('v3_object_' . $id , '_with_attribute_' . $attribute_type, $data);
	  }
      }
    else
      {
	$data = $this->dbpdo->query($q, array($attribute_type, $id));
	if(count($data) == 0)
	  throw new ObjectNotFoundException;
      }

    $this->new = false;

    $this->id = $id;
    $this->type = $data[0]['o_type'];
    $this->value = $data[0]['o_value'];
    $this->ring = $data[0]['o_ring'];
    $this->created = $data[0]['o_creation'];
    $this->modified = $data[0]['o_modification'];
    $this->unsaved = false;

    $this->attributes = array();
    foreach($data as $attribute)
      {
	$this->attributes[$attribute['oa_type']] = array(
						      'id' => $attribute['oa_id'],
						      'value' => $attribute['oa_value'],
						      'ring' => $attribute['oa_ring'],
						      'modified' => false
						      );
      }
  }

  function update_type($type)
  {
    $this->type = $type;
    $this->unsaved = true;
  }

  function update_value($value)
  {
    $this->value = $value;
    $this->unsaved = true;
  }

  function update_ring($ring)
  {
    $this->ring = $ring;
    $this->unsaved = true;
  }

  function get_attributes($type = '%')
  {
    $this->attributes = array();
    $attributes = $this->dbpdo->query("SELECT * FROM `object_attributes` WHERE `object_id` = ? AND `type` LIKE ?", array($this->id, $type));
    foreach($attributes as $attribute)
      {
	$this->attributes[$attribute['type']] = array(
						      'id' => $attribute['id'],
						      'value' => $attribute['value'],
						      'ring' => $attribute['ring'],
						      'modified' => false
						      );
      }
  }

  function define_attribute($type, $value, $ring)
  {
    if($this->attributes === NULL)
      $this->get_attributes($type);

    if(!isset($this->attributes[$type]))
      {
	$this->attributes[$type] = array(
					 'id' => NULL,
					 'value' => $value,
					 'new' => true,
					 'ring' => $ring
					 );
      }
    else
      {
	if(config::use_memcache)
	  {
	    $this->memcache_delete('v3_object_' . $this->id . '_attribute_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_attribute_%');
	  }
	$this->attributes[$type]['value'] = $value;
	$this->attributes[$type]['modified'] = true;
	$this->attributes[$type]['ring'] = $ring;
      }

    $this->unsaved = true;
  }

  function get_attribute_value($type, $redo_search = true)
  {
    if(config::use_memcache)
      {
	$value = $this->memcache_get('v3_object_' . $this->id . '_attribute_' . $type);
	if($value !== false)
	  return $value;      
    }

    if($redo_search)
      $this->get_attributes($type);
    if(isset($this->attributes[$type]))
      {
	if(config::use_memcache)
	  $this->memcache_set('v3_object_' . $this->id . '_attribute_' . $type, $this->attributes[$type]['value']);
	
	return $this->attributes[$type]['value'];
      }
    else
      throw new ObjectAttributeNotFoundException;
  }

  function remove_attribute($type)
  {
    $this->dbpdo->query("DELETE FROM `object_attributes` WHERE `object_id` = ? AND `type` = ?",
			array(
			      $this->id,
			      $type
			      ));
    if(isset($this->attributes[$type]))
      unset($this->attributes[$type]);
    if(config::use_memcache)
      $this->memcache_delete('v3_object_' . $this->id . '_attribute_' . $type);
  }

  function get_parents($parent_type = '%', $association_type='%', $offset = NULL, $limit = NULL, $orderfield = NULL, $order = NULL)
  {
    $this->parents = array();

    $q = "SELECT p.id AS parent_id, p.type AS parent_type, a.id AS association_id, a.type AS association_type FROM objects AS p INNER JOIN associations AS a ON p.id = a.parent_id WHERE a.child_id = ? AND a.type LIKE ? AND p.type LIKE ?";

    if($offset === NULL && $limit === NULL && config::use_memcache)
      {
	$key = 'v3_object_' . $this->id . '_parents_' . $parent_type . '_' . $association_type;
	$data = $this->memcache_get($key);
	if(!$data)
	  {
	    $data = $this->dbpdo->query($q, array($this->id, $association_type, $parent_type));
	    $this->memcache_set($key, $data);
	  }
      }
    else
      {
	if($orderfield !== NULL && $order !== NULL)
	  $q .= " ORDER BY $orderfield $order";

	if($offset !== NULL)
	  if($limit !== NULL)
	    $q .= " LIMIT $offset, $limit";
	  else
	    $q .= " LIMIT $offset";
	
	$data = $this->dbpdo->query($q, array($this->id, $association_type, $parent_type));
      }
    foreach($data as $assoc)
      {
	if(!isset($this->parents[$assoc['parent_type']]) || !is_array($this->parents[$assoc['parent_type']]))
	  $this->parents[$assoc['parent_type']] = array();
	$this->parents[$assoc['parent_type']][] = $assoc['parent_id'];
	if(!isset($this->associations[$assoc['association_type']]) || !is_array($this->associations[$assoc['association_type']]))
	  $this->associations[$assoc['association_type']] = array();
	$this->associations[$assoc['association_type']][] = $assoc['association_id'];
      }
  }

  function get_children_with_attribute($child_type, $association_type, $attribute)
  {
    if(config::use_memcache)
      {
	$key = 'v3_object_' . $this->id . '_children_' . $child_type . '_association_' . $association_type . '_with_attribute_' . $attribute;
	$data = $this->memcache_get($key);
	if(!$data)
	  $data = $this->dbpdo->query("SELECT o.value, oa.value FROM (associations AS a INNER JOIN objects AS o ON a.parent_id = ? AND o.id = a.child_id AND a.type = ? AND o.type = ?) LEFT OUTER JOIN object_attributes AS oa ON o.id = oa.object_id AND oa.type = ?",
				      array(
					    $this->id,
					    $association_type,
					    $child_type,
					    $attribute
					    ));
	$this->memcache_set($key, $data);
	return $data;
      }
    else
      {
	  return $data = $this->dbpdo->query("SELECT o.value, oa.value FROM (associations AS a INNER JOIN objects AS o ON a.parent_id = ? AND o.id = a.child_id AND a.type = ? AND o.type = ?) LEFT OUTER JOIN object_attributes AS oa ON o.id = oa.object_id AND oa.type = ?",
				      array(
					    $this->id,
					    $association_type,
					    $child_type,
					    $attribute
					    ));
      }

  }

  function get_children($child_type = '%', $association_type='%', $offset = NULL, $limit = NULL, $orderfield = NULL, $order = NULL)
  {
    $this->children = array();
    $q = "SELECT c.id AS child_id, c.type AS child_type, a.id AS association_id, a.type AS association_type FROM objects AS c INNER JOIN associations AS a ON a.child_id = c.id WHERE a.parent_id = ? AND a.type LIKE ? AND c.type LIKE ?";

    if($offset === NULL && $limit === NULL && config::use_memcache)
      {
	$key = 'v3_object_' . $this->id . '_children_' . $child_type . '_' . $association_type;
	$data = $this->memcache_get($key);
	if(!$data)
	  {
	    $data = $this->dbpdo->query($q, array($this->id, $association_type, $child_type));
	    $this->memcache_set($key, $data);
	  }
      }
    else
      {
	if($orderfield !== NULL && $order !== NULL)
	  $q .= " ORDER BY $orderfield $order";
	if($offset !== NULL)
	  if($limit !== NULL)
	    $q .= " LIMIT $offset, $limit";
	  else
	    $q .= " LIMIT $offset";

	$data = $this->dbpdo->query($q, array($this->id, $association_type, $child_type));
      }

    foreach($data as $assoc)
      {
	if(!isset($this->children[$assoc['child_type']]) || !is_array($this->children[$assoc['child_type']]))
	  $this->children[$assoc['child_type']] = array();
	$this->children[$assoc['child_type']][] = $assoc['child_id'];
	if(!isset($this->associations[$assoc['association_type']]) || !is_array($this->associations[$assoc['association_type']]))
	  $this->associations[$assoc['association_type']] = array();
	$this->associations[$assoc['association_type']][] = $assoc['association_id'];
      }
  }

  function add_child($id, $type, $ring)
  {
    return $this->create_association($this->id, $id, $type, $ring);
  }

  function add_parent($id, $type, $ring)
  {
    return $this->create_association($id, $this->id, $type, $ring);
  }

  function get_object_type($id)
  {
    $obj = $this->dbpdo->query("SELECT `type` FROM `objects` WHERE `id` = ?", array($id));
    if(count($obj) > 0)
      return $obj[0]['type'];
    else
      return false;
  }

  function create_association($parent_id, $child_id, $type, $ring)
  {
    $date = $this->timestamp();
    if(config::use_memcache)
      {
	if($this->id == $parent_id)
	  {
	    $child_type = $this->get_object_type($child_id);
	    $this->memcache_delete('v3_object_' . $this->id . '_children_' . $child_type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_children_%_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_children_' . $child_type . '_%');
	    $this->memcache_delete('v3_object_' . $this->id . '_children_%_%');

	    $this->memcache_delete('v3_object_' . $child_id . '_parents_' . $this->type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $child_id . '_parents_%_' . $type);
	    $this->memcache_delete('v3_object_' . $child_id . '_parents_' . $this->type . '_%');
	    $this->memcache_delete('v3_object_' . $child_id . '_parents_%_%');
	  }
	elseif($this->id == $child_id)
	  {
	    $parent_type = $this->get_object_type($parent_id);
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_' . $parent_type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_%_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_' . $parent_type . '_%');
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_%_%');

	    $this->memcache_delete('v3_object_' . $parent_id . '_children_' . $this->type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $parent_id . '_children_%_' . $type);
	    $this->memcache_delete('v3_object_' . $parent_id . '_children_' . $this->type . '_%');
	    $this->memcache_delete('v3_object_' . $parent_id . '_children_%_%');
	  }
      }

    return $this->dbpdo->query("INSERT INTO `associations` (`parent_id`,`type`,`child_id`,`ring`,`creation`,`modification`) VALUES(?, ?, ?, ?, ?, ?)", 
			array(
			      $parent_id,
			      $type,
			      $child_id,
			      $ring,
			      $date,
			      $date
			      ));
  }

  function remove_association($parent_id, $child_id, $type = '%')
  {
    $this->dbpdo->query("DELETE FROM `associations` WHERE `parent_id` = ? AND `child_id` = ? AND `type` LIKE ?", 
			array(
			      $parent_id,
			      $child_id,
			      $type
			      ));
    if(config::use_memcache)
      {
	if($this->id == $parent_id)
	  {
	    $child_type = $this->get_object_type($child_id);
	    $this->memcache_delete('v3_object_' . $this->id . '_children_' . $child_type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_children_%_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_children_' . $child_type . '_%');
	    $this->memcache_delete('v3_object_' . $this->id . '_children_%_%');

	    $this->memcache_delete('v3_object_' . $child_id . '_parents_' . $this->type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $child_id . '_parents_%_' . $type);
	    $this->memcache_delete('v3_object_' . $child_id . '_parents_' . $this->type . '_%');
	    $this->memcache_delete('v3_object_' . $child_id . '_parents_%_%');
	  }
	elseif($this->id == $child_id)
	  {
	    $parent_type = $this->get_object_type($parent_id);
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_' . $parent_type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_%_' . $type);
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_' . $parent_type . '_%');
	    $this->memcache_delete('v3_object_' . $this->id . '_parents_%_%');

	    $this->memcache_delete('v3_object_' . $parent_id . '_children_' . $this->type . '_' . $type);
	    $this->memcache_delete('v3_object_' . $parent_id . '_children_%_' . $type);
	    $this->memcache_delete('v3_object_' . $parent_id . '_children_' . $this->type . '_%');
	    $this->memcache_delete('v3_object_' . $parent_id . '_children_%_%');
	  }
      }
  }
      
  function remove_parent($id, $type = '%')
  {
    $this->remove_association($id, $this->id, $type);
  }

  function remove_child($id, $type = '%')
  {
    $this->remove_association($this->id, $id, $type);
  }

  function define($type, $value, $ring)
  {
    if($this->id !== NULL)
      $this->error("Called define() on an object that already exists.");
    $this->type = $type;
    $this->value = $value;
    $this->ring = $ring;
    $this->unsaved = true;
    $this->save();
  }

  function save()
  {
    $date = $this->timestamp();

    if($this->id === NULL)
      {
	if($this->type === NULL || $this->value === NULL || $this->ring === NULL)
	  $this->error("save() called without all object properties being set.");

	$this->id = $this->dbpdo->query("INSERT INTO `objects` (`type`,`value`,`ring`,`creation`,`modification`) VALUES (?, ?, ?, ?, ?)", 
					array(
					      $this->type,
					      $this->value,
					      $this->ring,
					      $date,
					      $date
					      ));
      }
    elseif($this->unsaved = true)
      {
	$this->dbpdo->query("UPDATE `objects` SET `type` = ?, `value` = ?, `ring` = ?, `modification` = ? WHERE `id` = ?", 
					array(
					      $this->type,
					      $this->value,
					      $this->ring,
					      $date,
					      $this->id
					      ));
	if(config::use_memcache)
	  $this->memcache_delete('v3_object_' . $this->id);
      }

    if($this->attributes !== NULL)
      {
	foreach($this->attributes as $attribute => $info)
	  {
	    if(isset($info['new']) && $info['new'] == true)
	      {
		$this->dbpdo->query("INSERT INTO `object_attributes` (`object_id`,`type`,`value`,`ring`,`creation`,`modification`) VALUES (?, ?, ?, ?, ?, ?)",
				    array(
					  $this->id,
					  $attribute,
					  $info['value'],
					  $info['ring'],
					  $date,
					  $date
					  ));
	      }
	    if(isset($info['modified']) && $info['modified'] == true)
	      {
		$this->dbpdo->query("UPDATE `object_attributes` SET `value` = ?, `ring` = ?, `modification` = ? WHERE `id` = ?", 
				    array(
					  $info['value'],
					  $info['ring'],
					  $date,
					  $info['id']
					  ));
		if(config::use_memcache)
		  $this->memcache_delete('v3_object_' . $this->id . '_attribute_' . $attribute);
	      }
	    $this->get_parents();
	    if(config::use_memcache)
	    foreach($this->parents as $parent_type => $parents)
	      foreach($parents as $parent_id)
	        foreach($this->attributes as $attribute => &$values)
	          foreach($this->associations as $association_type => $association_id)
	            $this->memcache_delete('v3_object_' . $parent_id . '_children_' . $this->type . '_association_' . $association_type . '_with_attribute_' . $attribute);
	  }
      }

    $this->unsaved = false;
  }

  function __destruct()
  {
    if($this->unsaved)
      $this->save();
  }

  function delete()
  {
    $assocs = $this->dbpdo->query("SELECT id, parent_id, child_id, type FROM associations WHERE parent_id = ? OR child_id = ?",
				  array($this->id, $this->id));
    foreach($assocs as $assoc)
      {
	$this->dbpdo->query("DELETE FROM association_attributes WHERE association_id = ?",
			    array($assoc['id']));
	$this->remove_association($assoc['parent_id'],$assoc['child_id'],$assoc['type']);
      }
    
    $attrs = $this->dbpdo->query('SELECT DISTINCT(type) FROM object_attributes WHERE object_id = ?',
				 array($this->id));
    foreach($attrs as $attr)
      $this->remove_attribute($attr['type']);

    $this->dbpdo->query("DELETE FROM objects WHERE id = ?",
			array($this->id));
  }

}

?>