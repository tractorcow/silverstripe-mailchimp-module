<?php 

/**
 * This work is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc/4.0/.
 */
class MCListField extends DataObject {
    
    public static $db = array(
        'MergeTag' => 'Varchar(255)',
        'FieldName' => 'Varchar(255)',
        'OnClass' => 'Enum("MCSubscription, Member", "MCSubscription")',
        'SyncDirection' => 'Enum("Import, Export, Both", "Both")'
    );
    
    public static $has_one = array(
        'MCList' => 'MCList'
    );
    
    public function getCMSFields() {
        $fields = parent::getCMSFields();
                
        $mergeTag = new TextField('MergeTag', 'Merge Tag');
        $onClass = new DropdownField('OnClass', 'On Record', $this->AvailableClasses(), 'MCSubscription');
        $fieldName = new GroupedDropdownField('FieldName', 'Field Name', $this->PopulateClassFieldNames());
        
        $fields->removeByName("MCListID"); 
        $fields->removeByName("MergeTag");
        $fields->removeByName("OnClass"); 
        $fields->removeByName("FieldName"); 
        
        $fields->addFieldToTab('Root.Main', $mergeTag->performReadonlyTransformation(), 'SyncDirection'); 
        $fields->addFieldToTab('Root.Main', $onClass, 'SyncDirection');
        $fields->addFieldToTab('Root.Main', $fieldName, 'SyncDirection');  
        
        return $fields;
    }
    
    public function AvailableClasses() {
        return singleton('MCListField')->dbObject('OnClass')->enumValues();
    }
    
    public function PopulateClassFieldNames() {
        $classes = $this->AvailableClasses();
        $fieldnames = array();
        $exclusions = array(
            'MCMemberID',
            'MCEmailID',
            'Subscribed',
            'UnsubscribeReason',
            'DoubleOptIn',
            'Password',
            'RememberLoginToken',
            'Bounced',
            'AutoLoginHash',
            'AutoLoginExpired',
            'PasswordEncryption',
            'Salt',
            'PasswordExpiry',
            'LockedOutUntil',
            'Locale'
            
        );
        foreach($classes as $class) {
            $array = singleton($class)->db();
            // db() returns array in fieldname => specification format, we are only interesed in the keys (fieldnames) for both key => values
            foreach($array as $fieldname => $type) {
                // Exclude certain field names
                if(!in_array($fieldname, $exclusions)) {
                    $fieldnames[$class][$fieldname] = $fieldname;
                }
            }
            natsort($fieldnames[$class]);
        }        
        return $fieldnames;
    }
    
}