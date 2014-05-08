<?php


namespace PubSubHubbubSubscriber;


use Language;
use MediaWikiLangTestCase;

class DeletionXMLImporterTest extends MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );
	}

	protected function tearDown() {
		parent::tearDown();
	}

	protected function newDeletionXMLImporter() {
		return new
	}

}
 