<?php

/**
 * This work is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc/4.0/.
 *
 * A very simple example event object. This is provided solely so that MCEvent
 * has something to extend from. You can modify this file and/or replace it
 * entirely with your own event however, be aware that the $belongs_many_many
 * relationship to Member and the $db 'Title' should remain as they are used
 * as part of the MCListSegment set up and ongoing syncronisation of attendees
 * to the segment in Mailchimp. If you don't want your Event to have a title
 * field, make sure you provide a getTitle() method instead.
 */
class Event extends DataObject {

    private static $db = array(
        'Title'             => 'Varchar(255)',
        'Description'       => 'HTMLText',
        'FinishDateTime'    => 'SS_DateTime',
        'StartDateTime'     => 'SS_DateTime'
    );

    private static $belongs_many_many = array(
        'Attendees' => 'Member'
    );

    public function getCMSFields() {

        $fields = parent::getCMSFields();

        $startDateTime = new DateTimeField('StartDateTime', 'Event Start Date & Time');
        $startDateTime->getDateField()->setConfig('showcalendar', true);
        $startDateTime->getDateField()->setConfig('datevalueformat', 'YYYY-MM-dd');
        $startDateTime->getDateField()->setConfig('dateformat', 'dd-MM-YYYY');

        $finishDateTime = new DateTimeField('FinishDateTime', 'Event Finish Date & Time');
        $finishDateTime->getDateField()->setConfig('showcalendar', true);
        $finishDateTime->getDateField()->setConfig('dateformat', 'dd-MM-YYYY');
        $finishDateTime->getDateField()->setConfig('datevalueformat', 'YYYY-MM-dd');

        $fields->addFieldToTab('Root.Main', $startDateTime);
        $fields->addFieldToTab('Root.Main', $finishDateTime);

        return $fields;

    }

    public function getCMSValidator() {
        return new RequiredFields(
            array(
                'StartDateTime',
                'Title',
            )
        );
    }

}