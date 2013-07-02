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

$(document).ready(function(){rcmail.env.event_imported=!1;rcmail.env.event_previewed=[]});
function calendar_icalattach(){this.save=function(f,a){var b="&_items="+urlencode(a);c=1;if(!a){try{c=rcmail.env.myevents.length}catch(d){c=parent.rcmail.env.myevents.length}b=""}confirm(rcmail.gettext("calendar.importconfirmation").replace("%s",c))&&(rcmail.http_post("plugin.saveical","_uid="+rcmail.env.uid+"&_mbox="+urlencode(rcmail.env.mailbox)+"&_part="+urlencode(f)+b,!0),rcmail.env.event_imported=!0,setTimeout("calendar_icalattach.save_after()",1E3));return!1};this.save_after=function(){this.remove();
try{parent.$("#upcoming").fullCalendar("refetchEvents")}catch(f){$("#upcoming").fullCalendar("refetchEvents")}};this.preview=function(f){var a=new Date(f),b=a.getDate(),d=a.getMonth(),e=a.getFullYear();try{var g=parent.$("#upcoming").fullCalendar("getDate")}catch(h){g=$("#upcoming").fullCalendar("getDate")}if(g.getDate()!=a||g.getMonth()!=d||g.getFullYear()!=e){try{parent.$("#upcoming").fullCalendar("gotoDate",e,d,b),parent.$("#upcoming").fullCalendar("refetchEvents")}catch(i){$("#upcoming").fullCalendar("gotoDate",
e,d,b),$("#upcoming").fullCalendar("refetchEvents")}a=new Date(f+864E5);b=a.getDate();d=a.getMonth();e=a.getFullYear();try{parent.$("#upcoming_1").fullCalendar("gotoDate",e,d,b)}catch(j){$("#upcoming_1").fullCalendar("gotoDate",e,d,b)}a=new Date(f+1728E5);b=a.getDate();d=a.getMonth();e=a.getFullYear();try{parent.$("#upcoming_2").fullCalendar("gotoDate",e,d,b)}catch(k){$("#upcoming_2").fullCalendar("gotoDate",e,d,b)}}};this.remove=function(){try{parent.rcmail.env.myevents=[],parent.$("#upcoming").fullCalendar("removeEvents",
"preview"),parent.$("#upcoming-container").scrollTop(0)}catch(f){rcmail.env.myevents=[],$("#upcoming").fullCalendar("removeEvents","preview"),$("#upcoming-container").scrollTop(0)}}}calendar_icalattach=new calendar_icalattach;