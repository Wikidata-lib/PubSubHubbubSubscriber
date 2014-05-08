<?php

namespace PubSubHubbubSubscriber;

use Language;
use MediaWikiLangTestCase;

/**
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class SubscriberClientTest extends MediaWikiLangTestCase {

	/**
	 * @var HttpMockRequest $mRequest
	 */
	private $mRequest;

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

		$this->mClient = $this->getMock( 'PubSubHubbubSubscriber\\SubscriberClient', array( 'createHttpRequest' ), array( "http://random.resource/" ) );
		$this->mClient->expects( $this->any() )
			->method( 'createHttpRequest' )
			->withAnyParameters()
			->will( $this->returnCallback( array( $this, 'mockCreateHttpRequest' ) ) );
	}

	public function mockCreateHttpRequest( $method, $hubURL, $postData ) {
		$this->mRequest = new HttpMockRequest( $method, $hubURL, $postData );
		return $this->mRequest;
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::__construct
	 */
	public function testSubscriberClientConstructor() {
		$client = new SubscriberClient( "Test X" );
		$this->assertAttributeEquals( "Test X", 'mResourceURL', $client );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::createHttpRequest
	 */
	public function testCreateHeadRequest() {
		$client = new SubscriberClient( "http://a.resource/" );
		$request = $client->createHttpRequest( 'HEAD', "http://a.resource/" );

		$this->assertEquals( "http://a.resource/", $request->getFinalUrl() );
		$this->assertAttributeEquals( 'HEAD', 'method', $request );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::createHttpRequest
	 */
	public function testCreatePostRequest() {
		$client = new SubscriberClient( "http://a.resource/" );
		$request = $client->createHttpRequest( 'POST', "http://a.hub/" , array(
			'x' => "abc",
		) );
		$this->assertAttributeEquals( array(
			'x' => "abc",
		), 'postData', $request );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::findRawLinkHeaders
	 */
	public function testFindRawLinkHeaders() {
		$result = $this->mClient->findRawLinkHeaders( "http://random.resource/" );
		$this->assertArrayEquals( array(
			"<http://random.resource/actual.link>; rel=\"self\", <http://a.hub/>; rel=\"hub\"",
		), $result );
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
	 * @param string $mode Not used here.
	 * @param string $resourceURL
	 * @param string $callbackURL
	 */
	public function testCreateCallbackURL( $mode, $resourceURL, $callbackURL ) {
		$this->assertEquals( $callbackURL, SubscriberClient::createCallbackURL( $resourceURL ));
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::createPostData
	 * @dataProvider getCallbackData
	 * @param string $mode
	 * @param string $resourceURL
	 * @param string $callbackURL
	 */
	public function testCreatePostData( $mode, $resourceURL, $callbackURL ) {
		$postData = $this->mClient->createPostData( $mode, $resourceURL, $callbackURL );
		$this->assertEquals( $mode, $postData['hub.mode'] );
		$this->assertEquals( $resourceURL, $postData['hub.topic'] );
		$this->assertEquals( $callbackURL, $postData['hub.callback'] );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::sendSubscriptionRequest
	 */
	public function testSendSubscriptionRequest() {
		$this->mClient->sendSubscriptionRequest( "http://a.hub/", array() );
		$this->assertEquals( 'POST', $this->mRequest->mMethod );
		$this->assertEquals( 'http://a.hub/', $this->mRequest->mHubURL );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::subscribe
	 */
	public function testSubscribe() {
		$this->mClient->subscribe();
		$subscription = Subscription::findByTopic( "http://random.resource/actual.link" );
		$this->assertNotNull( $subscription );
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
				'subscribe',
				'http://resource/',
				'http://this.is.a.test.wiki/w/api.php?action=pushcallback&hub.mode=push&hub.topic=http%3A%2F%2Fresource%2F'
			),
			array(
				'unsubscribe',
				'http://another.resource/',
				'http://this.is.a.test.wiki/w/api.php?action=pushcallback&hub.mode=push&hub.topic=http%3A%2F%2Fanother.resource%2F'
			),
		);
	}

}

class HttpMockRequest {

	public $mMethod;
	public $mHubURL;
	public $mPostData;

	function __construct( $method, $hubURL, $postData ) {
		$this->mMethod = $method;
		$this->mHubURL = $hubURL;
		$this->mPostData = $postData;
	}

	public function execute() {
	}

	public function getResponseHeaders() {
		return array(
			"link" => array(
				"<http://random.resource/actual.link>; rel=\"self\", <http://a.hub/>; rel=\"hub\"",
			),
		);
	}

}
