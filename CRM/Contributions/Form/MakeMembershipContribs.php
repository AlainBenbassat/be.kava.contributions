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

    $this->setTitle('Aanmaken van lidmaatschapsbijdragen');

    // add list of membership types
    $this->add('select','membership_type','1. Soort lidmaatschap',[0 => '--- KIES EEN SOORT LIDMAATSCHAP ---'] + CRM_Member_PseudoConstant::membershipType(),TRUE);

    // add text field for year
    $this->add('text', 'year', '2. Jaar');
    $defaults['year'] = date('Y');

    // add start date month
    $this->add('text', 'start_month_from', '3. Maand startdatum van', ['size' => 3]);
    $defaults['start_month_from'] = '1';
    $this->add('text', 'start_month_end', 'tot en met', ['size' => 3]);
    $defaults['start_month_end'] = '3';

    // add text field for amount
    $this->add('text', 'amount', '4. Bedrag van de bijdrage');
    $defaults['amount'] = 1;

    // add text field for description
    $this->add('text', 'description', '5. Omschrijving', ['size' => 80]);
    $defaults['description'] = 'Abonnement';

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

    // validate the membership type
    if ($fields['membership_type'] == 0) {
      $errors['membership_type'] = 'Kies een soort lidmaatschap';
    }

    // validate the submitted year
    if (CRM_Utils_Type::validate($fields['year'], 'Integer', FALSE) && $fields['year'] >= 1900 && $fields['year'] <= 3000) {
      //OK, valid year
    }
    else {
      $errors['year'] = 'Dit moet een geldig jaartal zijn (tussen 1900 en 3000)';
    }

    // validate the month from/to
    if (CRM_Utils_Type::validate($fields['start_month_from'], 'Integer', FALSE)
      && CRM_Utils_Type::validate($fields['start_month_end'], 'Integer', FALSE)
      && $fields['start_month_from'] >= 1
      && $fields['start_month_from'] <= 12
      && $fields['start_month_end'] >= 1
      && $fields['start_month_end'] <= 12
      && $fields['start_month_end'] >= $fields['start_month_from']) {
      //OK, valid year
    }
    else {
      $errors['start_month_from'] = '"Van" en "tot" moeten een getal zijn tussen 1 en 12, en "tot" moet groter of gelijk zijn aan "van"';
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
      CRM_Core_Session::setStatus('Geen lidmaatschappen gevonden.', 'Aanmaken lidmaatschapsbijdragen', 'warning');
    }

    parent::postProcess();
  }

  private function fillQueue($values) {
    // select all valid memberships of the given type in the given period
    // without contribution
    // By valid:
    //   - start date must be this year or earlier
    //   - end date must be >= 31/12 of this year
    //     if the end date is another date this year it is assumed the membership was charged the
    //     previous year, and was cancelled. For end date = 31/12, membership will be charged
    $sql = "
      select
        m.id
      from
        civicrm_membership m
      join
        civicrm_value_facturatie_79 f on m.id=f.entity_id
      where 
        m.membership_type_id = %1
      and
        year(m.start_date) <= %2
      and
	      m.end_date >= %3
	    and
	      month(m.start_date) between %4 and %5
	    and 
	      m.owner_membership_id is null
            and
              f.gratis__260=0
	    and 
        not exists (
          select 
            *
          from
            civicrm_membership_payment mp  
          inner join
            civicrm_contribution c on mp.contribution_id = c.id and year(c.receive_date) = %2
          where 
            mp.membership_id = m.id
        )
    ";
    $sqlParams = [
      1 => [$values['membership_type'], 'Integer'],
      2 => [$values['year'], 'Integer'],
      3 => [$values['year'] . '-12-31', 'String'],
      4 => [$values['start_month_from'], 'Integer'],
      5 => [$values['start_month_end'], 'Integer'],
    ];
    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);

    while ($dao->fetch()) {
      // add them to the queue
      $task = new CRM_Queue_Task(
        ['CRM_Contributions_Form_MakeMembershipContribs', 'createMembershipContribution'],
        [$dao->id, $values['year'], $values['amount'], $values['description']]
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

  public static function createMembershipContribution(CRM_Queue_TaskContext $ctx, $membershipID, $year, $amount, $description) {
    // get the membership
    $sql = "
      select 
        m.*
        , f.betaler_257 betaler_id
        , f.product_261 product_code
        , ifnull(a.hoeveelheid_138, 1) hoeveelheid
      from
        civicrm_membership m
      left outer join  
        civicrm_value_facturatie_79 f on f.entity_id = m.id
      left outer join
        civicrm_value_aft_abonnement_26 a on a.entity_id = m.id
      where 
        m.id = $membershipID
    ";
    $membership = CRM_Core_DAO::executeQuery($sql);
    if ($membership->fetch()) {
      // create the contribution
      $params = [
        'contact_id' => $membership->betaler_id ? $membership->betaler_id : $membership->contact_id,
        'financial_type_id' => 2,
        'total_amount' => $amount,
        'contribution_status_id' => 2,
        'contribution_source' => $description,
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

      // update the contribution line item: store the product code
      if ($membership->product_code) {
        $sqlLineItem = "
          update
            civicrm_line_item
          set 
            label = %2
            , qty = %3
          where
            contribution_id = %1
        ";
        $sqlLineItemParams = [
          1 => [$contribution['id'], 'Integer'],
          2 => [$membership->product_code, 'String'],
          3 => [$membership->hoeveelheid, 'Integer'],
        ];
        CRM_Core_DAO::executeQuery($sqlLineItem, $sqlLineItemParams);
      }
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
