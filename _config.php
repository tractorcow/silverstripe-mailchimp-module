<?php

// Extensions
Object::add_extension('Member', 'MCMemberExtension');
Object::add_extension('SiteConfig', 'MCSiteConfigExtension');

//CMS Requirements
LeftAndMain::require_javascript('silverback/javascript/actions.js');

?>