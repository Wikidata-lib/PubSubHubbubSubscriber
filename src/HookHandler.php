<?php

namespace PubSubHubbubSubscriber;

use DatabaseUpdater;

class HookHandler {

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$sqlPath = __DIR__ . "/../sql/";
		$updater->addExtensionTable( 'push_subscriptions',
			$sqlPath . "create_pushsubscriptions.sql" );
		$updater->addExtensionIndex( 'push_subscriptions', 'psb_topic',
			$sqlPath . "create_pushsubscriptions_index_topic.sql" );
		return true;
	}

}
