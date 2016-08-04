<?php

/**
 * notes.php
 *
 * Changelog:
 *   2.0 copyright © 2009-2012 Gorlum for http://supernova.ws
 *     [!] Wrote from scratch
 */

use Vector\Vector;

include('common.' . substr(strrchr(__FILE__, '.'), 1));
lng_include('notes');

global $user;

$template = gettemplate('notes', true);

$result = array();
if(($result_message = sys_get_param_str('MESSAGE')) && isset(classLocale::$lang[$result_message])) {
  $result[] = array('STATUS' => sys_get_param_int('STATUS'), 'MESSAGE' => classLocale::$lang[$result_message]);
}

$note_id_edit = sys_get_param_id('note_id_edit');
$note_title_unsafe = sys_get_param_str_unsafe('note_title');
$note_text_unsafe = sys_get_param_str_unsafe('note_text');
if(sys_get_param('note_delete')) {
  try {
    DBStaticNote::processDelete($user, $note_id_edit);
  } catch(Exception $e) {
    $note_id_edit = 0;
    sn_db_transaction_rollback();
    $result[] = array(
      'STATUS'  => in_array($e->getCode(), array(ERR_NONE, ERR_WARNING, ERR_ERROR)) ? $e->getCode() : ERR_ERROR,
      'MESSAGE' => classLocale::$lang[$e->getMessage()],
    );
  }
} elseif(($note_title_unsafe = sys_get_param_str_unsafe('note_title')) || ($note_text_unsafe = sys_get_param_str('note_text'))) {
  $note_title_unsafe == classLocale::$lang['note_new_title'] ? $note_title_unsafe = '' : false;
  $note_text_unsafe == classLocale::$lang['note_new_text'] ? $note_text_unsafe = '' : false;
  try {
    $note_galaxy = max(0, min(sys_get_param_id('note_galaxy'), Vector::$knownGalaxies));
    $note_system = max(0, min(sys_get_param_id('note_system'), Vector::$knownSystems));
    $note_planet = max(0, min(sys_get_param_id('note_planet'), Vector::$knownPlanets + 1));

    if(!$note_text_unsafe && !$note_title_unsafe && !$note_galaxy && !$note_system && !$note_planet) {
      throw new Exception('note_err_note_empty', ERR_WARNING);
    }

    $note_priority = min(sys_get_param_id('note_priority', 2), count($note_priority_classes) - 1);
    $note_planet_type = max(1, min(sys_get_param_id('note_planet_type', 1), count(classLocale::$lang['sys_planet_type'])));
    $note_sticky = intval(sys_get_param_id('note_sticky')) ? 1 : 0;

    sn_db_transaction_start();
    if($note_id_edit) {
      $check_note_id = DBStaticNote::db_note_get_id_and_owner($note_id_edit);
      if(!$check_note_id) {
        throw new Exception('note_err_note_not_found', ERR_ERROR);
      }
    }

    if($note_id_edit) {
      if($check_note_id['owner'] != $user['id']) {
        throw new Exception('note_err_owner_wrong', ERR_ERROR);
      }

      DBStaticNote::db_note_update_by_id($note_priority, db_escape($note_title_unsafe), db_escape($note_text_unsafe), $note_galaxy, $note_system, $note_planet, $note_planet_type, $note_sticky, $note_id_edit);
    } else {
      DBStaticNote::db_note_insert($user['id'], $note_priority, $note_title_unsafe, $note_text_unsafe, $note_galaxy, $note_system, $note_planet, $note_planet_type, $note_sticky);
    }

    sn_db_transaction_commit();
    sys_redirect('notes.php?STATUS=' . ERR_NONE . '&MESSAGE=' . ($note_id_edit ? 'note_err_none_changed' : 'note_err_none_added'));
//    throw new exception($note_id_edit ? 'note_err_none_changed' : 'note_err_none_added', ERR_NONE);
  } catch(Exception $e) {
    $note_id_edit = 0;
    sn_db_transaction_rollback();
    $result[] = array(
      'STATUS'  => in_array($e->getCode(), array(ERR_NONE, ERR_WARNING, ERR_ERROR)) ? $e->getCode() : ERR_ERROR,
      'MESSAGE' => classLocale::$lang[$e->getMessage()],
    );
  }
}

if(!$note_id_edit) {
  note_assign($template, array(
    'id'          => 0,
    'time'        => SN_TIME_NOW,
    'priority'    => 2,
    'planet_type' => PT_PLANET,
    'title'       => classLocale::$lang['note_new_title'],
    'text'        => classLocale::$lang['note_new_text'],
  ));
}

$note_exist = false;
$notes_query = DBStaticNote::db_note_list_by_owner($user['id']);
while($note_row = db_fetch($notes_query)) {
  note_assign($template, $note_row);
  $note_exist = $note_exist || $note_row['id'] == $note_id_edit;
}
$note_id_edit = $note_exist ? $note_id_edit : 0;

foreach($note_priority_classes as $note_priority_id => $note_priority_class) {
  $template->assign_block_vars('note_priority', array(
    'ID'    => $note_priority_id,
    'CLASS' => $note_priority_classes[$note_priority_id],
    'TEXT'  => classLocale::$lang['sys_notes_priorities'][$note_priority_id],
  ));
}

foreach(classLocale::$lang['sys_planet_type'] as $planet_type_id => $planet_type_string) {
  $template->assign_block_vars('planet_type', array(
    'ID'   => $planet_type_id,
    'TEXT' => $planet_type_string,
  ));
}

foreach($result as $result_data) {
  $template->assign_block_vars('result', $result_data);
}

$template->assign_vars(array(
  'PAGE_HEADER'      => classLocale::$lang['note_page_header'],
  'NOTE_ID_EDIT'     => $note_id_edit,
  'NOTE_FULL_RENDER' => true,
));

display($template);
