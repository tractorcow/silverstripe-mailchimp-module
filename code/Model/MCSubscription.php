<?php 

class MCSubscription extends DataObject {
	
	private $_firstWrite;
	private $_writeCount;
    private $_syncMailChimp;
    private $_originalChangedFields;
    		
	public static $db = array(
	    'MCMemberID' => 'Int',
	    'MCEmailID' => 'Varchar(255)',
	    'FirstName' => 'Varchar(255)',
	    'Surname' => 'Varchar(255)',
		'Email' => 'Varchar(255)',
		'Subscribed' => 'Boolean',
		'UnsubscribeReason' => 'Varchar(255)',
		'DoubleOptIn' => 'Boolean'
	);
	
	public static $has_one = array(
	    'Member' => 'Member',
	    'MCList' => 'MCList'
	);
	
	public static $defaults = array(
	   'Subscribed' => 1,
	   'DoubleOptIn' => 0
	);
	
	public function getCMSFields() {
	    
	    $fields = parent::getCMSFields();
	    
	    // Validate The Fact That an Actual E-mail Has Been Used
	    $fields->replaceField("Email", new EmailField("Email", "Email"));
	    
        $fields->removeByName('DoubleOptIn');
        $fields->removeByName('MCMemberID');
        $fields->removeByName('MCListID');
	    $fields->removeByName('MemberID');
    	if(empty($this->ID)) {
	        $fields->removeByName('Subscribed');
    	}
	    if(empty($this->UnsubscribeReason)) {
	        $fields->removeByName('UnsubscribeReason');
	    }
	    
	    $lists = new DataList("MCList");
	    $lists->sort("\"MCList\".\"Name\" ASC");
	    $map = $lists->map("ID", "Name");
	    $mcListID = new DropdownField('MCListID', 'MailChimp List', $map);
	     
	    // Calculate What Parent Object We Are Adding This Subscription record Under (A Specific MCList or A Member) 
	    $params = Director::get_current_page()->getURLParams();
	    $FormParentObject = end($params); 
	    // If Already Set Don't Allow It To Be Modified
	    if(!empty($this->MCListID)) {
	         $mcListID = $mcListID->performReadonlyTransformation();
	    }
	    // If Already Set And Read Only Or If Unset But Adding Subscription Record Under Member Object Show The MCListID Filed
	    if(!empty($this->MCListID) || $FormParentObject == "Members") {
	        $fields->addFieldToTab('Root.Main', $mcListID); 
        }
        
	    $MCMemberID = new TextField('MCMemberID', 'MailChimp Member ID');
	    $MCMemberID = $MCMemberID->performReadonlyTransformation();
	    
	    $MCEmailID = new TextField('MCEmailID', 'MailChimp Email ID');
	    $MCEmailID = $MCEmailID->performReadonlyTransformation();
	    
	    $fields->addFieldToTab('Root.Main', $MCMemberID, 'FirstName');
	    $fields->addFieldToTab('Root.Main', $MCEmailID, 'FirstName');
	    
	    return $fields;
	    
	}
	
	public function IsSubscribed() {
        $status = $this->getField("Subscribed");
        return (!empty($status)) ? true : false;
    }
    
	public function getMCListName() {
	    return $this->getComponent("MCList")->getField("Name");
	}
	
	// Try and Use MailChimp Generated E-mail Based ID For All listUpdateMember() and listUnsubscribe() Calls
    // Fall Back On E-mail (Needed To Match Up MC Record When Email Updated On Site) Return False If Both Empty
	public function getMailChimpIdentifier() {
        if(!empty($this->MCEmailID)) {
            return $this->MCEmailID;
        } else {
            return (!empty($this->Email)) ? $this->Email : false;
        }
	}
	
