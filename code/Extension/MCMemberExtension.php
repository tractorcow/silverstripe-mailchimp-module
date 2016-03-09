<?php

/**
 * This work is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc/4.0/.
 */
class MCMemberExtension extends DataExtension {

    private $_firstWrite;
    private $_syncMailChimp;

    // Setters & Getters for SynvMail Chimp (Defaults to true)
    public function setSyncMailChimp($sync = false) {
        $this->_syncMailChimp = (empty($sync)) ? false : true;
    }
    public function getSyncMailChimp() {
        if(!isset($this->_syncMailChimp)) {
            $this->setSyncMailChimp(true);
        }
        return $this->_syncMailChimp;
    }

    public static $has_many = array(
        'MCSubscriptions' => 'MCSubscription'
    );

    private static $many_many = array(
        'Events'    => 'Event'
    );

    public function updateCMSFields(FieldList $fields) {

        $fields->removeByName('MCSubscriptions');

        /* START SUBSCRIBERS GRIDFIELD */
        $config = GridFieldConfig_RelationEditor::create();
        $config->getComponentByType('GridFieldAddExistingAutocompleter')->setSearchFields(array("Email", "FirstName", "Surname"));
        $config->getComponentByType('GridFieldAddExistingAutocompleter')->setResultsFormat('$Email ($FirstName $Surname)');
        $config->getComponentByType('GridFieldAddNewButton')->setButtonName("Add Subscription");
        $config->getComponentByType('GridFieldDataColumns')->setDisplayFields(array(
		      'MCListName' => 'MailChimp List Name',
		      'FirstName' => 'First Name',
		      'Surname' => 'Surname',
		      'Email' => 'E-mail',
		      'Subscribed' => 'Active Subscription',
		      'Created' => 'Created',
  		      'LastEdited' => 'LastEdited'
		    ));
		    $l = new GridField(
		      'MCSubscriptions',
		      'MailChimp Subscriptions',
		      $this->owner->getComponents("MCSubscriptions", "", "\"MCListID\" ASC, \"Surname\" ASC, \"FirstName\" ASC, \"Email\" ASC"),
		      $config
		    );
        $fields->addFieldToTab('Root.SubscriberRecords', $l);
        /* FINISH MCLISTS GRIDFIELD */

    }

    public function setSubscriptionData($subID, $data) {
        $sub = $this->owner->getComponents("MCSubscriptions", "\"ID\" = '".$subID."'")->first();
        foreach($data as $key => $val) {
            $sub->setField($key, $val);
        }
        $sub->setForceAdditionalWrite(true);
        $sub->write();
    }

    //OnBeforeWrite Flag Getter/Setter Functions
	public function getFirstWrite() {
	   if(isset($this->_firstWrite)) {
	       return $this->_firstWrite;
	   }
	   return true;
	}
	public function setFirstWrite($state = false) {
	    $this->_firstWrite = $state;
	}

	public function onBeforeWrite() {

	    parent::onBeforeWrite();

	}

    public function onAfterWrite() {

        parent::onAfterWrite();

        // This is to ensure this only fires once on each write
	    if($this->getFirstWrite()) {

    	    // Get Array of updated fields
    	    $UpdatedDataFields = $this->owner->getChangedFields(true, 2);

    	    // Get HasManyList of this Members MCSubscriptions
    	    $subs = $this->owner->getComponents("MCSubscriptions");

    	    // If the Member Has One or More Subscriptions
    	    if(is_object($subs) && $subs->exists()) {

    	        // Foreach of This Members Subscription Objects
    	        foreach($subs as $sub) {

                    SS_Log::log("Subscription ID = ".$sub->ID, SS_Log::NOTICE);

                    // Get DataList of MC List Field Mappings (Excluding LastVisited) Which Are On The Member Class && In A MCList Which Concerns This Member (i.e. One They Are Subscribed To)
            	    // (as if LastVisited is the ONLY updated field it just represents a site login, not an actual manual MC data field update)
            	    $dl = new DataList("MCListField");
            	    $mappings = $dl->where("\"OnClass\" = 'Member' AND \"MCListID\" = '".$sub->MCListID."' AND \"SyncDirection\" IN ('Export','Both') AND \"FieldName\" != 'LastVisited'");

                    // Foreach Mapping Record
            	    foreach($mappings as $mapping) {

                        SS_Log::log("Mapping Field Name = ".$mapping->FieldName, SS_Log::NOTICE);

            	        // If The Member FieldName is One of the Updated Fields
            	        if(isset($UpdatedDataFields[$mapping->FieldName])) {
            	            // Mark the Subscription as Being Updated
            	            SS_Log::log("\$UpdatedDataFields['".$mapping->FieldName."'] Is Set, doing \$sub->write();", SS_Log::NOTICE);
            	            $sub->setSyncMailChimp($this->getSyncMailChimp()); // Set MCSubscriber Sync to MailChimp Based on This (Member) Sync State
            	            $sub->setForceAdditionalWrite(true);
            	            $sub->write();
            	            break;
            	        }

            	    }

    	        }

    	    } else if(isset($UpdatedDataFields["ID"])) { // Creation Write

                // Add New or Link Existing Subscription Records
                $subs = MCSubscription::get()->where("\"Email\" = '" . $this->owner->Email . "'");
                if(is_object($subs) && $subs->exists()) {

                    // Link Each Subscriber Object to this Member
                    foreach($subs as $sub) {
                        $sub->setForceAdditionalWrite(true);
                        $this->owner->getComponents("MCSubscriptions")->add($sub);
                    }

                } else {

                    // Get the Default MailChimp List To Relate The New Subscription To
                    $MCList = MCList::get()->sort("\"SortOrder\" ASC")->first();
                    if(is_object($MCList) && $MCList->exists()) {
                        // Create New Subscriber Object and Link to New Member
                        $sub = new MCSubscription();
                        $sub->setField("FirstName", $this->owner->FirstName);
                        $sub->setField("Surname", $this->owner->Surname);
                        $sub->setField("Email", $this->owner->Email);
                        $sub->setField("MemberID", $this->owner->ID);
                        $sub->setField("MCListID", $MCList->ID);
                        $sub->write();
                        // Link the New Subscriber Object to this Member
                        $this->owner->getComponents("MCSubscriptions")->add($sub);
                    } else {
                        SS_Log::log("No MCList object available to add new MCSubscriptions to when saving Member #" . $this->owner->ID, SS_Log::NOTICE);
                    }

                }

            }

	    }

    }

}