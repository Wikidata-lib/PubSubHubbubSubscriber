<?php

namespace PubSubHubbubSubscriber;

use DatabaseUpdater;

class HookHandler {

	/**
	 * Called when MediaWiki is updated to allow extensions to update the database.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @codeCoverageIgnore
	 * @param DatabaseUpdater $updater The updater to use.
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$sqlPath = __DIR__ . "/../sql/";
		$updater->addExtensionTable( 'push_subscriptions',
			$sqlPath . "create_pushsubscriptions.sql" );
		$updater->addExtensionIndex( 'push_subscriptions', 'psb_topic',
			$sqlPath . "create_pushsubscriptions_index_topic.sql" );
		return true;
	}

	/**
	 * Called when building a list of files with PHPUnit tests.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @codeCoverageIgnore
	 * @param string[] $files The list of test files.
	 * @return bool
	 */
	public static function onUnitTestsList( &$files ) {
		$files = array_merge( $files, glob( __DIR__ . '/../tests/phpunit/*Test.php' ) );
		return true;
	}

}
