<?php

use CRM_Contributions_ExtensionUtil as E;

class CRM_Contributions_Form_MakeMembershipContribs extends CRM_Core_Form {
  private $queue;
  private $queueName = 'kavamembershipcontribs';

  public function __construct() {
    // create the queue
    $this->queue = CRM_Queue_Service::singleton()->create([
      'type' => 'Sql',
      'name' => $this->queueName,
      'reset' => FALSE, //do not flush queue upon creation
    ]);

    parent::__construct();
  }

  public function buildQuickForm() {
    $defaults = [];

    // add list of membership types
    $this->add('select','membership_type','1. Soort lidmaatschap',CRM_Member_PseudoConstant::membershipType(),TRUE);

    // add text field for year
    $this->add('text', 'year', '2. Jaar');
    $defaults['year'] = 2019;

    // add text field for amount
    $this->add('text', 'amount', '3. Bedrag van de bijdrage');
    $defaults['amount'] = 1;

    // add the buttons
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => 'Maak bijdragen',
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => 'Annuleer',
      ],
    ]);

    // set defaults
    $this->setDefaults($defaults);

    // add form validation
    $this->addFormRule(['CRM_Contributions_Form_MakeMembershipContribs', 'formRule'], $this);

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public static function formRule($fields, $files, $form) {
    $errors = [];

    // validate the submitted year
    if (CRM_Utils_Type::validate($fields['year'], 'Integer', FALSE) && $fields['year'] >= 1900 && $fields['year'] <= 3000) {
      //OK, valid year
    }
    else {
      $errors['year'] = 'Dit moet een geldig jaartal zijn (tussen 1900 en 3000)';
    }

    // validate the amount
    if (!CRM_Utils_Type::validate($fields['amount'], 'Money', FALSE)) {
      $errors['amount'] = 'Dit moet een geldig bedrag zijn.';
    }

    if (count($errors) == 0) {
      return TRUE;
    }
    else {
      return $errors;
    }
  }

  public function postProcess() {
    $values = $this->exportValues();

    // delete the queue
    $this->queue->deleteQueue();
    $this->fillQueue($values);

    if ($this->queue->numberOfItems() > 0) {
      $this->runQueue();
    }
    else {
      CRM_Core_Session::setStatus('Geen lidmaatschappen gevonden.', 'MakeMembershipContribs', 'warning');
    }

    parent::postProcess();
  }

  private function fillQueue($values) {
    // select all valid memberships of the given type in the given period
    // without contribution
    $sql = "
      select
        m.id
      from
        civicrm_membership m
      left outer join
        civicrm_membership_payment mp on mp.membership_id = m.id
      left outer join
        civicrm_contribution c on mp.contribution_id = c.id and year(c.receive_date) = %2
      where 
        m.membership_type_id = %1
      and
        year(m.join_date) <= %2
      and
	      year(m.end_date) >= %2
	    and 
        mp.id IS NULL
    ";
    $sqlParams = [
      1 => [$values['membership_type'], 'Integer'],
      2 => [$values['year'], 'Integer'],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);

    while ($dao->fetch()) {
      // add them to the queue
      $task = new CRM_Queue_Task(
        ['CRM_Contributions_Form_MakeMembershipContribs', 'createMembershipContribution'],
        [$dao->id, $values['year'], $values['amount']]
      );
      $this->queue->createItem($task);
    }
  }

  private function runQueue() {
    $runner = new CRM_Queue_Runner([
      'title' => 'Aanmaken van bijdragen voor lidmaatschap',
      'queue' => $this->queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_CONTINUE,
      'onEnd' => ['CRM_Contributions_Form_MakeMembershipContribs', 'onEnd'],
      'onEndUrl' => CRM_Utils_System::url('civicrm/membershipcontributions', 'reset=1'),
    ]);

    $runner->runAllViaWeb();
  }

  public static function createMembershipContribution(CRM_Queue_TaskContext $ctx, $membershipID, $year, $amount) {
    // get the membership
    $sql = "
      select 
        m.*
        , f.betaler_257 betaler_id
      from
        civicrm_membership m
      left outer join  
        civicrm_value_facturatie_79 f on f.entity_id = m.id
      where 
        id = $membershipID
    ";
    $membership = CRM_Core_DAO::executeQuery($sql);
    if ($membership->fetch()) {
      // create the contribution
      $params = [
        'contact_id' => $membership->betaler_id ? $membership->betaler_id : $membership->contact_id,
        'financial_type_id' => 2,
        'receive_date' => "$year-01-01 12:00:00",
        'total_amount' => $amount,
        'contribution_status_id' => 1,
        'sequential' => 1,
      ];
      $contribution = civicrm_api3('Contribution', 'create', $params);

      // add soft credit, if needed
      if ($membership->betaler_id) {
        $params = [
          'contribution_id' => $contribution['id'],
          'contact_id' => $membership->contact_id,
          'amount' => $amount,
          'soft_credit_type_id' => 3,
        ];
        civicrm_api3('ContributionSoft', 'create', $params);
      }

      // create the link between the membership and the contribution
      $params = [
        'membership_id' => $membershipID,
        'contribution_id' => $contribution['id'],
      ];
      civicrm_api3('MembershipPayment', 'create', $params);
    }

    return TRUE;
  }

  public static function onEnd() {
    CRM_Core_Session::setStatus('De bijdragen zijn aangemaakt', 'Klaar', 'success');
  }

  public function getRenderableElementNames() {
    $elementNames = array();
    foreach ($this->_elements as $element) {
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
