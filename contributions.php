<?php

require_once 'contributions.civix.php';
use CRM_Contributions_ExtensionUtil as E;

function contributions_civicrm_export(&$exportTempTable, &$headerRows, &$sqlColumns, &$exportMode, &$componentTable, &$ids) {
  // Check if it's an export of contributions
  if ($exportMode == 2) {
    // overwrite the standard sqlColumns and headerRows
    $sqlColumns = [];
    $sqlColumns['apbnr'] = 'apbnr int';
    $sqlColumns['overname'] = 'overname int';
    $sqlColumns['jaar'] = 'jaar int';
    $sqlColumns['maand'] = 'maand int';
    $sqlColumns['oorspr'] = 'oorspr varchar(255)';
    $sqlColumns['refnr'] = 'refnr int';
    $sqlColumns['waarde'] = 'waarde int';
    $sqlColumns['hoevh'] = 'hoevh int';
    $sqlColumns['product'] = 'product varchar(255)';
    $sqlColumns['omschrijving'] = 'omschrijving varchar(255)';
    $sqlColumns['username'] = 'username varchar(255)';
    $sqlColumns['prog'] = 'prog varchar(255)';
    $sqlColumns['creatdate'] = 'creatdate varchar(255)';
    $sqlColumns['sendflag'] = 'sendflag int';
    $sqlColumns['senddate'] = 'senddate varchar(255)';
    $sqlColumns['e_waarde'] = 'e_waarde decimal';

    $headerRows = [];
    foreach ($sqlColumns as $k => $v) {
      $headerRows[] = $k;
    }

    // create a new temp table (if it does not exist)
    $sql = 'CREATE TABLE IF NOT EXISTS temp_kava_contrib_export (' . implode(', ', $sqlColumns) . ')';
    CRM_Core_DAO::executeQuery($sql);

    // clear the temp table
    $sql = 'truncate table temp_kava_contrib_export';
    CRM_Core_DAO::executeQuery($sql);

    // insert the contributions
    $sql = "
      insert into 
        temp_kava_contrib_export 
      select 
         round(cx.klantnummer_kava_203 / 10, 0) apbnr,
         cx.klantnummer_kava_203 - (round(cx.klantnummer_kava_203 / 10, 0) * 10) overname,
         year(cb.receive_date) jaar,
         month(cb.receive_date) maand, 
         '???' oorspr,
         cb.id refnr,
         0 as waarde,
         0 hoevh,
         0 product,
         '???' omschrijving,
         0 username,
         0 prog,
         NOW() creatdate,
         0 sendflag,
         NOW() senddate,
         0 e_waarde 
      from 
        civicrm_contact c
      inner join
        civicrm_value_contact_extra cx on cx.entity_id = c.id
      inner join
        civicrm_contribution cb on c.id = cb.contact_id
      where
        cb.id in (select civicrm_primary_id from $exportTempTable)      
    ";
    CRM_Core_DAO::executeQuery($sql);

    // replace the standard temp table with ours
    $exportTempTable = 'temp_kava_contrib_export';
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function contributions_civicrm_config(&$config) {
  _contributions_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function contributions_civicrm_xmlMenu(&$files) {
  _contributions_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function contributions_civicrm_install() {
  _contributions_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function contributions_civicrm_postInstall() {
  _contributions_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function contributions_civicrm_uninstall() {
  _contributions_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function contributions_civicrm_enable() {
  _contributions_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function contributions_civicrm_disable() {
  _contributions_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function contributions_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _contributions_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function contributions_civicrm_managed(&$entities) {
  _contributions_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function contributions_civicrm_caseTypes(&$caseTypes) {
  _contributions_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function contributions_civicrm_angularModules(&$angularModules) {
  _contributions_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function contributions_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _contributions_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function contributions_civicrm_entityTypes(&$entityTypes) {
  _contributions_civix_civicrm_entityTypes($entityTypes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function contributions_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function contributions_civicrm_navigationMenu(&$menu) {
  _contributions_civix_insert_navigation_menu($menu, 'Mailings', array(
    'label' => E::ts('New subliminal message'),
    'name' => 'mailing_subliminal_message',
    'url' => 'civicrm/mailing/subliminal',
    'permission' => 'access CiviMail',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _contributions_civix_navigationMenu($menu);
} // */
