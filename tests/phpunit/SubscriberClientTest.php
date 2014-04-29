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

	/**
	 * @var SubscriberClient $mClient
	 */
	private $mClient;

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
			'wgServer' => "http://this.is.a.test.wiki",
			'wgScriptPath' => "/w",
			'wgScriptExtension' => ".php",
		) );


		$this->mClient = $this->getMock( 'PubSubHubbubSubscriber\\SubscriberClient', array( 'createHeadRequest' ), array( "testResource" ) );
		$this->mClient->expects( $this->any() )
			->method( 'createHeadRequest' )
			->withAnyParameters()
			->will( $this->returnValue( new HttpMockRequest() ) );
	}

	public function testSubscriberClientConstructor() {
		$client = new SubscriberClient( "Test X" );
		$this->assertAttributeEquals( "Test X", 'mResourceURL', $client );
	}

	public function testCreateHeadRequest() {
		$client = new SubscriberClient( "http://a.resource/" );
		$request = $client->createHeadRequest( "http://a.resource/" );

		$this->assertEquals( "http://a.resource/", $request->getFinalUrl() );
		$this->assertAttributeEquals( 'HEAD', 'method', $request );
	}

	public function testFindRawLinkHeaders() {
		$result = $this->mClient->findRawLinkHeaders( "http://random.resource/" );
		$this->assertEquals( "http://random.resource/actual.link", $result );
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

	/**
	 * @dataProvider getCallbackData
	 * @param string $resourceURL
	 * @param string $callbackURL
	 */
	public function testCreateCallbackURL( $resourceURL, $callbackURL ) {
		$this->assertEquals( $callbackURL, SubscriberClient::createCallbackURL( $resourceURL ));
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
			),
		);
	}

	public function getCallbackData() {
		return array(
			array(
				'http://resource/',
				'http://this.is.a.test.wiki/w/api.php?action=pushcallback&hub.mode=push&hub.topic=http%3A%2F%2Fresource%2F'
			),
			array(
				'http://another.resource/',
				'http://this.is.a.test.wiki/w/api.php?action=pushcallback&hub.mode=push&hub.topic=http%3A%2F%2Fanother.resource%2F'
			),
		);
	}

}

class HttpMockRequest {

	public function execute() {
	}

	public function getResponseHeaders() {
		return array( 'link' => "http://random.resource/actual.link" );
	}

}
