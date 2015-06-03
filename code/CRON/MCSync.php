<?php

class MCSync_Checkpoint extends DataObject {
    
    public static $db = array(
        "LastSuccessfulSync" => "SS_Datetime", // Stored in GMT Based on MailChimps API Server Time at Sync Start 
        "MCListID" => "Varchar(255)"
    );
    
}

class MCSync extends Controller {
    
    public static $allowed_actions = array(
        'CleanUpSubscriptionStatus',
        'UpdateLists',
        'UpdateMemberData',
        'UpdateMemberStatus',
        'UpdateSegments'
    );
    
    public $apikey;
    
    // Redirect Any Non-Authorised Access And Set API Key Field
    public function init() {
        parent::init();
        // Define API Key
        $this->apikey = SiteConfig::current_site_config()->getMCAPIKey();
        // Quit here if no API key available
        if(empty($this->apikey)) {
            SS_Log::log("No MailChimp API Key Provided, Aborting Syncronisation!", SS_LOG::NOTICE);
            exit();
        }
    }
    
    // Run Entire Syncronisation Process
    public function index() {
        
        // Make Sure Lists Are In Sync With MailChimp Before Processing
        $this->UpdateLists();
        // Get DataList of All Lists                        
        $lists = $this->getLists();
        // Process Each List
        foreach($lists as $list) {
            // Store the Sync Start Based on MailChimp API Time
            // (MailChimp API Calls Are Based On GMT But This Also Prevents Server Time Drift Issues)
            $syncStart = $this->getMailChimpTimestamp();
            // Create or Find Latest Sync Checkpoint
            $checkpoint = $this->getLatestSyncCheckpoint($list);
            // Populate List Field Mapping Array For This List
            $listFields = $this->getFieldListMappings($list);
            // Update All Member Data Modified On MailChimp Since The Last Checkpoint For This List
            $updatedData = $this->UpdateMemberData($list, $checkpoint, $listFields);
            // Update Any Member Unsubscribes On MailChimp Since The Last Checkpoint For This List
            $updatedStatus = $this->UpdateMemberStatus($list, $checkpoint);
            // Clean Up Static Segments Deleted On MailChimp For This List
            $this->UpdateSegments($list);
            // Clean Up Subscription Records 'Subscribed' State (For MC Deleted Records and Records Awaiting Confirmation)
            $this->CleanUpSubscriptionStatus($list);
            // Update Last Successful Sync Field For This MCList
            if($updatedData && $updatedStatus) {
                $this->setLatestSyncCheckpoint($checkpoint, $syncStart);
            }
        }       

    }
    
    // Get DataList of All MailChimp Lists
    public function getLists() {
        $lists = new DataList("MCList");
        if($lists->count() == 0) {
            error_log($this->error_log_prefix()."No MailChimp Lists To Run Updates Against! Exiting...");
            return array();
        } else {
            return $lists;
        }
    }
    
    // Get DataList of Field List Mappings For This List
    public function getFieldListMappings($list = null) {
        
        $listFields = array();
        
        if(!is_object($list)) {
            error_log($this->error_log_prefix()."getFieldListMappings() Requireds a MCList Object Parameter!");
            return $listFields;
        }

        $dl = new DataList("MCListField");
        $objs = $dl->where("\"MCListID\" = '".$list->ID."' AND \"SyncDirection\" IN ('Import','Both')")->sort("\"OnClass\" ASC");
        
        foreach($objs as $obj) {
            $listFields[$obj->MergeTag] = array("OnClass" => $obj->OnClass, "FieldName" => $obj->FieldName);
        }
        // If No List Field Mapping No Data Will Be Stored
        if(empty($listFields)) {
            error_log($this->error_log_prefix()."No Import List Field Mappings For ".$list->Name." List! Exiting...");
        }
        
        return $listFields;
        
    }
    
