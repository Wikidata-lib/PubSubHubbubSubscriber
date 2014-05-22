<?php

namespace PubSubHubbubSubscriber;

use ApiMain;
use DerivativeRequest;
use FauxRequest;
use Language;
use MediaWikiLangTestCase;
use UsageException;

/**
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class ApiSubscriptionTest extends MediaWikiLangTestCase {

	/**
	 * @var ApiSubscription $mApi
	 */
	private $mApi;

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );

		$this->mApi = new ApiSubscription( new ApiMain(), 'pushcallback' );
	}

	/**
	 * @covers PubSubHubbubSubscriber\ApiSubscription::acceptRequest
	 * @dataProvider getAcceptRequestData
	 */
	public function testAcceptRequest( $challenge, $expectedResultData ) {
		$this->mApi->acceptRequest( $challenge );
		$result = $this->mApi->getResult();
		$data = $result->getData();
		$this->assertArrayEquals( $expectedResultData, $data, false, true );
	}

	/**
	 * @covers PubSubHubbubSubscriber\ApiSubscription::declineRequest
	 */
	public function testDeclineRequest() {
		$this->mApi->declineRequest();
		$result = $this->mApi->getResult();
		$data = $result->getData();
		$this->assertArrayEquals( array(
			'mime' => 'text/plain',
			'text' => '',
		), $data, false, true );
	}

	/**
	 * @covers PubSubHubbubSubscriber\ApiSubscription::execute
	 * @dataProvider getExecuteData
	 */
	public function testExecute( $mode, $method, $topic, $challenge, $success ) {
		// Create mocked SubscriptionHandler.
		$handler = $this->getMock( 'PubSubHubbubSubscriber\\SubscriptionHandler', array( $method ) );
		$handler->expects( $this->once() )
			->method( $method )
			->will( $this->returnValue( $success ) );

		// Create mocked API module.
		$api = $this->getMock( 'PubSubHubbubSubscriber\\ApiSubscription',
			array( 'createSubscriptionHandler' ),
			array( $this->createApiMain( $mode, $topic, $challenge, true ), 'pushcallback' ) );
		$api->expects( $this->once() )
			->method( 'createSubscriptionHandler' )
			->will( $this->returnValue( $handler ) );

		// Actual test.
		$api->execute();
		$apiResult = $api->getResultData();
		if ( $mode != 'push' && $success ) {
			$this->assertEquals( $challenge, $apiResult['text'] );
		} else {
			$this->assertEquals( "", $apiResult['text'] );
		}
	}

	/**
	 * @covers PubSubHubbubSubscriber\ApiSubscription::execute
	 * @expectedException UsageException
	 */
	public function testExecutePushFail() {
		$api = new ApiSubscription( $this->createApiMain( 'push', 'http://a.topic/', 'abc', false ), 'pushcallback' );
		$api->execute();
	}

	/**
	 * Create an ApiMain object with ApiSubscription-specific parameters.
	 *
	 * @param string $mode The mode to use. Must be one of "push", "subscribe" or "unsubscribe".
	 * @param string $topic A topic URL.
	 * @param string $challenge The challenge the API needs to response with if successful.
	 * @param bool $posted whether a POST request should be used.
	 * @return ApiMain
	 */
	public function createApiMain( $mode, $topic, $challenge, $posted ) {
		$request = new DerivativeRequest(
			new FauxRequest(),
			array(
				'action' => 'pushcallback',
				'hub_mode' => $mode,
				'hub_topic' => $topic,
				'hub_challenge' => $challenge,
			), $posted
		);
		return new ApiMain( $request );
	}

	public function getAcceptRequestData() {
		return array(
			array(
				'Challenge 1',
				array(
					'mime' => 'text/plain',
					'text' => 'Challenge 1',
				)
			),
			array(
				'This is another challenge',
				array(
					'mime' => 'text/plain',
					'text' => 'This is another challenge',
				)
			),
		);
	}

	public function getExecuteData() {
		return array(
			array(
				'subscribe',
				'handleSubscribe',
				'http://a.random.topic/',
				'a.challenge',
				true,
			),
			array(
				'subscribe',
				'handleSubscribe',
				'http://another.random.topic/',
				'another.challenge',
				true,
			),
			array(
				'subscribe',
				'handleSubscribe',
				'http://random.topic/',
				'x.challenge',
				false,
			),
			array(
				'unsubscribe',
				'handleUnsubscribe',
				'http://topic/',
				'a.challenge',
				true,
			),
			array(
				'unsubscribe',
				'handleUnsubscribe',
				'http://yet.another.topic/',
				'fake.challenge',
				true,
			),
			array(
				'unsubscribe',
				'handleUnsubscribe',
				'http://yet.another.topic/',
				'no.challenge',
				false,
			),
			array(
				'push',
				'handlePush',
				'http://a.topic/',
				'challenge.5',
				true,
			),
			array(
				'push',
				'handlePush',
				'http://another.topic/',
				'challenge.6',
				false,
			),
		);
	}

}
