<?
require_once('init.php');
ldap_login();

header('Content-Type: text/xml; charset=utf-8');

/*
echo '<bla><[!CDATA[';
print_r($_REQUEST);
echo ']]></bla>';
*/

$FIELD = preg_replace('/entry\[/','',$_REQUEST['field']);
$FIELD = preg_replace('/\W+/','',$FIELD);

if($FIELD == 'marker'||$FIELD == 'markers'){
  ajax_taglookup($_REQUEST['value']);
}else{
  ajax_lookup($FIELD,$_REQUEST['value']);
}

/*
if(!empty($_REQUEST['taglookup'])){
  ajax_taglookup($_REQUEST['taglookup']);
}elseif(!empty($_REQUEST['lookup']) && !empty($_REQUEST['s'])){
  ajax_lookup($_REQUEST['lookup'],$_REQUEST['s']);
}elseif(!empty($_REQUEST['addnote'])){
  ajax_addnote($_REQUEST['addnote'],$_REQUEST['note']);
}elseif(!empty($_REQUEST['settags'])){
  ajax_settags($_REQUEST['settags'],$_REQUEST['tags']);
}
*/


/**
 * Add a note to the existing notes
 */
function ajax_addnote($dn,$note){
  global $conf;
  global $LDAP_CON;
  global $FIELDS;

  // fetch the existing note
  $result = ldap_search($LDAP_CON,$dn,'(objectClass=inetOrgPerson)',array($FIELDS['note']));
  if(ldap_count_entries($LDAP_CON,$result)){
    $result = ldap_get_binentries($LDAP_CON, $result);
  }
  $note = $note."\n\n".$result[0][$FIELDS['note']][0];
  $note = preg_replace("!\n\n\n+!","\n\n",$note);

  $entry[$FIELDS['note']] = $note;
  ldap_modify($LDAP_CON,$dn,$entry);


  require_once(dirname(__FILE__).'/smarty/plugins/modifier.noteparser.php');
  print smarty_modifier_noteparser($note);
}

/**
 * Sett tags for a contact
 */
function ajax_settags($dn,$tags){
  global $conf;
  global $LDAP_CON;
  global $FIELDS;
  if(!$FIELDS['_marker']) return;

  $tags = explode(',',$tags);
  $tags = array_map('trim',$tags);
  $tags = array_unique($tags);
  $tags = array_diff($tags, array('')); //strip empty ones

  $entry[$FIELDS['_marker']] = $tags;
  ldap_mod_replace($LDAP_CON,$dn,$entry);

  foreach ($tags as $tag){
    print '<a href="index.php?marker=';
    print rawurlencode($tag);
    print '" class="tag">';
    print htmlspecialchars($tag);
    print '</a> ';
  }
}

/**
 * Find all tags (markers) starting with the given
 * string
 */
function ajax_taglookup($tag){
  global $conf;
  global $LDAP_CON;
  global $FIELDS;
  if(!$FIELDS['_marker']) return;

  $search = ldap_filterescape($tag);
  $filter = "(&(objectClass=inetOrgPerson)(".$FIELDS['_marker']."=$search*))";
  $result = ldap_queryabooks($filter,$FIELDS['_marker']);

  if(!count($result)) return;

  $tags = array();
  foreach ($result as $entry){
    if(count($entry[$FIELDS['_marker']])){
      foreach($entry[$FIELDS['_marker']] as $marker){
        if(preg_match('/^'.preg_quote($tag,'/').'/i',$marker)){
          array_push($tags, strtolower($marker));
        }
      }
    }
  }

  $tags = array_unique($tags);
  sort($tags,SORT_STRING);

  echo '<?xml version="1.0"?>'.NL;
  echo '<ajaxresponse>'.NL;
  foreach($tags as $out){
    echo '<item>'.NL;
    echo '<value>'.htmlspecialchars($out).'</value>'.NL;
    echo '<text>'.htmlspecialchars($out).'</text>'.NL;
    echo '</item>'.NL;
  }
  echo '</ajaxresponse>'.NL;
}

/**
 * Do a simple lookup in any simple field
 */
function ajax_lookup($field,$search){
    global $conf;
    global $LDAP_CON;
    global $FIELDS;

    if(!$FIELDS[$field]) return;
    $field = $FIELDS[$field];

    $search = ldap_filterescape($search);
    $filter = "(&(objectClass=inetOrgPerson)($field=$search*))";
    $result = ldap_queryabooks($filter,$field);
    if(!count($result)) return;

    $items = array();
    foreach ($result as $entry){
        if(isset($entry[$field]) && !empty($entry[$field])){
            $items[] = $entry[$field][0];
        }
    }

    $items = array_unique($items);
    sort($items,SORT_STRING);

    echo '<?xml version="1.0"?>'.NL;
    echo '<ajaxresponse>'.NL;
    foreach($items as $out){
        echo '<item>'.NL;
        echo '<value>'.htmlspecialchars($out).'</value>'.NL;
        echo '<text>'.htmlspecialchars($out).'</text>'.NL;
        echo '</item>'.NL;
    }
    echo '</ajaxresponse>'.NL;
}

?>