    // Find or Create a Checkpoint to Get Updates Since For This List
    public function getLatestSyncCheckpoint($list = null) {
        
        if(!is_object($list)) {
            error_log($this->error_log_prefix()."getLatestSyncCheckpoint() Requireds a MCList Object Parameter!");
            return false;
        }
         
        $dl = new DataList("MCSync_Checkpoint");
        $checkpoint = $dl->where("\"MCListID\" = '".$list->ListID."'")->sort("\"LastSuccessfulSync\" DESC")->first();
        if(empty($checkpoint)) {
            $checkpoint = new MCSync_Checkpoint();
            $checkpoint->setField("MCListID", $list->ListID);
            $checkpoint->setField("LastSuccessfulSync", null);
            $checkpoint->write();
        }
        return $checkpoint;
    }
    
    // Update Our Latest Checkpoint For This List
    public function setLatestSyncCheckpoint($checkpoint = null, $syncStart = 0) {
        
        if(!is_object($checkpoint) || empty($syncStart)) {
            error_log($this->error_log_prefix()."setLatestSyncCheckpoint() Requireds A Checkpoint Object and Sync Start DateTime Parameters!");
            return false;
        }
        
        $checkpoint->setField("LastSuccessfulSync", $syncStart);
        $checkpoint->write();
        error_log($this->error_log_prefix()."Succesfully Updated List ID ".$checkpoint->MCListID." @ ".$syncStart);
        
        return true;
        
    }
    
    // Returns the Current DateTime According to the MailChimp API Server
    public function getMailChimpTimestamp() {
        $api = new MCAPI($this->apikey);
        $retval = $api->ping();
        if ($api->errorCode){
        	error_log($this->error_log_prefix()."Unable to load lists()! Error Code = ".$api->errorCode." Error Msg = ".$api->errorMessage);
        	return false;
        } else {
            return gmdate('Y-m-d H:i:s', strtotime($retval['headers']['Date']));
        }
    }
    
    // Do MCList Syncronisation
    public function UpdateLists() {
                
        $api = new MCAPI($this->apikey);
        
        $retval = $api->lists();
        if ($api->errorCode){
        	error_log($this->error_log_prefix()."Unable to load lists()! Error Code = ".$api->errorCode." Error Msg = ".$api->errorMessage);
        	return false;
        } else {
            foreach ($retval['data'] as $list){
        	    // Get DataList of All MCLists
        	    $dl = MCList::get();
        	    $l = $dl->where("ListID = '".$list['id']."'")->first();
        	    // If the Current Iterations List Object Does Not Yet Exist, Create It
        	    if(!is_object($l)) {
        	        $l = new MCList();
        	        $l->setField("ListID", $list['id']);
        	    }
        	    // Populate/Overwrite the List Data
        		$l->setField("Name", $list['name']);
        		$l->setField("WebID", $list['web_id']);
        		$l->setField("Subscribed", $list['stats']['member_count']);
        		$l->setField("Unsubscribed", $list['stats']['unsubscribe_count']);
        		$l->setField("Cleaned", $list['stats']['cleaned_count']);
        		$l->write();
        		
        		// Add/Delete any New/Removed Merge Tags
        		// (Newly Added Merge Tags Will Need Linking/Relating to the Appropriate DB Field Name
        		// via Admin -> Setting -> MC Lists -> List Field Relationships)
        		$retval = $api->listMergeVars($l->ListID);
                if ($api->errorCode){
                	error_log($this->error_log_prefix()."Unable to load listMergeVars()! Code = ".$api->errorCode." Msg = ".$api->errorMessage);
                	return false;
                } else {
                    $currTags = array();
                    foreach($retval as $mergeTagData) {
                        $currTags[] = $mergeTag = $mergeTagData['tag'];
                        $listField = $l->getComponents("MCListFields", "\"MergeTag\" = '".$mergeTag."'")->first();
                        if(empty($listField)) {
                            $lf = new MCListField();
                            $lf->setField("MergeTag", $mergeTag);
                            $lf->write();
                            $l->getComponents("MCListFields")->add($lf);
                        }
                    }
                    // Create DataList of All Existing MC List Fields Which Are No Longer Present In MailChimp (Old Merge Tags) and Delete Them
                    $dl = new DataList("MCListField");
                    $dl->removeByFilter("\"MCListID\" = '".$l->ListID."' AND \"MergeTag\" NOT IN (".$this->arrayToCSV($currTags).")");
                }
        	}	
        }
        return true;
    }
    
