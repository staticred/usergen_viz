<?php
/**
 * @file
 * Common functions for user-generated visualizations
 */


/**
 * Helper function to return list of content types.
 *
 * @return mixed
 *   Returns a multidimensional array that includes name, type,
 *   table name in DB, and array of content fields for all content types
 *   installed.
 */
 
function usergenviz_getcontenttypes() {

  // Get a list of content types configured on the system.
  $content_info = node_type_get_types();
  
  $content_tables = array();

  // Get info about each content type configured on the system.
  foreach ($content_info as $type) {

    // Get information about the content type.
    $type_info = field_info_instances('node', $type->type);
    
    foreach ($type_info as $field_type => $field_details) {
      // Get DB tables for the content fields.  We need this for
      // Database queries, and it shifts based on field setup.
      $tablename = "content_" . $field_name;
      $content_tables[$type->type]['label'] = $type->name;
      $content_tables[$type->type]['field_name'] = $field_type;
      $field_info = field_info_field($field_type);
      $content_tables[$type->type]['field_type'] = $field_info['type'];
      $content_tables[$type->type]['table'] = $tablename;
      $content_tables[$type->type]['fields'][$field_type] = $field_info;
      $content_tables[$type->type]['fields'][$field_type]['type'] = $field_info['type'];
    }

    // Get info about each field.
/*
    foreach ($type_info['fields'] as $key => $value) {
      $content_tables[$type->type]['fields'][$key]['field_name'] = $value['field_name'];
      $content_tables[$type->type]['fields'][$key]['type'] = $value['type'];
    }
*/
  }
  // Send it back.  
  
  return ($content_tables);
}

/**
 * Function to get list of fields in the database matching the 'location' type.
 *
 * This is so we can bring this back to admin functions, etc
 *
 * @param string $fieldname
 *   name of field to return
 * @param string $fieldtype
 *   type of field to return
 *
 * @return mixed
 *   returns array with list of fields, or FALSE if no fields found
 *   with the following format:
 *
 * array(1) {
 *   ["field_name"]=>
 *   array(3) {
 *     ["name"]=>
 *     string(14) "Name of field (Label)"
 *     ["table"]=>
 *     string(27) "content_field_table"
 *     ["column"]=>
 *     string(23) "field_field_id"
 *   }
 * }
 *
 * For example, if looking for a list of 'location' fields, the following might
 * be returned:
 *
 * array(3) {
 *   ["field_film_location"]=>
 *   array(3) {
 *     ["name"]=>
 *     string(14) "Shoot Location"
 *     ["table"]=>
 *     string(27) "content_field_film_location"
 *     ["column"]=>
 *     string(23) "field_film_location_lid"
 *   }
 *   ["field_distribution_location"]=>
 *   array(3) {
 *     ["name"]=>
 *     string(21) "Distribution Location"
 *     ["table"]=>
 *     string(35) "content_field_distribution_location"
 *     ["column"]=>
 *     string(31) "field_distribution_location_lid"
 *   }
 *   ["field_interviewlocation"]=>
 *   array(3) {
 *     ["name"]=>
 *     string(14) "Panel Location"
 *     ["table"]=>
 *     string(31) "content_field_interviewlocation"
 *     ["column"]=>
 *     string(27) "field_interviewlocation_lid"
 *   }
 * }
 */
function usergenviz_getfields($fieldname = '', $fieldtype = '') {

  // Get a list of content fields (CCK) configured on the system.
//  $fields = field_info_field($fieldname);
  $fields = field_info_fields();
  $loc_fields = array();
  $i = 0;

  // we're only interested in the fields we've passed to the
  // function call, of course.
  foreach ($fields as $key => $field) {
    
    if (isset($fieldname) && $key == $fieldname) {
      return ($fields[$fieldname]);    
    }
    
    if ($fieldtype <> "") {
      if ($field['type'] == $fieldtype) {
        $loc_fields[$key] = $field;
      }
    }
    else {
      $loc_fields[$key] = $field;
    }
    $i++;
  }

  if (sizeof($loc_fields) > 0) {
    return $loc_fields;
  }
  else {
    return FALSE;
  }

}

 /**
 * Retrieves a list of saved timelines or maps.
 *
 * @param int $uid
 *   Get list of saved timelines for a given user.
 * @param array $data
 *   An array of data to check. This should pass along an array with the
 *   following keys (in order: filters, types, sources, fromdate, todate.
 * @param string $viztype
 *   The type of data to return. e.g. 'timeline'
 */
