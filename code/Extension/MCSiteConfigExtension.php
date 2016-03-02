<?php

/**
 * This work is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc/4.0/.
 */
class MCSiteConfigExtension extends DataExtension {
    
    public static $db = array(
        'MCApiKey' => 'Varchar(255)'
    );
    
    public static $has_many = array(
        'MCSubscriptions' => 'MCSubscription'
    );
    
    public function getMCAPIKey() {
        return ($this->owner->getField("MCApiKey")) ? $this->owner->getField("MCApiKey") : false;
    }
    
    public function updateCMSFields(FieldList $fields) {
        
        $fields->addFieldToTab('Root.MailChimp', new TextField('MCApiKey', 'MailChimp API Key'));
        $fields->addFieldToTab('Root.MailChimp', new LiteralField('ListOrderNote', '<div style="width:auto; border:1px dashed orangered; padding:15px; margin:15px auto;"><p><strong>Note:</strong> The first list in this table is taken to be your main MailChimp list that new subscribers will be added to by default (unless specified).</p><p style="margin:0;">Use the drag and drop option to re-order the lists and change the default.</p></div>'));
        $fields->addFieldToTab('Root.MailChimp', new LiteralField('UpdateMCLists', '<a href="/mailchimp/UpdateLists" title="Update Lists From MailChimp" class="ajax-call ss-ui-button ss-ui-action-constructive">Update Lists From MailChimp</a>'));
        
        /* START MC LISTS GRIDFIELD */ 
        $config = GridFieldConfig_RecordEditor::create();
        $config->addComponent(new GridFieldOrderableRows('SortOrder'));
        $addComponent = $config->getComponentByType("GridFieldAddNewButton");
        $config->removeComponent($addComponent);
        $deleteComponent = $config->getComponentByType("GridFieldDeleteAction");
        $config->removeComponent($deleteComponent);
        $config->getComponentByType('GridFieldDataColumns')->setDisplayFields(array(
	      'ListID' => 'List ID',
	      'Name' => 'List Name',
	      'Subscribed' => 'Subscribed Members',
	      'Unsubscribed' => 'Un-Subscribed Members',
	      'Cleaned' => 'Cleaned Members'
	    ));
	    $l = new GridField(
	      'MCLists',
	      'MailChimp Lists',
	      new DataList("MCList"),
	      $config
	    );
        $fields->addFieldToTab('Root.MailChimp', $l);
        /* FINISH MC LISTS GRIDFIELD */
        
    }
    
}

?>