    // Do MCList Member Data (Excludes Subscription Status) Updates
    public function UpdateMemberData($list = null, $checkpoint = null, $listFields = array()) {
    
        if(!is_object($list) || !is_object($checkpoint)) {
            error_log($this->error_log_prefix()."UpdateMemberData() Requireds MCList Object and Sync Checkpoint Parameters!");
            return false;
        }
        
        $api = new MCAPI($this->apikey);
        
        $retval = $api->listMembers($list->ListID, 'updated', $checkpoint->LastSuccessfulSync, 0, 5000);
        if ($api->errorCode){
            error_log($this->error_log_prefix()."API Call Failed: listMembers('".$list->ListID."', 'updated', '".$checkpoint->LastSuccessfulSync."', 0, 5000); Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage);
            return false;
        } else {
            error_log($this->error_log_prefix()."API Call Success: listMembers('".$list->ListID."', 'updated', '".$checkpoint->LastSuccessfulSync."', 0, 5000); Returned Members = ".$retval['total']);

        	if($retval['total'] > 0) {
        	    
            	foreach($retval['data'] as $member){
                    
                    $where = "\"MCListID\" = '".$list->ID."' AND ";
                    // Try and Lookup Member Data By E-mail To Get MailChimp ID for More Accurate Updates 
                    // (i.e. E-mail Updated on MailChimp Would Otherwise Create New Sub Rather Than Update Existing)
                    $mcMember = $api->listMemberInfo($list->ListID, $member['email']);
                    if ($api->errorCode){ // (Should Always Return Data As Members Yet To Confirm Subscribption Shouldn't Be Returned in listMembers() But Just Incase Fall Back On E-mail)
                    	error_log($this->error_log_prefix()." - API Call Failed: listMemberInfo('".$list->ListID."', '".$member['email']."'); Error Code = " . $api->errorCode . " | Error Message = " . $api->errorMessage);
                        $where .= "LOWER(\"Email\") = '".strtolower($member['email'])."'";
                    } else {
                        error_log($this->error_log_prefix()." - API Call Success: listMemberInfo('".$list->ListID."', '".$member['email']."');");
                        $where .= "\"MCMemberID\" = '".$mcMember['data'][0]['web_id']."'";  
                    }
                    
                    error_log($this->error_log_prefix()." - MEMBER ".$member['email']." (MCID: ".$mcMember['data'][0]['web_id'].")");
                    error_log($this->error_log_prefix()." - MailChimp Updated: ".$member['timestamp']);
                    
            	    $dl = new DataList("MCSubscription");
            	    $sub = $dl->where($where)->first();
            	    
            	    if(empty($sub)) {
            	        // Newly Added Subscription
            	        $sub = new MCSubscription();
            	        $sub->setField("MCListID", $list->ID);
            	        // See if There is a Related Member Email
            	        $dl = new DataList("Member");
            	        $relatedMember = $dl->where("LOWER(\"Email\") = '".strtolower($member['email'])."'")->first();
            	        if(!empty($relatedMember->ID)) {
            	            $sub->setField("MemberID", $relatedMember->ID);
            	        }
            	    } else {
            	        $relatedMember = $sub->getComponent("Member");
            	    }
            	    // We Will Need To Set Subscribed To True For Newly Added Members (Including Members Who Took A While To Confirm (DoubleOpt In) And Got Marked As Unsubscribed)
            	    // Setting Subscribed True Even If Its Already True Wont Do Any Harm Anyway
        	        $sub->setField("Subscribed", 1);
            	    
            	    error_log($this->error_log_prefix()." - Site MC Data Updated: ".gmdate('Y-m-d H:i:s', strtotime($sub->LastEdited)));
            	    
            	    /*
            	    // The Below If Should Never Return True If Exporting Data From The Website - Mail Chimp On Write(), Unless The API Call Were To Fail On Export 
            	    // $sub->LastEdited Also Takes Into Account The Last Time MCList Fields On Member Were Updated (See MCMemberExtension onBeforeWrite())
            	    // Take $sub->LastEdited As A Marker For Last Time ANY Data Relating To This MCList Was Manually Updated 
            	    if(strtotime($sub->LastEdited) > strtotime($member['timestamp'])) {
            	        // MailChimp Data Has Been SuperSeded By More Recent Website Managed MailChimp Data Update (Skip Update)
            	        error_log($this->error_log_prefix()." - Site MC Data Updated: ".$sub->LastEdited);
            	        error_log($this->error_log_prefix()." - Data Superseded By Site MC Data");
            	        continue;
            	    }
            	    */
            	    
            	    // Push MCSubscription and (If Exists) Member Objects In To $class Array
            	    $Class['MCSubscription'] = $sub;
            	    error_log($this->error_log_prefix()." - Subscriber ID = ".$sub->ID);
            	    
            	    if(!empty($relatedMember->ID)) { // getComponent() Returns an Empty Component if None Are Found (So Check For Existance of ID)
            	        $Class['Member'] = $relatedMember;
            	        error_log($this->error_log_prefix()." - Related Member ID = ".$Class['Member']->ID);
            	    } else {
            	        error_log($this->error_log_prefix()." - No Related Member");
            	    }
            	    
            	    // $mcMember Array Created Above When Doing listMemberInfo() Call
                    if(isset($mcMember['data']) && !empty($mcMember['data'])) {
                        
                        // Set The MailChimp Member (Web) && Email ID's for this Member
                        $Class["MCSubscription"]->setField("MCMemberID", $mcMember['data'][0]['web_id']);
                        $Class["MCSubscription"]->setField("MCEmailID", $mcMember['data'][0]['id']);
                        
                        foreach($mcMember['data'][0]['merges'] as $mergeTag => $Value) {
                            // If Current Merge Tag is in List Field Mapping (i.e. is to be synced)
                            if(isset($listFields[$mergeTag])) {
                                $ClassName = $listFields[$mergeTag]['OnClass'];
                                $FieldName = $listFields[$mergeTag]['FieldName'];
                                // If We Are Updating a 'Subscription' Object Which Has No Related Member $Class['Member'] Will Not Contain a Member Object (So Just Dump The Data)
                                if(isset($Class[$ClassName]) && !empty($FieldName) && !empty($Value)){
                                    error_log($this->error_log_prefix()." -- \$Class['".$ClassName."']->setField('".$FieldName."', '".$Value."');");
                                    $Class[$ClassName]->setField($FieldName, $Value);
                                }
                            }
                        }
                        
                        // write() The Updated Object(s)
                        $Class["MCSubscription"]->setSyncMailChimp(false);
                        $Class["MCSubscription"]->write();
                        if(!empty($Class["Member"])){
                            $Class["Member"]->setSyncMailChimp(false);
                            $Class["Member"]->write();
                        }
                        
                    } // END listMemberInfo() -> if($api->errorCode) {} else { 
            	    
            	} // END foreach($retval['data] as $member) { 
            	
        	} // END if($retval['total] > 0) {
        	    
        } // END listMembers() -> if($api->errorCode) {} else {
        
        // Assume Success If We Havn't Already Returned False By This Point
        return true; 
        
    }
    
    // Do MCList Member Subscription Status Updates
    public function UpdateMemberStatus($list = null, $checkpoint = null) {
        
        if(!is_object($list) || !is_object($checkpoint)) {
            error_log($this->error_log_prefix()."UpdateMemberStatus() Requireds MCList Object and Sync Checkpoint Parameters!");
            return false;
        }
        
        $api = new MCAPI($this->apikey);
        
        $retval = $api->listMembers($list->ListID, 'unsubscribed', $checkpoint->LastSuccessfulSync, 0, 5000);
        if ($api->errorCode){
            error_log($this->error_log_prefix()."API Call Failed: listMembers('".$list->ListID."', 'unsubscribed', '".$checkpoint->LastSuccessfulSync."', 0, 5000); Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage);
            return false;
        } else {
            error_log($this->error_log_prefix()."API Call Success: listMembers('".$list->ListID."', 'unsubscribed', '".$checkpoint->LastSuccessfulSync."', 0, 5000); Returned Members = ".$retval['total']);
        	
        	if($retval['total'] > 0) {   
            	
            	foreach($retval['data'] as $member){
            	    
            	    $where = "\"MCListID\" = '".$list->ID."' AND ";
                    // Try and Lookup Member Date By E-mail To Get MailChimp ID for More Accurate Updates 
                    // (i.e. E-mail Updated on MailChimp Would Otherwise Create New Sub Rather Than Update Existing)
                    $memberInfo = $api->listMemberInfo($list->ListID, $member['email']);
                    // Members Yet To Confirm Subscribption Shouldn't Be Returned in listMembers()
                    // listMemberInfo() Error Would Suggest The Record Has Been DELETED from MailChimp (Rather Than Just Unsubscribed)
                    if ($api->errorCode){
                    	error_log($this->error_log_prefix()." - API Call Failed: listMemberInfo('".$list->ListID."', '".$member['email']."'); Error Code = " . $api->errorCode . " | Error Message = " . $api->errorMessage);
                        $where .= "LOWER(\"Email\") = '".strtolower($member['email'])."'";
                        $delete_record = true;
                        error_log($this->error_log_prefix()."Member Record DELETED In MailChimp (Not Just Unsubscribed)");
                    } else {
                        error_log($this->error_log_prefix()." - API Call Success: listMemberInfo('".$list->ListID."', '".$member['email']."');");
                        $where .= "\"MCMemberID\" = '".$memberInfo['data'][0]['web_id']."'";
                        $delete_record = false;  
                    }
                    
            	    error_log($this->error_log_prefix()." - MEMBER ".$member['email']." (MCID: ".$memberInfo['data'][0]['web_id'].")");
                    error_log($this->error_log_prefix()." - MailChimp Updated: ".$member['timestamp']);
            	    
            	    $dl = new DataList("MCSubscription");
            	    $sub = $dl->where($where)->first();
            	    
            	    if(!empty($sub) && !empty($delete_record)) { 
            	        error_log($this->error_log_prefix()." - Subscriber ID = ".$sub->ID." Deleted!");
            	        $sub->setSyncMailChimp(false);
            	        $sub->delete();
            	    } else if (!empty($sub)) {
            	        error_log($this->error_log_prefix()." - Subscriber ID = ".$sub->ID);
            	        $reason = (isset($member['reason_text']) && !empty($member['reason_text'])) ? $member['reason_text'] : $member['reason'];
            	        $sub->setField('Subscribed', 0);
            	        $sub->setField('UnsubscribeReason', $reason);
            	        $sub->setSyncMailChimp(false);
            	        $sub->write();
            	    } else {
            	        error_log($this->error_log_prefix()."Member In MailChimp List (".$list->Name.") and marked as unsubscribed but has no related MCSubscription object! Perhaps The Subscription Record Was Deleted On The Site Rather Than Just Unsubscribed and the Sync is only just catching up?");
            	    }
            	    
            	} // END foreach($retval['data] as $member) { 
            	    
            } // END if($retval['total] > 0) {

        } // END listMemberInfo() -> if($api->errorCode) {} else {
            
        // Assume Success If We Havn't Already Returned False By This Point
        return true;
             
    }
    
    // Clean Up Any Static Segments On This List Deleted Through MailChimp
    public function UpdateSegments($list = null) {
        
        if(!is_object($list)) {
            error_log($this->error_log_prefix()."UpdateSegments() Requireds a MCList Object Parameter!");
            return false;
        }
        
        $api = new MCAPI($this->apikey);
        
        $retval = $api->listStaticSegments($list->ListID);
        if ($api->errorCode){
            error_log($this->error_log_prefix()."API Call Failed: listStaticSegments('".$list->ListID."); Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage);
            return false;
        } else {
            error_log($this->error_log_prefix()."API Call Success: listStaticSegments('".$list->ListID.");");
            if(!empty($retval)) {
                $LiveSegmentIDs = array();
                foreach($retval as $segment) {
                    $dl = new DataList("MCListSegment");
                    $seg = $dl->where("\"MCListSegmentID\" = '".$segment['id']."'")->first();
                    // Create New Segment If It Doesnt Yet Exist On The Site (i.e. It Was Created On MailChimp)
                    if(empty($seg)) {
                        $seg = new MCListSegment();
                        $seg->setField("Title", $segment['name']);
                        $seg->setField("MCListSegmentID", $segment['id']);
                        $seg->setField("MCListID", $list->ID);
                        $seg->setSyncMailChimp(false);
                        $seg->write();
                    }
                    $LiveSegmentIDs[] = $segment['id'];
                }
            }
            // Get List of Segments Which Exist On Site But Not On MailChimp
            $where = "\"MCListID\" = '".$list->ID."'";
            $where .= (!empty($LiveSegmentIDs)) ? "AND \"MCListSegmentID\" NOT IN (".$this->arrayToCSV($LiveSegmentIDs).")" : ""; // Don't Delete Segments Which Still Exist
            $dl = new DataList("MCListSegment");
            $deadSegs = $dl->where($where);
            // Delete Dead Segments (Deleted On MailChimp Directly)
            if(!empty($deadSegs)) {
                foreach($deadSegs as $deadSeg) {
                    $deadSeg->setSyncMailChimp(false);
                    $deadSeg->delete();
                }
            }
        }
        
        return true;
        
    }
    
    // Clean Up All Records Subscription Status By Marking Any Subscriptions Present On The Website, But Not In MailChimp (MailChimp Admin Panel Deletions and Subscriptions Yet To Be Confirmed) As Unsubscribed
    public function CleanUpSubscriptionStatus($list = null) {
    
        if(!is_object($list)) {
            error_log($this->error_log_prefix()."CleanUpSubscriptionStatus() Requireds MCList Object Parameter!");
            return false;
        }
        
        $emails = array();
        
        $api = new MCAPI($this->apikey);
        
        // Get ALL Subscribed List Members
        $retval = $api->listMembers($list->ListID, 'subscribed', null, 0, 5000);
        if ($api->errorCode){
            error_log($this->error_log_prefix()."API Call Failed: listMembers('".$list->ListID."', 'subscribed', null, 0, 5000); Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage);
        } else {
            error_log($this->error_log_prefix()."API Call Success: listMembers('".$list->ListID."', 'subscribed', null, 0, 5000); Returned Members = ".$retval['total']);
        	if($retval['total'] > 0) {
            	foreach($retval['data'] as $member){
                    $emails[] = strtolower($member['email']);
            	}            	
            }
        }
        
        // Get ALL Unsubscribed List Members
        $retval = $api->listMembers($list->ListID, 'unsubscribed', null, 0, 5000);
        if ($api->errorCode){
            error_log($this->error_log_prefix()."API Call Failed: listMembers('".$list->ListID."', 'unsubscribed', null, 0, 5000); Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage);
        } else {
            error_log($this->error_log_prefix()."API Call Success: listMembers('".$list->ListID."', 'unsubscribed', null, 0, 5000); Returned Members = ".$retval['total']);
        	if($retval['total'] > 0) {
            	foreach($retval['data'] as $member){
                    $emails[] = strtolower($member['email']);
            	}            	
            }
        }
        
    	if(!empty($emails)) {
    	    // For All Subscription Records On The Webstie Which Arnt In The MailChimp List (MailChimp Admin Panel Deletions and Subscriptions Yet To Be Confirmed)
        	$dl = new DataList("MCSubscription");
    	    $subs = $dl->where("\"MCListID\" = '".$list->ID."' AND LOWER(\"Email\") NOT IN (".$this->arrayToCSV($emails).")");
    	    if(!empty($subs)) {
    	        foreach($subs as $sub) {
    	            $sub->setField("Subscribed", 0);
        	        $sub->setSyncMailChimp(false);
        	        $sub->write();
    	        }
    	    }
    	}

        
    }
    
    // Returns a CSV String Given An Array        
    public function arrayToCSV($arr) {
        if(!is_array($arr)){
            return false;
        } else {
            $str = "";
            foreach($arr as $v) {
                $str .= "'".$v."',";
            }
            return substr($str, 0, -1);
        }
    }
    
    // Returns a Formatted DateTime for Prefixing error_log entries
    public function error_log_prefix(){
        return "[".date('Y-m-d H:i:s')."] ";
    }
    
}

?>