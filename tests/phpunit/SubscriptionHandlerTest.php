<?php

namespace PubSubHubbubSubscriber;

use ApiMain;
use Language;
use MediaWikiLangTestCase;

/**
 * @covers PubSubHubbubSubscriber\SubscriptionHandler
 *
 * @group Database
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class SubscriptionHandlerTest extends MediaWikiLangTestCase {

	/**
	 * @var SubscriptionHandler $mHandler;
	 */
	private $mHandler;

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );
		$this->tablesUsed[] = 'push_subscriptions';

		$this->mHandler = new SubscriptionHandler();
	}

	public function testHandleSubscribeNonExistent() {
		$success = $this->mHandler->handleSubscribe( "http://some.nonexistent.topic/" );
		$this->assertFalse( $success );
	}

	public function testHandleSubscribeAlreadyConfirmed() {
		$subscription = new Subscription( NULL, "http://topic/", NULL, true, false );
		$subscription->update();

		$success = $this->mHandler->handleSubscribe( "http://topic/" );
		$this->assertFalse( $success );
		$subscription = Subscription::findByTopic( "http://topic/" );
		$this->assertTrue( $subscription->isConfirmed() );
	}

	public function testHandleSubscribeSuccessful() {
		$subscription = new Subscription( NULL, "http://another.topic/", NULL, false, false );
		$subscription->update();

		$success = $this->mHandler->handleSubscribe( "http://another.topic/" );
		$this->assertTrue( $success );
		$subscription = Subscription::findByTopic( "http://another.topic/" );
		$this->assertTrue( $subscription->isConfirmed() );
	}

	public function testHandleUnsubscribeNonExistent() {
		$success = $this->mHandler->handleUnsubscribe( "http://some.nonexistent.topic/" );
		$this->assertFalse( $success );
	}

	public function testHandleUnsubscribeNotMarkedForUnsubscription() {
		$subscription = new Subscription( NULL, "http://topic/", NULL, true, false );
		$subscription->update();

		$success = $this->mHandler->handleUnsubscribe( "http://topic/" );
		$this->assertFalse( $success );
		$subscription = Subscription::findByTopic( "http://topic/" );
		$this->assertFalse( $subscription->isUnsubscribed() );
	}

	public function testHandleUnsubscribeSuccessful() {
		$subscription = new Subscription( NULL, "http://another.topic/", NULL, true, true );
		$subscription->update();

		$success = $this->mHandler->handleUnsubscribe( "http://another.topic/" );
		$this->assertTrue( $success );
		$subscription = Subscription::findByTopic( "http://another.topic/" );
		$this->assertNull( $subscription );
	}

}
