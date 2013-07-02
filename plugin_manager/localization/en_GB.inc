<?php

/*
 +-----------------------------------------------------------------------+
 | ./plugins/plugin_manager/localization/en_GB.inc
 |
 | Language file of MyRoundcube Plugins Bundle
 | Copyright (C) 2010-2012, Roland 'rosali' Liebl
 | Licensed under the GNU GPL
 |
 +-----------------------------------------------------------------------+
 | Author: myroundcube@mail4us.net - 03/20/2012 09:34:44
 +-----------------------------------------------------------------------+

*/

$labels = array();
$labels['plugin_manager_title'] = 'Manage Plugins';
$labels['calendaring'] = 'Calendaring';
$labels['misc'] = 'Miscellaneous';
$labels['translate'] = 'Translate this Plugin';
$labels['restoredefaults'] = 'Restore Defaults';
$labels['checkall'] = 'Check All';
$labels['uncheckall'] = 'Uncheck All';
$labels['toggle'] = 'Toggle Selection';
$labels['status'] = 'Status';
$labels['globalplugins'] = 'Global Plugins';
$labels['backend'] = 'Server Plugins';
$labels['messagesmanagement'] = 'Message Management';
$labels['furtherconfig'] = 'Do you want to configure the plugin now?';
$labels['uninstall'] = 'You are about to disable this plugin. Do you want to remove all saved settings permanently?';
$labels['uninstallconfirm'] = 'You are about to disable this plugin. WARNING: Select »DISABLE« to disable this plugi...';
$labels['areyousure'] = 'You can\'t undo this action. Are you really sure?';
$labels['successfullydeleted'] = 'Settings have been removed successfully.';
$labels['successfullysaved'] = 'Successfully saved.';
$labels['errorsaving'] = 'An error occured while saving.';
$labels['yes'] = 'Yes';
$labels['no'] = 'No';
$labels['disable'] = 'Disable';
$labels['remove'] = 'Remove';
$labels['savewarning'] = 'Do you want to save your changes yet?';
$labels['plugin_manager_update'] = 'Update Plugins';
$labels['authors_comments'] = 'Author\'s Comments (not translated)';
$labels['requiredby'] = 'Plugin is required by';
$labels['requires'] = 'Plugin requires';
$labels['recommended'] = 'Recommended Plugins';
$labels['update_plugins'] = 'Update Plugins';
$labels['ziparchive'] = 'Download Zip-Archive';
$labels['serverversion'] = 'Server Version';
$labels['mirrorversion'] = 'Mirror Version';
$labels['mirrorhost'] = 'Mirror Server';
$labels['comments'] = 'Requirements and Update Notes';
$labels['update_error'] = 'Version mismatch';
$labels['update_ok'] = 'Most recent has been detected. No update necessary.';
$labels['update_edit'] = 'Plugin has to be re-configured or requires database adjustments. Please consider Update Notes.';
$labels['servernewer'] = 'Registered Plugin is newer than Mirror Version.';
$labels['orhigher'] = 'or higher';
$labels['rc_ok'] = 'Roundcube Core is up to date.';
$labels['update_update'] = 'Plugin is outdated. Please update! Consider Update Notes.';
$labels['noupdates'] = 'No Updates selected.';
$labels['rc_update'] = 'Roundcube Core is outdated. Please update!';
$labels['rc_uptodate'] = 'Roundcube Core is up to date.';
$labels['rc_newer'] = 'Roundcube Core is newer than Mirror version!';
$labels['nottested'] = 'Please use Plugins with care! We don\'t know if hosted Plugins work with Roundcube v%s.';
$labels['justunzip'] = 'Just unzip the Archive over your existing Installation.';
$labels['guide'] = 'Read more...';
$labels['thirdparty'] = 'This is not a MyRoundcube Plugin.';
$labels['thirdpartywarning'] = 'This is a third party plugin. We recommend not to download the plugin from this Mirror Server. Do...';
$labels['develsite'] = 'Download';
$labels['notinstalled'] = 'not installed';
$labels['notregistered'] = 'not registered';
$labels['roundcubeurl'] = 'Download Roundcube';
$labels['languageupdate'] = 'Localization Update is available.';
$labels['donotregister'] = 'Don\'t register this plugin. It is loaded automatically.';
$labels['register'] = 'Register this plugin in ./config/main.inc.php <small>[$rcmail_config[\'plugins\'] = array("<i>foo<!--...</td--></i></small>';
$labels['noplugindescription'] = 'No plugin description detected.';
$labels['markbuttons_pluginname'] = 'Mark Message Buttons';
$labels['markbuttons_plugindescription'] = 'Add control icons under message list separator to quickly mark messages.';
$labels['chbox_pluginname'] = 'Message List Check Box';
$labels['chbox_plugindescription'] = 'Add a check box to each message row in message list.';
$labels['automatic_addressbook_pluginname'] = 'Automatic Addressbook';
$labels['automatic_addressbook_plugindescription'] = 'Collect email addresses of all recipients in a separate addressbook.';
$labels['keyboard_shortcuts_pluginname'] = 'Keyboard Shortcuts';
$labels['keyboard_shortcuts_plugindescription'] = 'Enter commands using keyboard shortcuts.';
$labels['message_highlight_pluginname'] = 'Highlight Messages';
$labels['message_highlight_plugindescription'] = 'Highlight messages which matches your filter options.';
$labels['fixTHEAD_pluginname'] = 'Fix Header of Message List';
$labels['fixTHEAD_plugindescription'] = 'Fix the header of message list.';
$labels['compose_addressbook_pluginname'] = 'Pick up Addresses';
$labels['compose_addressbook_plugindescription'] = 'Pick up addresses when composing a message.';
$labels['google_contacts_pluginname'] = 'Google Contacts';
$labels['google_contacts_plugindescription'] = 'Access your Google Contacts.';
$labels['contextmenu_pluginname'] = 'Context Menu';
$labels['contextmenu_plugindescription'] = 'Enable right click context menu functionality.';
$labels['newmail_notifier_pluginname'] = 'New Mail Notifier';
$labels['newmail_notifier_plugindescription'] = 'Notify on new messages.';
$labels['listcommands_pluginname'] = 'Reply Mailing Lists';
$labels['listcommands_plugindescription'] = 'Various options to reply to messages sent from mailing lists.';
$labels['copymessage_pluginname'] = 'Copy Message(s)';
$labels['copymessage_plugindescription'] = 'Copy message(s) into another IMAP folder.';
$labels['forwardattachment_pluginname'] = 'Forward as Attachment';
$labels['forwardattachment_plugindescription'] = 'Forward multiple messages as attachments.';
$labels['vcard_attachments_pluginname'] = 'vCard Attachments';
$labels['vcard_attachments_plugindescription'] = 'Add a box to messages which have a vcard attachment to import the contact directly into the addre...';
$labels['importmessages_pluginname'] = 'Import Messages';
$labels['importmessages_plugindescription'] = 'Import messages from a zip or eml source.';
$labels['zipdownload_pluginname'] = 'Export Messages';
$labels['zipdownload_plugindescription'] = 'Export messages as a zip file.';
$labels['markasjunk2_pluginname'] = 'Mark Messages as Spam';
$labels['markasjunk2_plugindescription'] = 'Mark Messages as spam or not spam.';
$labels['google_ads_pluginname'] = 'Google Ads';
$labels['google_ads_plugindescription'] = 'Display Google advertisement.';
$labels['google_analytics_pluginname'] = 'Google Analytics';
$labels['google_analytics_plugindescription'] = 'Include Google Analytics.';
$labels['fileapi_attachments_pluginname'] = 'Attachments per Drag &amp; Drop';
$labels['fileapi_attachments_plugindescription'] = 'Add attachments by dragging &amp; dropping into your compose window.';
$labels['globaladdressbook_pluginname'] = 'Global Addressbook';
$labels['globaladdressbook_plugindescription'] = 'This is a readonly addressbook provided by your administrator.';
$labels['blockspamsending_pluginname'] = 'Prevent Sending Spam';
$labels['blockspamsending_plugindescription'] = 'Allow sending out only a specific amount of messages with same body during one session.';
$labels['global_config_pluginname'] = 'Configuration Manager';
$labels['global_config_plugindescription'] = 'This is a configuration manager plugin. It optimizes your server by centralizing plugin configura...';
$labels['jqueryui_pluginname'] = 'Jqueryui';
$labels['jqueryui_plugindescription'] = 'Jqueryui javascript library';

?>