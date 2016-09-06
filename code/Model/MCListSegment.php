<?php

class MCListSegment extends DataObject {

    private $_firstWrite;
    private $_syncMailChimp;

    public static $db = array(
        'Title' => 'Varchar(255)',
        'MCListSegmentID' => 'Int'
    );

    public static $has_one = array(
        'Event' => 'MCEvent',
        'MCList' => 'MCList'
    );

    public static $defaults = array(
        'MCListSegmentID' => 0
    );

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

    public function getCMSFields() {
        $fields = parent::getCMSFields();
        return $fields;
    }

    public function getRelatedListName() {
        $list = $this->getComponent("MCList");
        if(!empty($list->ID)) {
            return (!empty($list->Name)) ? $list->Name : false;
        } else {
            return false;
        }
    }

    public function getRelatedEventName() {
        $event = $this->getComponent("Event");
        if(!empty($event->ID)) {
            return (!empty($event->Title)) ? $event->Title : "(Unknown Event Title)";
        } else {
            return "(Not Related To Any Event)";
        }
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

    public function onAfterWrite() {

        parent::onAfterWrite();

        if(
            $this->isChanged("ID") && // If This Is The Creation Write
            $this->getFirstWrite() && // And This Is The First Run Of Write
            $this->getSyncMailChimp() // And We Want To Sync To MailChimp (i.e. Not On Creation via MailChimp Import)
        ) {

            $apikey = SiteConfig::current_site_config()->getMCAPIKey();
            $api = new MCAPI($apikey);

            $list = $this->getComponent("MCList");
            if(!empty($list)) {

                // Limitited to 50 Bytes (Hopefully 50 Chars)
                $SegmentTitle = substr($this->Title, 0,  45);

                $api->listStaticSegmentAdd($list->ListID, $SegmentTitle);

                if($api->errorCode) {
                    SS_Log::log("API Call Failed: listStaticSegmentAdd(); | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                } else {
                    SS_Log::log("API Call Success: listStaticSegmentAdd();", SS_Log::NOTICE);

                    // Make Second Call To Return MailChimp Segment ID
                    $segments = $api->listStaticSegments($list->ListID);
                    foreach($segments as $segment) {
                        // Make Sure We Capture the WHOLE Event ID!
                        $id_chars = strlen((string)$this->EventID);
                        $id = substr($segment['name'], 6, $id_chars);
                        if($id == $this->EventID) {
                            SS_Log::log("This Event ID = ".$this->EventID.", Static Segment Named ".$segment['name']." Relates to Event ID ".$id, SS_Log::NOTICE);
                            SS_Log::log("We Have a Match", SS_Log::NOTICE);
                            $this->setField("MCListSegmentID", $segment['id']);
                            $this->write();
                            break;
                        }
                    }

                }
            }

       } // END: if($this->isChanged("ID")) {

       $this->setFirstWrite(false);

    }

    public function onAfterDelete() {

        parent::onBeforeDelete();

        $apikey = SiteConfig::current_site_config()->getMCAPIKey();
        $api = new MCAPI($apikey);

        $list = $this->getComponent("MCList");
        if(
            !empty($list->ID) && // If We Have a Related List
            $this->getSyncMailChimp() // And We Want To Sync To MailChimp (i.e. Not a Deletion via MailChimp Import Sync)
        ) {
            $api->listStaticSegmentDel($list->ListID, $this->MCListSegmentID);
            if($api->errorCode) {
               SS_Log::log("API Call Failed: listStaticSegmentDel(); | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
            } else {
               SS_Log::log("API Call Success: listStaticSegmentDel();", SS_Log::NOTICE);
            }
        }

    }

}