	// Setters & Getters for Is First Write Logic (Defaults to true)
	public function getFirstWrite() {
	   if(isset($this->_firstWrite)) {
	       return $this->_firstWrite;
	   }
	   return true;
	}
	public function setFirstWrite($state = false) {
	    $this->_firstWrite = $state;
	}
	// Setters & Getters for Sync To MailChimp (Defaults to true)
    public function setSyncMailChimp($sync = false) {
        $this->_syncMailChimp = (empty($sync)) ? false : true;
    }
    public function getSyncMailChimp() {
        if(!isset($this->_syncMailChimp)) {
            $this->setSyncMailChimp(true);
        }
        return $this->_syncMailChimp;
    }
    // Setters & Getters for Write Count (Defaults to 1)
    public function getWriteCount() {
        if(isset($this->_writeCount)) {
            return $this->_writeCount;
        }
        return 1;
    }
    public function setWriteCount() {
        $this->_writeCount = $this->getWriteCount() + 1;
    }
    // Setters & Getters for Original Changed Fields (Fields Marked As Changed On The First Write Iteration)
    public function getOriginalChangedFields() {
        if(isset($this->_originalChangedFields)) {
            return $this->_originalChangedFields;
        } else {
            // Should Never Be Called Before Being Set But As A Fail Safe
            return $this->getChangedFields(false, 2);
        }
    }
    public function setOriginalChangedFields($arr) {
        if(!is_array($arr)) {
            SS_Log::log("setOriginalChangedFields() Requires An Array Parameter!", SS_Log::WARN);
        }
        $this->_originalChangedFields = $arr;
    }
    // Setters & Getters For Force Additional Write
    public function getForceAdditionalWrite() {
        if(isset($this->_forceAdditionalWrite)) {
            return $this->_forceAdditionalWrite;
        }
        return false;
    }
    public function setForceAdditionalWrite($state = false) {
        $this->_forceAdditionalWrite = $state;
    }
        
    public function write(){
        
        SS_Log::log("Write Iteration ".$this->getWriteCount(), SS_Log::NOTICE);

	    $cf = $this->getOriginalChangedFields(); 
	             
	    // Only Do 'Email Already Exists' Check If E-mail Has Changed
	    if(isset($cf["Email"])) {
	        // Check If The Updated E-mail Is In Use
	        $dl = new DataList("MCSubscription");
	        // DO Include Unsubscribed List Members As listSubscribe() Using An E-mail Address Still In The List (Even Unsubscribed From It) Errors
    	    $duplicate = $dl->where("\"MCListID\" = '".$this->MCListID."' && LOWER(\"Email\") = '".strtolower($this->Email)."' && \"ID\" != '".$this->ID."'")->first();
    	    if(!empty($duplicate)) {
    	        $this->setOriginalChangedFields($cf); // Store Original (First Write) Change Fields For Use On Second Write
    	        $vr = new ValidationResult(false, "Error: This E-mail Is Already In Use Within This List!");
    	        throw new ValidationException($vr);
    	    }
    	}
    	
    	parent::write();
    	
    	// Do E-mail Comparison Set AFTER 1st Write 
    	// Otherwise We Can Have All Subscriber Data Fields (inc Forign Key Fields) CAN be Writeen on the 1st Write,
    	// Meaning the MailChimp Sync Logic Never Fires
    	if(isset($cf["Email"])) {
    	    // Check For Related Member E-mails and Link to Member If Found
            $dl = new DataList("Member");
            $relatedMember = $dl->where("LOWER(\"Email\") = '".strtolower($this->Email)."'")->first();
            if(!empty($relatedMember->ID)) {
                $this->setField("MemberID", $relatedMember->ID);
            }
	    }
	    
	}
	    
