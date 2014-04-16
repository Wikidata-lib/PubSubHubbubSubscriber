<?php

namespace PubSubHubbubSubscriber;

use Language;
use MediaWikiLangTestCase;

/**
 * @covers PubSubHubbubSubscriber\SubscriberClient
 *
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class SubscriberClientTest extends MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );
	}

	/**
	 * @dataProvider getLinkHeaders
	 * @param string[] $rawLinkHeaders
	 * @param string[] $expectedParsedLinkHeaders
	 */
	public function testParseLinkHeaders( $rawLinkHeaders, $expectedParsedLinkHeaders ) {
		$parsedLinkHeaders = SubscriberClient::parseLinkHeaders( $rawLinkHeaders );
		$this->assertArrayEquals( $expectedParsedLinkHeaders, $parsedLinkHeaders, false, true );
	}

	public function getLinkHeaders() {
		return array(
			array(
				array(
					'<http://url1/>; rel="abc"'
				),
				array(
					'abc' => "http://url1/"
				)
			),
			array(
				array(
					'<http://leet/>; rel="42", <http://fun/>; rel="21"'
				),
				array(
					'42' => "http://leet/",
					'21' => "http://fun/"
				)
			),
			array(
				array(
					'<http://abc/>; rel="1337", <http://test.wikipedia.org/>; rel="wiki"',
					'<http://eden/>; rel="apple"'
				),
				array(
					'1337' => "http://abc/",
					'wiki' => "http://test.wikipedia.org/",
					'apple' => "http://eden/"
				)
			)
		);
	}

}
