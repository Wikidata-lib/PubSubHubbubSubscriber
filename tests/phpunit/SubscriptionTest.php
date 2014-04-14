<?php

namespace PubSubHubbubSubscriber;

use Language;
use MediaWikiLangTestCase;
use ReflectionClass;

/**
 * @covers PubSubHubbubSubscriber\Subscription
 *
 * @group Database
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class SubscriptionTest extends MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );
	}

	public function testSubscriptionTableEmpty() {
		$this->assertSelect( 'push_subscriptions', 'psb_id', '', array() );
	}

	/**
	 * @dataProvider getInitialSubscriptionData
	 * @depends testSubscriptionTableEmpty
	 *
	 * @param mixed[] $objectData
	 * @param mixed[] $expectedData
	 */
	public function testSubscriptionInsert( $objectData, $expectedData ) {
		$reflection = new ReflectionClass( 'PubSubHubbubSubscriber\\Subscription' );
		$subscription = $reflection->newInstanceArgs( $objectData );
		$subscription->update();
		$this->assertSelect( 'push_subscriptions',
			array( 'psb_id', 'psb_topic', 'psb_expires', 'psb_confirmed', 'psb_unsubscribe' ), '',
			$expectedData );
	}

	/**
	 * @dataProvider getUpdatedSubscriptionData
	 * @depends testSubscriptionInsert
	 */
	public function testSubscriptionUpdate( $id, $confirmed, $expectedData ) {
		$subscription = Subscription::findById( $id );
		$subscription->setConfirmed( $confirmed );
		$subscription->update();
		$this->assertSelect( 'push_subscriptions',
			array( 'psb_id', 'psb_topic', 'psb_expires', 'psb_confirmed', 'psb_unsubscribe' ), '',
			$expectedData );
	}

	/**
	 * @depends testSubscriptionUpdate
	 */
	public function testSubscriptionFindNothingByID() {
		$subscription = Subscription::findByID( 100 );
		$this->assertNull( $subscription );
	}

	/**
	 * @depends testSubscriptionUpdate
	 */
	public function testSubscriptionFindNothingByTopic() {
		$subscription = Subscription::findByTopic( "topic5" );
		$this->assertNull( $subscription );
	}

	/**
	 * @depends testSubscriptionUpdate
	 */
	public function testSubscriptionFindByTopic() {
		$subscription = Subscription::findByTopic( "topic2" );
		$this->assertNotNull( $subscription );
		$this->assertEquals( "topic2", $subscription->getTopic() );
	}

	/**
	 * @dataProvider getDeletedSubscriptionData
	 * @depends testSubscriptionUpdate
	 */
	public function testSubscriptionDelete( $id, $expectedData ) {
		$subscription = Subscription::findById( $id );
		$subscription->delete();
		$this->assertSelect( 'push_subscriptions', array( 'psb_id' ), '', $expectedData );
	}

	public function getInitialSubscriptionData() {
		return array(
			array(
				array( NULL, 'topic1', NULL, true, true ),
				array( array( '1', 'topic1', NULL, '1', '1' ) ),
			),
			array(
				array( NULL, 'topic2', NULL, false, true ),
				array(
					array( '1', 'topic1', NULL, '1', '1' ),
					array( '2', 'topic2', NULL, '0', '1' ),
				),
			),
		);
	}

	public function getUpdatedSubscriptionData() {
		return array(
			array(
				1,
				false,
				array(
					array( '1', 'topic1', NULL, '0', '1' ),
					array( '2', 'topic2', NULL, '0', '1' ),
				),
			),
			array(
				2,
				true,
				array(
					array( '1', 'topic1', NULL, '0', '1' ),
					array( '2', 'topic2', NULL, '1', '1' ),
				),
			),
		);
	}

	public function getDeletedSubscriptionData() {
		return array(
			array(
				1, array(
					array( '2' ),
				),
			),
			array( 2, array() ),
		);
	}

}
