<?php 

class MCList extends DataObject {
    
    public static $db = array(
        'ListID' => 'Varchar(255)',
        'WebID' => 'Int',
        'Name' => 'Varchar(255)',
        'Subscribed' => 'Int',
        'Unsubscribed' => 'Int',
        'Cleaned' => 'Int',
        'SortOrder' => 'Int'
    );
    
    public static $has_many = array(
        'MCListFields' => 'MCListField',
        'MCListSegments' => 'MCListSegment',
        'MCSubscriptions' => 'MCSubscription'
    );
    
    public static $default_sort = "SortOrder ASC";
    
    public function getCMSFields() {
        
        $lid = new TextField('ListID', 'List ID');
        $wid = new TextField('WebID', 'Web ID');
        $name = new TextField('Name', 'List Name');
        $sub = new TextField('Subscribed', '# of Subscribed Members');
        $unsub = new TextField('Unsubscribed', '# of Un-Subscribed Members');
        $clean = new TextField('Cleaned', '# of Cleaned Members');
        
        $fields = parent::getCMSFields();
        $fields->addFieldToTab('Root.Main', $lid->performReadonlyTransformation());
        $fields->addFieldToTab('Root.Main', $wid->performReadonlyTransformation());
        $fields->addFieldToTab('Root.Main', $name->performReadonlyTransformation());
        $fields->addFieldToTab('Root.Main', $sub->performReadonlyTransformation());
        $fields->addFieldToTab('Root.Main', $unsub->performReadonlyTransformation());
        $fields->addFieldToTab('Root.Main', $clean->performReadonlyTransformation());
        
        $fields->removeByName("SortOrder");
        $fields->removeByName("MCListFields");
        $fields->removeByName("MCListSegments");
        $fields->removeByName("MCSubscriptions");
        
        /* START LIST SUBSCRIBERS GRIDFIELD */
        $config = GridFieldConfig_RecordEditor::create();
        $addComponent = $config->getComponentByType("GridFieldAddNewButton");
        $addComponent->setButtonName("Add Subscriber");
        $config->getComponentByType('GridFieldDataColumns')->setDisplayFields(array(
		    'FirstName' => 'First Name',
		    'Surname' => 'Surname',
		    'Email' => 'E-mail',
		    'Subscribed' => 'Active Subscription',
		    'Created' => 'Created',
		    'LastEdited' => 'LastEdited'
		));
		$gf = new GridField(
	      'MCSubscribers',
	      'List Subscribers',
	      $this->getComponents("MCSubscriptions", "", "\"MCListID\" ASC, \"Surname\" ASC, \"FirstName\" ASC, \"Email\" ASC"),
	      $config
	    );
	    $fields->addFieldToTab('Root.ListSubscribers', $gf);
        /* FINISH LIST SUBSCRIBERS GRIDFIELD */
        
        /* START MC LIST SEGMENTS GRIDFIELD */
        $config = GridFieldConfig_RecordEditor::create();
        $addComponent = $config->getComponentByType("GridFieldAddNewButton");
        $config->removeComponent($addComponent);
        $editComponent = $config->getComponentByType("GridFieldEditButton");
        $config->removeComponent($editComponent);
        $config->getComponentByType('GridFieldDataColumns')->setDisplayFields(array(
		    'getRelatedEventName' => 'Related Event Title',
		    'MCListSegmentID' => 'List Segment ID',
		    'Title' => 'List Segment Title'
		));
		$gf = new GridField(
	      'MCListSegments',
	      'List Segments',
	      $this->getComponents('MCListSegments'),
	      $config
	    );
	    $fields->addFieldToTab('Root.ListSegments', $gf);
        /* FINISH MC LIST SEGMENTS GRIDFIELD */
        
        /* START MC LIST FIELDS GRIDFIELD */
        $config = GridFieldConfig_RecordEditor::create();
        //$config->getComponentByType("GridFieldAddNewButton")->setButtonName("Add List Field Relationships");
        // Should Only Be Able To Add Merge Tags Which Actually Exist In MailChimp. Therefore Do A List Sync Then Edit The Pulled In Merge Tags!
        $addComponent = $config->getComponentByType("GridFieldAddNewButton");
        $config->removeComponent($addComponent);
        $deleteComponent = $config->getComponentByType("GridFieldDeleteAction");
        $config->removeComponent($deleteComponent);
        $config->getComponentByType('GridFieldDataColumns')->setDisplayFields(array(
            'MergeTag' => 'MailChimp Merge Tag',
            'FieldName' => 'Field Name',
            'OnClass' => 'Class Name',
            'SyncDirection' => 'Sync Direction'
        ));
        $gf = new GridField(
          'MCListFields',
          'MCListField',
          $this->getComponents('MCListFields'),
          $config
        );
        $fields->addFieldToTab('Root.ListFieldMappings', $gf);
        /* FINISH MC LIST FIELDS GRIDFIELD */
        
        return $fields;
    }
    
}