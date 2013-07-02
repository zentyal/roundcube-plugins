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

$(document).ready(function(){rcmail.gui_object("message","#message");msgid=rcmail.set_busy(!0,"loading");rcmail.env.keep_alive=!1;window.onunload=function(){opener.rcmail.env.calpopup=!1};$(window).resize(function(){var a=10*parseInt($(window).width()/rcmail.env.cal_print_cols/10);$(".print").each(function(){$(this).css("max-width",a+50+"px")})});rcmail.env.myevents=[];rcmail.env.myeventobjects=[];rcmail.env.calendar_print_curview="calendar";calendar_commands.init();rcmail.addEventListener("plugin.getSettings",
function(a){rcmail.env.calsettings=a;rcmail.set_busy(!1,"loading",msgid);var d=new Date(1E3*queryString("_date")),b=new Date,f=b.getFullYear(),g=b.getMonth(),b=b.getDate();"false"!=d&&(d=$.fullCalendar.parseDate(new Date(1E3*queryString("_date"))),f=d.getFullYear(),g=d.getMonth(),b=d.getDate());$("#calendar").fullCalendar({header:{left:"",center:"title",right:""},height:$(window).height()-70,titleFormat:{month:a.settings.titleFormatMonth,week:a.settings.titleFormatWeek,day:a.settings.titleFormatDay},
columnFormat:{month:a.settings.columnFormatMonth,week:a.settings.columnFormatWeek,day:a.settings.columnFormatDay},theme:a.settings.ui_theme_main,editable:!1,eventSources:calendar_common.eSources(a),ignoreTimezone:!1,date:b,month:g,year:f,monthNames:a.settings.months,monthNamesShort:a.settings.months_short,dayNames:a.settings.days,dayNamesShort:a.settings.days_short,firstDay:a.settings.first_day,firstHour:a.settings.first_hour,timeFormat:js_time_formats[rcmail.env.rc_time_format],axisFormat:js_time_formats[rcmail.env.rc_time_format],
defaultView:queryString("_view"),allDayText:rcmail.gettext("all-day","calendar"),loading:function(a){if(a){msgid=rcmail.set_busy(true,"loading");rcmail.enable_command("plugin.calendar_do_print",false);rcmail.enable_command("plugin.calendar_toggle_view",false)}else{rcmail.set_busy(false,"loading",msgid);a=new Date(queryString("_date")*1E3);$("#calendar").fullCalendar("gotoDate",$.fullCalendar.parseDate(a));rcmail.env.myevents=[];$(window).resize(function(){$("#print").width($(window).width()-20);$("#agendalist").width($(window).width()-
20)});$("#caltoggleviewbut").trigger("click")}},eventRender:function(c,b,d){rcmail.enable_command("plugin.calendar_do_print",true);rcmail.enable_command("plugin.calendar_toggle_view",true);if(c.description){var e=c.description;e.length>100&&(e=e.substring(0,100)+" ...");b.find("span.fc-event-title").after('<span class="fc-event-description"><br />'+e+"</span>")}if(c.className){e=c.classNameDisp;if(!e)e=c.className;b.find("span.fc-event-title").after('<span class="fc-event-categories"><br />'+e+"</span>")}if(c.end&&
c.allDay!=1){d=c.end.format(js_time_formats[rcmail.env.rc_time_format]);b.find(".fc-event-title").html(" - "+d+" "+c.title)}else calendar_common.modifyEvents(c,b,d,a)}});$("#toolbar").show()});rcmail.http_post("plugin.getSettings","")});