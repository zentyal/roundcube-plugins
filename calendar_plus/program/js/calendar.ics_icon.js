/***************************************************************************
 * This file is part of Roundcube "##PLUGIN##" plugin.              
 *                                                                 
 * Your are not allowed to distribute this file or parts of it.    
 *                                                                 
 * This file is distributed in the hope that it will be useful,    
 * but WITHOUT ANY WARRANTY; without even the implied warranty of  
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.          
 *                                                                 
 * Copyright (c) 2012 - ##YEAR## Roland 'Rosali' Liebl - all rights reserved
 * dev-team [at] myroundcube [dot] com
 * http://myroundcube.com
 ***************************************************************************/

function plugin_calendar_insertrow(a){var b=rcmail.env.messages[a.row.uid];("text/calendar"==a.row.ctype||b.flags&&b.flags.ics)&&$("#rcmrow"+a.uid+" > td.attachment").html('<img src="'+rcmail.env.ics_icon+'" alt="">')}$(document).ready(function(){window.rcmail&&rcmail.gui_objects.messagelist&&rcmail.addEventListener("insertrow",function(a){plugin_calendar_insertrow(a)})});