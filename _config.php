<?php

// Extensions
Object::add_extension('Member', 'MCMemberExtension');
Object::add_extension('SiteConfig', 'MCSiteConfigExtension');

// CMS Requirements
LeftAndMain::require_javascript('silverback/javascript/actions.js');

/**********************************/
/* Logging Information and Errors */
/**********************************/

// Clear any default writers
SS_Log::clear_writers();

// Logging notices & information 
SS_Log::add_writer(new SS_LogFileWriter('../assets/silverback/logs/silverback-info.log'), SS_Log::NOTICE, '=');

// Logging warnings & errors
SS_Log::add_writer(new SS_LogFileWriter('../assets/silverback/logs/silverback-error.log'), SS_Log::WARN, '<=');