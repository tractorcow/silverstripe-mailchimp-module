<?php

class MCEvent extends DataObject {

    private static $belongs_many_many = array(
        'Attendees' => 'Member'
    );

    private static $has_many = array(
        'MCListSegments' => 'MCListSegment'
    );

    private static $summary_fields = array(
        'getTitle' => 'Title'
    );

    private static $searchable_fields = array(
        'ID'
    );

    public $_firstWrite;

    public $_AffectedMCListIDs;

    public function getCMSFields() {

        $fields = parent::getCMSFields();

        // Remove MCListSegment GridField Tab And Manually Manage
        $fields->removeByName("MCListSegments");

        // Manually Manage Creation of MCListSegments Based on Selected MCLists
        if(empty($this->owner->ID)) {
            $lists = new DataList("MCList");
            if($lists->count() > 1) {
                $fields->addFieldToTab('Root.Main', new LiteralField('_AffectedMCListsTitle', '<h2>Affected MailChimp Lists</h2>'));
                $map = $lists->map("ID", "Name");
                $listIDs = new CheckboxSetField('_AffectedMCListIDs', 'Which MailChimp List(s) Does This Event Relate To', $map);
                $listIDs->setDefaultItems(array_keys($map->toArray()));
            } else if ($lists->count() == 1) {
                $listIDs = new HiddenField('_AffectedMCListIDs', 'Which MailChimp List(s) Does This Event Relate To', $lists->first()->ID);
            } else {
                $listIDs = new HiddenField('_AffectedMCListIDs', 'Which MailChimp List(s) Does This Event Relate To', 0);
            }
            $fields->addFieldToTab('Root.Main', $listIDs);
        }

        // Configure Attendees Gridfield
        $gf = $fields->fieldByName('Root.Attendees.Attendees');
        if (is_object($gf) && $gf->exists()) {
            $gf->setList($this->getMyManyManyComponents('Attendees'));
            $config = $gf->getConfig();
            $config->removeComponentsByType('GridfieldAddNewButton');
        }

        return $fields;

    }

    // First Write Flag Getter/Setter Functions
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

        // On Event Creation Instanciate New MCListSegment Object(s)
        if($this->owner->isChanged("ID") && $this->getFirstWrite()) {
           if(!empty($this->_AffectedMCListIDs)) {
               $ListData = explode(',', $this->_AffectedMCListIDs);
               foreach($ListData as $ListID) {
                   $seg = new MCListSegment();
                   $seg->setField("MCListID", $ListID);
                   $seg->setField("EventID", $this->owner->ID);
                   $seg->setField("Title", "Event ".$this->owner->ID.": ".$this->owner->getTitle());
                   $seg->write();
               }
           }
        } // END: if($this->isChanged("ID")) {
        $this->setFirstWrite(false);
    }

    public function onAfterDelete() {

        parent::onAfterDelete();

        // Trigger Deletion Of All Related MCListSegments On Event Deletion
        $segments = $this->owner->getComponents("MCListSegments");
        foreach($segments as $segment) {
            $segment->delete();
        }

    }

}

class MCEvent_Controller extends ContentController {

    public static $allowed_actions = array(
        'AddAttendee',
        'RemoveAttendee'
    );

    public function init() {
        parent::init();
        $this->getResponse()->addHeader("X-Robots-Tag", "noindex");
    }

    public static function AddAttendee($mid = false, $eid = false, $CalledStatically = false) {

        if(empty($eid) || !is_int($eid)) {
            $eid = Controller::curr()->getRequest()->param('ID');
        }

        if(empty($mid) || !is_int($mid)) {
            $mid = Member::currentUserID();
        }

        $member = DataObject::get_by_id("Member", $mid);
        $event = DataObject::get_by_id('Event', $eid);
        $response = array();

        if(!empty($member) && is_object($member)) {

            if(is_object($event) && !empty($event)) {

                // Get a ManyManyList of This Members Events
                $membersEvents = $member->getMyManyManyComponents('Events');

                // See if This Event is Already in The Members Event List
                $existing = $membersEvents->byID($eid);

                if(!empty($existing)) {
                     // Store Response
                    $response['error'] = "This Member is Already Attending Event ID ".$eid."!";
                    $response['error_code'] = 1;
                } else {
                    // Add Event to Members Event List
                    $membersEvents->add($event);

                    // Store Response
                    $response['error'] = false;
                    $response['error_code'] = 0;
                }

            } else {
                // Store Response
                $response['error'] = "No Event Found for Event ID ".$eid."!";
                $response['error_code'] = 2;
            }

        } else {
            // Store Response
            $response['error'] = "No Member Found for Member ID ".$mid."!";
            $response['error_code'] = 3;
        }

        // Return a JSON object if method is called via AJAX otherwise redirect back to calling page on success and show user_error on error
        if(Director::is_ajax()) {
            echo json_encode($response, JSON_FORCE_OBJECT);
            exit();
        } else {
            if(!empty($response['error'])) {
                user_error($response['error']);
                exit();
            } else {
                return ($CalledStatically) ? true : Controller::curr()->redirectBack();
            }
        }

    }

    public static function RemoveAttendee($mid = false, $eid = false, $CalledStatically = false) {

        if(empty($eid) || !is_int($eid)) {
            $eid = Controller::curr()->getRequest()->param('ID');
        }

        if(empty($mid) || !is_int($mid)) {
            $mid = Member::currentUserID();
        }

        $member = DataObject::get_by_id("Member", $mid);
        $event = DataObject::get_by_id('Event', $eid);
        $response = array();

        if(!empty($member) && is_object($member)) {

            if(is_object($event) && !empty($event)) {

                // Get a ManyManyList of This Members Events
                $membersEvents = $member->getMyManyManyComponents('Events');

                // See if This Event is Already in The Members Event List
                $existing = $membersEvents->byID($eid);

                if(empty($existing)) {
                     // Store Response
                    $response['error'] = "This Member is Not Attending Event ID ".$eid."!";
                    $response['error_code'] = 1;
                } else {
                    // Add Event to Members Event List
                    $membersEvents->remove($event);

                    // Store Response
                    $response['error'] = false;
                    $response['error_code'] = 0;
                }

            } else {
                // Store Response
                $response['error'] = "No Event Found for Event ID ".$eid."!";
                $response['error_code'] = 2;
            }

        } else {
            // Store Response
            $response['error'] = "No Member Found for Member ID ".$mid."!";
            $response['error_code'] = 3;
        }

        // Return a JSON object if method is called via AJAX otherwise redirect back to calling page on success and show user_error on error
        if(Director::is_ajax()) {
            echo json_encode($response, JSON_FORCE_OBJECT);
            exit();
        } else {
            if(!empty($response['error'])) {
                user_error($response['error']);
                exit();
            } else {
                return ($CalledStatically) ? true : Controller::curr()->redirectBack();
            }
        }

    }

}