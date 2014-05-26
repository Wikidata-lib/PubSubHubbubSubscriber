<?php

namespace PubSubHubbubSubscriber;

use Language;
use MediaWikiLangTestCase;

/**
 * @group Database
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class SubscriberClientTest extends MediaWikiLangTestCase {

	/**
	 * @var bool $mMockRequestSuccess
	 */
	private $mMockRequestSuccess;

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
			'wgServer' => "http://this.is.a.test.wiki",
			'wgScriptPath' => "/w",
			'wgScriptExtension' => ".php",
		) );
		$this->tablesUsed[] = 'push_subscriptions';

		$this->mMockRequestSuccess = true;
		$this->mClient = $this->getMock( 'PubSubHubbubSubscriber\\SubscriberClient',
			array( 'createHttpRequest' ), array( "http://random.resource/" ) );
		$this->mClient->expects( $this->any() )
			->method( 'createHttpRequest' )
			->withAnyParameters()
			->will( $this->returnCallback( array( $this, 'mockCreateHttpRequest' ) ) );
	}

	public function mockCreateHttpRequest( $method, $hubURL, $postData ) {
		$this->mRequest = new HttpMockRequest( $method, $hubURL, $postData, $this->mMockRequestSuccess );
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
	 * @covers PubSubHubbubSubscriber\SubscriberClient::retrieveLinkHeaders
	 */
	public function testRetrieveLinkHeadersSuccessful() {
		$this->mClient->retrieveLinkHeaders();
		$this->assertAttributeEquals( 'http://a.hub/', 'mHubURL', $this->mClient );
		$this->assertAttributeEquals( 'http://random.resource/actual.link', 'mResourceURL', $this->mClient );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::retrieveLinkHeaders
	 * @expectedException \PubSubHubbubSubscriber\PubSubHubbubException
	 */
	public function testRetrieveLinkHeadersUnsuccessful() {
		$this->mMockRequestSuccess = false;
		$this->mClient->retrieveLinkHeaders();
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
	 * @covers PubSubHubbubSubscriber\SubscriberClient::findRawLinkHeaders
	 */
	public function testFindRawLinkHeadersUnsuccessful() {
		$this->mMockRequestSuccess = false;
		$result = $this->mClient->findRawLinkHeaders( "http://random.resource/" );
		$this->assertArrayEquals( array(), $result );
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
	 * @dataProvider getData
	 * @param string $mode Not used here.
	 * @param string $hub Not used here.
	 * @param string $resourceURL
	 * @param string $callbackURL
	 * @param string $secret Not used here.
	 */
	public function testCreateCallbackURL( $mode, $hub, $resourceURL, $callbackURL, $secret ) {
		$this->assertEquals( $callbackURL, SubscriberClient::createCallbackURL( $resourceURL ));
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::createPostData
	 * @dataProvider getData
	 * @param string $mode
	 * @param string $hub Not used here.
	 * @param string $resourceURL
	 * @param string $callbackURL
	 * @param string $secret
	 */
	public function testCreatePostData( $mode, $hub, $resourceURL, $callbackURL, $secret ) {
		$postData = $this->mClient->createPostData( $mode, $resourceURL, $callbackURL, $secret );
		$this->assertEquals( $mode, $postData['hub.mode'] );
		$this->assertEquals( $resourceURL, $postData['hub.topic'] );
		$this->assertEquals( $callbackURL, $postData['hub.callback'] );
		if ( $secret ) {
			$this->assertEquals( bin2hex( $secret ), $postData['hub.secret'] );
		} else {
			$this->assertArrayNotHasKey( 'hub.secret', $postData );
		}
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::sendRequest
	 * @dataProvider getData
	 * @param string $mode
	 * @param string $hub
	 * @param string $resourceURL Not used here.
	 * @param string $callbackURL Not used here.
	 * @param string $secret Not used here.
	 */
	public function testSendRequest( $mode, $hub, $resourceURL, $callbackURL, $secret ) {
		$this->mClient->sendRequest( $mode, $hub, array() );
		$this->assertEquals( 'POST', $this->mRequest->mMethod );
		$this->assertEquals( $hub, $this->mRequest->mHubURL );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::subscribe
	 */
	public function testSubscribe() {
		$this->mClient->subscribe();
		$subscription = Subscription::findByTopic( "http://random.resource/actual.link" );
		$this->assertNotNull( $subscription );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::unsubscribe
	 */
	public function testUnsubscribe() {
		$resourceURL = "http://random.resource/actual.link";

		// Create Subscription.
		$subscription = new Subscription( NULL, $resourceURL, 'secret', NULL, true, false );
		$subscription->update();

		// Unsubscribe it.
		$this->mClient->unsubscribe();

		// Check if it's marked for unsubscription.
		$subscription = Subscription::findByTopic( $resourceURL );
		$this->assertNotNull( $subscription );
		$this->assertTrue( $subscription->isUnsubscribed() );
	}

	/**
	 * @covers PubSubHubbubSubscriber\SubscriberClient::unsubscribe
	 * @expectedException \PubSubHubbubSubscriber\PubSubHubbubException
	 */
	public function testUnsubscribeWithoutSubscription() {
		$this->mClient->unsubscribe();
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

	public function getData() {
		return array(
			array(
				'subscribe',
				'http://a.hub/',
				'http://resource/',
				'http://this.is.a.test.wiki/w/api.php?action=pushcallback&hub.mode=push&hub.topic=http%3A%2F%2Fresource%2F',
				'ThisSecretMustHaveExactly32Bytes'
			),
			array(
				'unsubscribe',
				'http://a.different.hub/',
				'http://another.resource/',
				'http://this.is.a.test.wiki/w/api.php?action=pushcallback&hub.mode=push&hub.topic=http%3A%2F%2Fanother.resource%2F',
				NULL
			),
		);
	}

}

class HttpMockRequest {

	public $mMethod;
	public $mHubURL;
	public $mPostData;
	private $mSuccess;

	function __construct( $method, $hubURL, $postData, $success ) {
		$this->mMethod = $method;
		$this->mHubURL = $hubURL;
		$this->mPostData = $postData;
		$this->mSuccess = $success;
	}

	public function execute() {
	}

	public function getResponseHeaders() {
		if ( $this->mSuccess ) {
			return array(
				"link" => array(
					"<http://random.resource/actual.link>; rel=\"self\", <http://a.hub/>; rel=\"hub\"",
				),
			);
		} else {
			return array();
		}
	}

}