function usergenviz_get_saved($uid = '', $viztype='', $data = '') {
  global $user;

  if ($uid == '') {
    $uid = $user->uid;
  }

  if (isset($data['uid'])) {
    unset($data['uid']);
  }

  switch ($viztype) {

    case 'timeline':
    
      $query = db_select('usergen_timeline_saved', 'uts')
        ->fields('uts', array('uid','vid','usergen_timeline_saved_title','usergen_timeline_saved_value','created'))
        ->condition('uid', $user->uid, '=');
      
      if (isset($data) && $data <> "") {
        $query->condition('usergen_timeline_saved_value', serialize($data), '=');
      }

      break;

    case 'map':
      $query = db_select('usergen_map_saved', 'ums')
        ->fields('ums', array('uid','vid','usergen_map_saved_title','usergen_map_saved_value','created'))
        ->condition('uid', $user->uid, '=');
      
      if (isset($data) && $data <> "") {
        $query->condition('usergen_map_saved_value', serialize($data), '=');
      }
      break;

    // If no $viztype is supplied, we can't go any further. Log an error message
    // and return false.
    default:

      return FALSE;
      break;
  }

  // We have to call _db_query here, as db_query() strips out the curly brackets
  // in the conditional for usergen_timeline_saved_value. This causes a known
  // issue where we may run into problems with table prefixes.  This method
  // bypasses the problem.
  $results = $query->execute();

  while ($row = $results->fetchAssoc()) {
    $vars[] = $row;
  }

  return $vars;
}


/**
 * Lists all saved maps for a user
 *
 * @param string $viztype
 *   The type of data to return. e.g. 'timeline'
 *
 * @return string page contents.
 */
function usergenviz_list_saved($viztype = '') {
  global $user;
  $page_contents = "";

  switch ($viztype) {
    case 'timeline':
      $saveditems = usergenviz_get_saved($user->uid, 'timeline');
      break; 

    case 'map':
      $saveditems = usergenviz_get_saved($user->uid, 'map');
      break;
    }

  $links = array();
  foreach ($saveditems as $saveditem) {
    $params = unserialize($saveditem['usergen_timeline_saved_value']);

    $url = url('usergen/' . $viztype . '/results') . "?" . http_build_query($params);


    $alltypes = usergenviz_getcontenttypes();

    $type_name = $alltypes[$params['types']]['name'];
    unset($alltypes);

    $datasource_info = usergenviz_getfields($params['sources']);
    $datasource_name = $datasource_info['widget']['label'];
    unset($datasource_info);

    $criteria = sprintf(t("Pages of type %s, using the data source %s"), $type_name, $datasource_name);
    if (isset($params['fromdate'])) {
      $criteria .= " " . sprintf(t("from %s"), $params['fromdate']);
    }
    if (isset($params['todate'])) {
      $criteria .= " " . sprintf(t("to %s"), $params['todate']);
    }

    $links[$i]['save'] = '<a href="' . $url . '">' . $criteria . '</a>';
    $links[$i]['delete'] = '<a href="' . url('usergen/' . $viztype . '/remove') . '?' . http_build_query($params) . '" title="' . t('Forget this {$viztype}') . '">[x]</a>';
    $i++;
  }

  if (sizeof($links) > 0) {
    $page_contents .= '<ul id="saved_' . $viztype . ' class="list">';
    foreach ($links as $link) {
      $page_contents .= "<li>{$link['save']} {$link['delete']}</li>";
    }
    $page_contents .= "</ul>";
  }
  else {
    $page_contents .= "<p>" . t('No saved timelines found.') . ' <a href="' . url('usergen/timeline') . '">' . t('Build another timeline') . '?</a></p>';
  }

  return $page_contents;

}
