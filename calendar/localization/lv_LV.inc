<?php
/*
+-----------------------------------------------------------------------+
| language/lv_LV/labels.inc                                             |
|                                                                       |
| Language file of the RoundCube Webmail client                         |
| Copyright (C) 2008-2009, RoundQube Dev. - Switzerland                 |
| Licensed under the GNU GPL                                            |
|                                                                       |
+-----------------------------------------------------------------------+
| Author: M.Dauģis martin.daugis@gmail.com                                                              |
+-----------------------------------------------------------------------+

*/
$labels = array();

// config
$labels['default_view'] = 'Noklusējuma skats';
$labels['timeslots'] = 'Timeslots per hour';
$labels['duration'] = 'Event duration (default)';
$labels['workdays'] = 'Darba dienas';
$labels['first_day'] = 'Pirmā nedēļas diena';
$labels['categories'] = 'Kategorijas';
$labels['add_category'] = 'Pievienot kategoriju';
$labels['remove_category'] = 'Nodzēst kategoriju';
$labels['calendar_feeds'] = 'Calendar Feeds';
$labels['add_feed'] = 'Add Feed';
$labels['remove_feed'] = 'Remove Feed';
$labels['cal_notify'] = 'Brīdināt ar epastu';
$labels['cal_notify_to'] = 'Saņēmējs';
$labels['caltoken'] = 'Token';
$labels['calconfidentialurl'] = 'Confidential feed access [%s]';
$labels['read_write'] = 'Pilna pieeja (lasīt/rakstīt)';
$labels['readonly'] = 'tikai lasīt';
$labels['calfeedurl'] = 'Feed URL';
$labels['feedaccesshint'] = 'Copy and paste the above URL and pass it to other %s users to share your calendar events or access the URL by a Feed reader. Change or remove the token to close access.';
$labels['calicsurl'] = 'ICS URL';
$labels['icsaccesshint'] = 'Use the above URL to access your calendar by Thunderbird or other Desktop clients. Change or remove the token to close access.';

// calendar
/* syntax for date/time formatting: http://arshaw.com/fullcalendar/docs/text/ */
$labels['timeFormat'] = 'h:mmt';
$labels['titleFormatMonth'] = 'MMMM yyyy';
$labels['titleFormatDay'] = 'dddd,';
$labels['columnFormatMonth'] = 'ddd';
$labels['columnFormatWeek'] = 'ddd';
$labels['columnFormatDay'] = 'dddd';
/* jquery-ui timepicker addon */
$labels['timeOnlyTitle'] = 'Izvēlēties laiku';
$labels['timeText'] = 'Laiks';
$labels['hourText'] = 'Stunda';
$labels['minuteText'] = 'Minūte';
$labels['currentText'] = 'Tagad';
$labels['closeText'] = 'Darīts';
/* others */
$labels['calendar'] = 'Kalendārs';
$labels['yes'] = 'jā';
$labels['no'] = 'nē';
$labels['day'] = 'Diena';
$labels['workday'] = 'Darbadiena';
$labels['week'] = 'Nedēļa';
$labels['calendar_week'] = 'Kalendārs nedēļa';
$labels['month'] = 'Mēnesis';
$labels['year'] = 'Gads';
$labels['new_event'] = 'Jauns notikums';
$labels['edit_event'] = 'Labot notikumu';
$labels['save'] = 'Saglabāt';
$labels['remove'] = 'Dzēst';
$labels['cancel'] = 'Atcelt';
$labels['summary'] = 'Kopsavilkums';
$labels['description'] = 'Apraksts';
$labels['all-day'] = 'visa-diena';
$labels['export'] = 'Eksportēt uz ICS';
$labels['category'] = 'Kategorija';
$labels['preview'] = 'Priekšskatījums';
$labels['location'] = 'Atrašanās vieta';
$labels['default'] = 'None';
$labels['date'] = 'Datums';
$labels['start'] = 'Sākums';
$labels['end'] = 'Beigas';
$labels['recur'] = 'Atkārtots notikums katru:';
$labels['recurs'] = 'Atkārtot';
$labels['expires'] = 'Beidzas';
$labels['infinite'] = 'infinite';
$labels['never'] = 'nekad';
$labels['occurrences'] = 'Atkārtošanās skaits';
$labels['toggle_view'] = 'Toggle view';
$labels['generated'] = 'Ģenerēts';
$labels['selectdate'] = 'Izvēlēties datumu';
$labels['prev_year'] = 'Iepriekšējais gads';
$labels['prev_month'] = 'Iepriekšējais mēnesis';
$labels['next_year'] = 'Nākamais gads';
$labels['next_month'] = 'Nākamais mēnesis';
$labels['choose_date'] = 'Izvēlēties datumu';
$labels['click_here'] = 'Spiest šeit';
$labels['removed'] = 'Notikums dzēsts!';
$labels['edited_by'] = 'Labojis';
$labels['addicalmsg'] = 'Pieveinot kalendāram';
$labels['icalsavefailed'] = 'Neizdodas pievienot kalendāra failu';
$labels['importedsuccessfully'] = 'Vaiksmīgi pievienots';
$labels['switch_calendar'] = 'Nomainīt kalendāru';
$labels['cal_user'] = 'Kalendāra lietotājs';
$labels['submit'] = 'Pievienot';
$labels['filter_events'] = 'Filtrēt notikumus';
$labels['allevents'] = 'visi notikumi';
$labels['removefilters'] = 'Noņemt filtrus';
$labels['defaultcategory'] = 'Noklusējuma';
$labels['general'] = 'Pamata';
$labels['schedule'] = 'Grafiks';
$labels['every'] = 'Ik';
$labels['bydays'] = 'Pēc dienām';
$labels['byweekdays'] = 'Pēc darbadienām';
$labels['bymonthdays'] = 'Pēc mēnešiem';
$labels['advanced'] = 'Papildus';
$labels['first'] = 'pirmais';
$labels['second'] = 'otrais';
$labels['third'] = 'trešais';
$labels['fourth'] = 'ceturtais';
$labels['fifth'] = 'piektais';
$labels['last'] = 'pēdējais';
$labels['january'] = 'Janvāris';
$labels['february'] = 'Februāris';
$labels['march'] = 'Marts';
$labels['april'] = 'Aprīlis';
$labels['may'] = 'Maijs';
$labels['june'] = 'Jūnijs';
$labels['july'] = 'Jūlijs';
$labels['august'] = 'Augusts';
$labels['september'] = 'Septembris';
$labels['october'] = 'Oktobris';
$labels['november'] = 'Novembris';
$labels['december'] = 'Decembris';
$labels['in'] = 'in';
$labels['export_as_file'] = 'Kā failu';
$labels['send_invitation'] = 'Sūtīt uzaicinājumu';
$labels['invitation_subject'] = 'Notikuma uzaicinājums';
$labels['upcoming'] = 'Notikums sākas';
$labels['reminder_before'] = 'Atgādināt pirms notikuma sākuma';
$labels['show_upcoming_cal'] = 'Ieslēgt gaidāmajiem notikumiem priekšskatījums';
$labels['invalidresponse'] = 'Invalid response from';
$labels['verifyexpiredate'] = 'The expiration date is implausible. Do you want to check it?';
$labels['recurwarning'] = 'This is a recurring event.';
$labels['importconfirmation'] = 'Do you want to import attached event(s) into your calendar [%s item(s)]?';
$labels['item'] = 'Item';
$labels['run_tests'] = 'Test settings (accessible for calendar admin only)';
$labels['undefined'] = '---';

?>