    public function onAfterWrite() {
        
        parent::onAfterWrite();
        
        // Define Related MCList Object 
        $list = $this->getComponent("MCList");

        // Store The True Changed Fields Array On First Write
        if($this->getWriteCount() == 1) {
            $cf = $this->getChangedFields(false, 2); // Define Change Fields Array
	        $this->setOriginalChangedFields($cf); // Store Original (First Write) Change Fields For Use On Second Write
	    } else if(
    	    $this->getWriteCount() > 1 // Only On The Second Or Greater Write of The DataObject (When Related Member Object Will Be Set If One Exists)
    	    && $this->getSyncMailChimp() // Only If We Are Actually Syncing to MailChimp
    	    && !empty($this->Email) // We Must Have a Unique MailChimp Member Identifier
    	    && !empty($list->ID) // We Must Have a Unique MailChimp List Identifier
	    ) {
	        
	        $apikey = SiteConfig::current_site_config()->getMCAPIKey();
            $api = new MCAPI($apikey);
            
            // Define The Change Fields Array Which We Stored On The First Write Iteration For Use On The Second Write (When Components Are Written)
            if($this->getWriteCount() == 2) {
                $cf = $this->getOriginalChangedFields();
            }
            
            $Class = array();
            $Class["MCSubscription"] = $this;

            $where = "\"MCListID\" = '".$this->MCListID."' AND \"SyncDirection\" IN ('Export','Both')";
            
            if(!empty($this->getComponent("Member")->ID)) {
                $Class['Member'] = $this->getComponent("Member");
                SS_Log::log("Sub ID ".$this->ID." Has A Related Member Object..", SS_Log::NOTICE);
            } else {
                // If No Related Member Object Only Deal With Subscription Record Merge Data
                $where .= " AND \"OnClass\" = 'MCSubscription'";
                SS_Log::log("Sub ID ".$this->ID." Has No Related Member Object..", SS_Log::NOTICE);
            }

            $dl = new DataList("MCListField");
            $mappings = $dl->where($where);

            $merge_vars = array();
            foreach($mappings as $map) {
                $merge_vars[$map->MergeTag] = $Class[$map->OnClass]->getField($map->FieldName);
            }
            
            if(isset($cf['ID'])) { // If Adding a New Subscription
                
                $result = $api->listSubscribe($list->ListID, $this->Email, $merge_vars, 'html', $this->DoubleOptIn);
                // If Successfully Added a New Subscription Make a Second Call to Return the MailChimp Member (Web) && Email ID's
                if(empty($api->errorCode)) {
                    SS_Log::log("API Call Success: listSubscribe(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID, SS_Log::NOTICE);
                    $retval = $api->listMemberInfo($list->ListID, $this->Email);
                    if(empty($api->errorCode)){
                        SS_Log::log("API Call Success: listMemberInfo(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID, SS_Log::NOTICE);
                        SS_Log::log("Calling Additional write() for Subscription ID " . $this->ID." to Save MailChimp Created Data", SS_Log::NOTICE);
                        $this->setField("MCMemberID", $retval['data'][0]['web_id']); // Set The MailChimp Member (Web) ID for this Member (Which Is Static - Used For MC - Site Imports)
                        $this->setField("MCEmailID", $retval['data'][0]['id']); // Set The MailChimp Email ID for this Member (Which Updates When E-mail Updates - Used For Site - MC Exports)
                        $this->setWriteCount();
                        $this->write();
                    } else {
                        SS_Log::log("API Call Failed: listMemberInfo(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID. " | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                    }
                } else {
                    SS_Log::log("API Call Failed: listSubscribe(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID. " | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                }
                
            } else if(isset($cf['Subscribed']) && !empty($this->Subscribed)) { // If Just Re-Subscribed (This Will Replace Previous MC Record With New One Rather Than Re-Subscribing Existing)
     
                $result = $api->listSubscribe($list->ListID, $this->Email, $merge_vars, 'html', $this->DoubleOptIn); // Must use E-mail For Re-Subscription as listSubscribe() assumes a new user (it actually deletes the existing 'un-subscribed' MailChimp record for the provided e-mail and re-adds the user)
                if(empty($api->errorCode)){
                    SS_Log::log("API Call Success: listSubscribe(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID, SS_Log::NOTICE);
                } else {
                    SS_Log::log("API Call Failed: listSubscribe(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID. " | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                }
                
            } else if(isset($cf['Subscribed']) && empty($this->Subscribed)) { // If Just Unsubscribed
                
                $result = $api->listUnsubscribe($list->ListID, $this->getMailChimpIdentifier());
                if(empty($api->errorCode)){
                    SS_Log::log("API Call Success: listUnsubscribe(".$list->ListID.", ".$this->getMailChimpIdentifier()."); for Subscription ID " . $this->ID, SS_Log::NOTICE);
                } else {
                    SS_Log::log("API Call Failed: listUnsubscribe(".$list->ListID.", ".$this->getMailChimpIdentifier()."); for Subscription ID " . $this->ID. " | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                }
                
            } else if(!empty($this->Subscribed)) { // If Updating an Existing Subscription (That Hasnt Already Unsubscribed)
                
                $result = $api->listUpdateMember($list->ListID, $this->getMailChimpIdentifier(), $merge_vars);
                // If Successfully Updated a Subscription Make a Second Call to Return the MailChimp Member Email ID
                if(empty($api->errorCode)) {
                    SS_Log::log("API Call Success: listUpdateMember(".$list->ListID.", ".$this->getMailChimpIdentifier()."); for Subscription ID " . $this->ID, SS_Log::NOTICE);
                    if(isset($cf['Email'])) {
                       $retval = $api->listMemberInfo($list->ListID, $this->Email); // Call Must Use Email As MCEmailID Will Be Outdated If Last Update Was An Email Change
                        if(empty($api->errorCode)){
                            SS_Log::log("API Call Success: listMemberInfo(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID, SS_Log::NOTICE);
                            SS_Log::log("Calling Additional write() for Subscription ID " . $this->ID." to Save Updated MailChimp Email ID", SS_Log::NOTICE);
                            $this->setField("MCEmailID", $retval['data'][0]['id']); // Update The MailChimp Email ID for this Member (Which Updates When E-mail Updates)
                            $this->setSyncMailChimp(false);
                            $this->write();
                        } else {
                            SS_Log::log("API Call Failed: listMemberInfo(".$list->ListID.", ".$this->Email."); for Subscription ID " . $this->ID. " | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                        }    
                    }
                } else {
                    SS_Log::log("API Call Failed: listUpdateMember(".$list->ListID.", ".$this->getMailChimpIdentifier()."); for Subscription ID " . $this->ID. " | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                }    
                
            } else {
                
                SS_Log::log("No API Call Made: Record Must Be Marked As Unsubscribed.", SS_Log::NOTICE);
            
            }
                        
        } else {
            SS_Log::log(
                "In >=2 Write But No MailChimp Sync Triggered? " .
                "Sub ID = '" . $this->ID . "' | " .
                "Write Count = '" . $this->getWriteCount() . "' | " .
        	    "Sync To MailChimp = '" . $this->getSyncMailChimp() . "' | " . 
        	    "Subscriber E-mail = '" . $this->Email . "' | " .
        	    "Related MC List ID = '" . $list->ID . "'",
                SS_Log::WARN
    	    );
        }
        
        $this->setWriteCount();
        
        // If We Have Forced An Additional Write (Triggered When Saving Subscription Object Via Related Member Data Being Updated)
        if($this->getForceAdditionalWrite()) {
            // Unset It First So We Don't Keep Forcing Additional Writes Causing An Infinite Loop
            $this->setForceAdditionalWrite(false);
            // Write The Object Once More (For Benefit Of Sync Logic On Second Write)
            $this->write();
        }
        
    }
    
    public function onAfterDelete() {
        
        // If Deletion Is Triggered By A Sync Where The Record Has Already Been Deleted In MC The Sync Flag Will Be False
        if($this->getSyncMailChimp()) {
            
            $apikey = SiteConfig::current_site_config()->getMCAPIKey();
            $api = new MCAPI($apikey);
    
            // Define Related MCList Object 
            $list = $this->getComponent("MCList");
            // Execute the Unsubscribe Call
            $result = $api->listUnsubscribe($list->ListID, $this->getMailChimpIdentifier(), true);
    
            if($result) {
                SS_Log::log("API Call Success: listUnsubscribe(".$list->ListID.", ".$this->getMailChimpIdentifier().", \$delete_member = true); for Subscription ID " . $this->ID, SS_Log::NOTICE);
            } else {
                SS_Log::log("API Call Failed: listUnsubscribe(".$list->ListID.", ".$this->getMailChimpIdentifier().", \$delete_member = true); for Subscription ID " . $this->ID . " | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
            }
             
        }


    }
	
}

?>