<?php
/**
 * This file is part of the PubSubHubbubSubscriber Extension to MediaWiki
 * https://www.mediawiki.org/wiki/Extension:PubSubHubbubSubscriber
 *
 * @section LICENSE
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

global $wgExtensionCredits;
global $wgExtensionMessagesFiles, $wgMessagesDirs;
global $wgAutoloadClasses;
global $wgAPIModules;
global $wgHooks;

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'PubSubHubbubSubscriber',
	'author' => array( 'BP2013N2' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:PubSubHubbubSubscriber',
	'descriptionmsg' => 'pubsubhubbubsubscriber-desc',
	'version'  => 0.1,
);

$dir = __DIR__ . '/';

$wgMessagesDirs['PubSubHubbub'] = $dir . 'i18n';
$wgExtensionMessagesFiles['PubSubHubbubSubscriber'] = $dir . 'PubSubHubbubSubscriber.i18n.php';

$wgAutoloadClasses['PubSubHubbubSubscriber\\SubscriptionCallback'] = $dir . 'src/SubscriptionCallback.php';
$wgAutoloadClasses['PubSubHubbubSubscriber\\HookHandler'] = $dir . 'src/HookHandler.php';

$wgAPIModules['pushcallback'] = 'PubSubHubbubSubscriber\\SubscriptionCallback';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'PubSubHubbubSubscriber\\HookHandler::onLoadExtensionSchemaUpdates';
