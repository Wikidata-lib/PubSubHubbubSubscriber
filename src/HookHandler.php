<?php

namespace PubSubHubbubSubscriber;

use DatabaseUpdater;
use XMLReader;

class HookHandler {

	/**
	 * @codeCoverageIgnore
	 * @param DatabaseUpdater $updater
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
	 * Add our tests to the list of PHPUnit test files.
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

	/**
	 * Called when parsing a top level XML tag.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ImportHandleToplevelXMLTag
	 *
	 * @param XMLReader $reader
	 * @return bool
	 */
	public static function onImportHandleToplevelXMLTag( XMLReader $reader ) {
		$tag = $reader->name;
		if ( $tag != 'deletion' ) {
			return true;
		}
		$importer = new DeletionXMLImporter( $reader );
		$importer->doImport();
		return false;
	}

}
