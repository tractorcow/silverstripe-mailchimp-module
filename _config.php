<?php

/**
 * This work is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc/4.0/.
 */

// Extensions
Object::add_extension('Member', 'MCMemberExtension');
Object::add_extension('SiteConfig', 'MCSiteConfigExtension');

// CMS Requirements
LeftAndMain::require_javascript(__DIR__ . '/javascript/actions.js');

/**********************************/
/* Logging Information and Errors */
/**********************************/

// Clear any default writers
SS_Log::clear_writers();

// Logging notices & information 
SS_Log::add_writer(new SS_LogFileWriter('../assets/silverstripe-mailchimp-module/logs/info.log'), SS_Log::NOTICE, '=');

// Logging warnings & errors
SS_Log::add_writer(new SS_LogFileWriter('../assets/silverstripe-mailchimp-module/logs/error.log'), SS_Log::WARN, '<=');