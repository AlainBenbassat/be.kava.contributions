<?php

class CRM_Contributions_Task_ExportContribs extends CRM_Contribute_Form_Task {
  public $_single = FALSE;
  protected $_rows;

  function preProcess() {
    parent::preProcess();

    // some magic...
    $qfKey = CRM_Utils_Request::retrieve('qfKey', 'String', $this);
    $urlParams = 'force=1';
    if (CRM_Utils_Rule::qfKey($qfKey)) {
      $urlParams .= "&qfKey=$qfKey";
    }

    $url = CRM_Utils_System::url('civicrm/contribute/search', $urlParams);
    $breadCrumb = [
      ['url' => $url, 'title' => ts('Search Results'),]
    ];

    CRM_Utils_System::appendBreadCrumb($breadCrumb);
    CRM_Utils_System::setTitle(ts('Bijdragen exporteren'));

    $session = CRM_Core_Session::singleton();
    $this->_userContext = $session->readUserContext();
  }

  public function buildQuickForm() {
    $this->addButtons(array(
      array(
        'type' => 'next',
        'name' => 'Exporteer de bijdragen voor KAVA facturatie.',
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'back',
        'name' => ts('Cancel'),
      ),
    ));
  }

  public function postProcess() {
    // we work with sqlColumns and headerRows to match the regular and Excel export format
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

    // select the contributions
    $sql = "
      select 
         if(length(cx.klantnummer_kava_203) < 7, cx.klantnummer_kava_203, round(cx.klantnummer_kava_203 / 10, 0)) apbnr,
         if(length(cx.klantnummer_kava_203) < 7, -1, cx.klantnummer_kava_203 - (round(cx.klantnummer_kava_203 / 10, 0) * 10)) overname,
         year(cb.receive_date) jaar,
         month(cb.receive_date) maand, 
         'bijdrage CiviCRM' oorspr,
         cb.id refnr,
         0 as waarde,
         li.qty hoevh,
         li.label product,
         concat(cb.source, ': ', ifnull(cbscont.display_name, c.display_name)) omschrijving,
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
      left outer join
        civicrm_line_item li on li.contribution_id = cb.id
      left outer join
        civicrm_contribution_soft cbs on cbs.contribution_id = cb.id
      left outer join
        civicrm_contact cbscont on cbscont.id = cbs.contact_id
      where
        cb.id in (" . implode(',', $this->_contributionIds) . ")      
    ";
    $dao = CRM_Core_DAO::executeQuery($sql);

    $rows = [];
    while ($dao->fetch()) {
      $row = [];
      foreach ($sqlColumns as $column => $dontCare) {
        $row[$column] = $dao->$column;
      }

      $rows[] = $row;
    }

    $dao->free();

    CRM_CiviExportExcel_Utils_SearchExport::export2excel2007($headerRows, $sqlColumns, $rows);
  }
}
