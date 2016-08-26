<?php

/**
 * Extending the ManyManyList Class in Order to Add Hooks in the add()/remove() Methods Which Trigger Logic When Two DataObjects Have a Relationship Created/Destroyed
 *
 * @todo
 * - Capture The Appropriate Pair of Objects in the add()/remove() methods
 * - Move the onLink() and onUnlink() methods in to the DataObject Extension
 * - Pass The A Object to the B Object onLink()/onUnlink() Method (e.g. in add() - $event->onLink($member); $member->onLink($event);)
 *
*/
class MyManyManyList extends ManyManyList{

    /**
     * Add an item to this many_many relationship
     * Does so by adding an entry to the joinTable.
     * @param $extraFields A map of additional columns to insert into the joinTable
    */
    public function add($item, $extraFields = null) {

        parent::add($item, $extraFields);

        $this->onLink($item);

    }

    /**
     * Overloading ManyManyList removeByID Rather Then remove() as That Just Calls removeByID()
     * Remove the given item from this list.
     * Note that for a ManyManyList, the item is never actually deleted, only the join table is affected
     * @param $itemID The item it
    */
    public function removeByID($itemID) {

        parent::removeByID($itemID);

        // Get Item Object Itself Based on $itemID
        $dl = new DataList($this->dataClass);

        // Must Include Table Prefix To Prevent "Column 'ID' in where clause is ambiguous" Error
        $item = $dl->where("\"".$this->dataClass."\".\"ID\" = '".$itemID."'")->first();

        $this->onUnlink($item);

    }

    public function onLink($item) {

        $apikey = SiteConfig::current_site_config()->getMCAPIKey();
        $api = new MCAPI($apikey);

        // Define Child/Parent Objects Being Related Based on ManyManyList's (the Parent Class) localKey/foreignKey Fields
        $member = Member::get()->where("\"ID\" = '".$this->foreignID."'")->first();
        $event = $item;
        $segments = $event->getComponents("MCListSegments");

        // Mailchimp Static Segment Addition Logic
        if(!empty($segments)) {
            foreach($segments as $segment) {
                $list = $segment->getComponent("MCList");
                // Get All Subscription Records This Member Has For This List (Which Arnt Unsubscribed)
                $subs = $member->getComponents("MCSubscriptions", "\"MCListID\" = '".$list->ID."' AND \"Subscribed\" = '1'");
                if($subs->count() > 0) {
                    // An Array of E-mail Address or MailChimp Provided ID's For Identifying Records
                    $identifiers = array();
                    foreach($subs as $sub) {
                        $identifiers[] = $sub->getMailChimpIdentifier();
                        SS_Log::log("MC Identifier: " . $sub->getMailChimpIdentifier(), SS_Log::NOTICE);
                    }
                    $api->listStaticSegmentMembersAdd($list->ListID, $segment->MCListSegmentID, $identifiers);
                    if($api->errorCode) {
                        SS_Log::log("API Call Failed: listStaticSegmentMembersAdd(".$list->ListID.", ".$segment->MCListSegmentID.", ".$identifiers."); | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                    } else {
                        SS_Log::log("API Call Success: listStaticSegmentMembersAdd(".$list->ListID.", ".$segment->MCListSegmentID.", ".$identifiers.");", SS_Log::NOTICE);
                    }
                }
            }
        }

        // // Send E-mail Confirmation of Event Attendance Update
        // $email = new Email();
        // $email->setFrom('noreply@example.com');
        // $email->setBcc('admin@example.com');
        // $email->setTo($member->Email);
        // $email->setSubject('Event Attendance Updated');
        // $email->setTemplate("EventAttendanceUpdated");
        // $email->populateTemplate(array(
        //     'Event' => $event,
        //     'Member' => $member
        // ));
        // $email->send();

    }

    public function onUnlink($item) {

        $apikey = SiteConfig::current_site_config()->getMCAPIKey();
        $api = new MCAPI($apikey);

        // Define Child/Parent Objects Being Related Based on ManyManyList's (the Parent Class) localKey/foreignKey Fields
        $member = Member::get()->where("\"ID\" = '".$this->foreignID."'")->first();
        $event = $item;
        $segments = $event->getComponents("MCListSegments");

        // Mailchimp Static Segment Removal Logic
        if(!empty($segments)) {
            foreach($segments as $segment) {
                $list = $segment->getComponent("MCList");
                // Get All Subscription Records This Member Has For This List
                // The Fact That This Could Include Subscriptions Which Arnt Actually In This Static Segment
                // (i.e Unsubscribed Subs and/or Newly Added Subs Since SegmentMembersAdd() Was Run)
                // Means We Are Liable To Get API Call Erros, They Should Not Do Any Harm Though
                $subs = $member->getComponents("MCSubscriptions", "\"MCListID\" = '".$list->ID."'");
                if($subs->count() > 0) {
                    // An Array of E-mail Address or MailChimp Provided ID's For Identifying Records
                    $identifiers = array();
                    foreach($subs as $sub) {
                        $identifiers[] = $sub->getMailChimpIdentifier();
                        SS_Log::log("MC Identifier: " . $sub->getMailChimpIdentifier(), SS_Log::NOTICE);
                    }
                    $api->listStaticSegmentMembersDel($list->ListID, $segment->MCListSegmentID, $identifiers);
                    if($api->errorCode) {
                        SS_Log::log("API Call Failed: listStaticSegmentMembersDel(".$list->ListID.", ".$segment->MCListSegmentID.", ".$identifiers."); | Error Code = ".$api->errorCode . " | Error Message = " . $api->errorMessage, SS_Log::ERR);
                    } else {
                        SS_Log::log("API Call Success: listStaticSegmentMembersDel(".$list->ListID.", ".$segment->MCListSegmentID.", ".$identifiers.");", SS_Log::NOTICE);
                    }
                }
            }
        }

    }

}