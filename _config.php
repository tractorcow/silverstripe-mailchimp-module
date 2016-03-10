<?php

/**
 * This work is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc/4.0/.
 */

/**
 * CMS Requirements
 */
Config::inst()->update(
    'LeftAndMain',
    'extra_requirements_javascript',
    array(
        basename(__DIR__) . '/javascript/actions.js' => array()
    )
);

/**********************************/
/* Logging Information and Errors */
/**********************************/

// Clear any default writers
SS_Log::clear_writers();

// Logging notices & information
SS_Log::add_writer(
    new SS_LogFileWriter('../assets/' . basename(__DIR__) . '/logs/info.log'),
    SS_Log::NOTICE,
    '='
);

// Logging warnings & errors
SS_Log::add_writer(
    new SS_LogFileWriter('../assets/' . basename(__DIR__) . '/logs/error.log'),
    SS_Log::WARN,
    '<='
);