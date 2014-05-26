<?php

namespace PubSubHubbubSubscriber;

use Language;
use MediaWikiLangTestCase;
use Revision;
use Title;
use WikiPage;

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
		$this->tablesUsed[] = 'push_subscriptions';

		$this->mHandler = new SubscriptionHandler();
	}

	public function testHandlePushFailNoSubscription() {
		$success = $this->mHandler->handlePush( 'http://a.non-subscribed.topic/', 'IrrelevantHMACSignature' );
		$this->assertFalse( $success );
	}

	public function testHandlePushFailSubscriptionNotConfirmed() {
		$subscription = new Subscription( NULL, 'http://some.topic/', 'secret' );
		$subscription->update();

		$success = $this->mHandler->handlePush( 'http://a.non-confirmed.topic/', 'IrrelevantHMACSignature' );
		$this->assertFalse( $success );
	}

	public function testHandlePushFailBadSource() {
		$subscription = new Subscription( NULL, 'http://a.bad.topic/', 'secret', NULL, true );
		$subscription->update();

		$success = $this->mHandler->handlePush( 'http://a.bad.topic/', 'IrrelevantHMACSignature', '' );
		$this->assertFalse( $success );
	}

	/**
	 * @dataProvider getXMLPushData
	 * @param string $xml The XML dump to import.
	 */
	public function testHandlePushFailBadSignature( $xml ) {
		$secret = base64_decode( 'mcl2BC9+zc619Pxq0qTRhQOBaJFBQcQ5I+PhqA1t5X8=' );
		$subscription = new Subscription( NULL, 'http://a.useful.topic/', $secret, NULL, true );
		$subscription->update();

		$file = 'data:application/xml,' . $xml;
		$success = $this->mHandler->handlePush( 'http://a.useful.topic/',
			'sha1=3da541559918a808c2402bba5012f6c60b27661c', $file );
		// Assert success - see specification.
		$this->assertTrue( $success );

		// Assert page still does not exist.
		$title = Title::newFromText( 'Unit Test Page' );
		$page = WikiPage::factory( $title );
		$revision = Revision::newFromPageId( $page->getId() );
		$this->assertNull( $revision );
	}

	/**
	 * @dataProvider getXMLPushData
	 * @param string $xml The XML dump to import.
	 */
	public function testHandlePushSuccessful( $xml ) {
		$secret = base64_decode( 'kN9SimfZVUmtXegAXLGmWdvvbX9n+a5c1Wu+bHekHRE=' );
		$subscription = new Subscription( NULL, 'http://a.useful.topic/', $secret, NULL, true );
		$subscription->update();

		$file = 'data:application/xml,' . $xml;
		$success = $this->mHandler->handlePush( 'http://a.useful.topic/',
			'sha1=a0a85efd850dfd33bff2dc5d02afb3b066a8c125', $file );
		$this->assertTrue( $success );

		$title = Title::newFromText( 'Unit Test Page' );
		$page = WikiPage::factory( $title );
		$revision = Revision::newFromPageId( $page->getId() );
		$this->assertNotNull( $revision );
		$this->assertEquals( "lg0sq0pjm7cngi77vxtmmeko4o7pho6", $revision->getSha1() );
		$text = $revision->getContent()->getNativeData();
		$this->assertEquals( "This is a Test Page.", $text );
	}

	public function testHandleSubscribeNonExistent() {
		$success = $this->mHandler->handleSubscribe( "http://some.nonexistent.topic/", 1337 );
		$this->assertFalse( $success );
	}

	public function testHandleSubscribeAlreadyConfirmed() {
		$subscription = new Subscription( NULL, "http://topic/", 'secret', NULL, true, false );
		$subscription->update();

		$success = $this->mHandler->handleSubscribe( "http://topic/", 42 );
		$this->assertFalse( $success );
		$subscription = Subscription::findByTopic( "http://topic/" );
		$this->assertTrue( $subscription->isConfirmed() );
	}

	public function testHandleSubscribeSuccessful() {
		$subscription = new Subscription( NULL, "http://another.topic/", 'secret', NULL, false, false );
		$subscription->update();

		$success = $this->mHandler->handleSubscribe( "http://another.topic/", 21 );
		$this->assertTrue( $success );
		$subscription = Subscription::findByTopic( "http://another.topic/" );
		$this->assertTrue( $subscription->isConfirmed() );
		$this->assertEquals( $_SERVER['REQUEST_TIME'] + 21, $subscription->getExpires()->getTimestamp( TS_UNIX ) );
	}

	public function testHandleUnsubscribeNonExistent() {
		$success = $this->mHandler->handleUnsubscribe( "http://some.nonexistent.topic/" );
		$this->assertFalse( $success );
	}

	public function testHandleUnsubscribeNotMarkedForUnsubscription() {
		$subscription = new Subscription( NULL, "http://topic/", 'secret', NULL, true, false );
		$subscription->update();

		$success = $this->mHandler->handleUnsubscribe( "http://topic/" );
		$this->assertFalse( $success );
		$subscription = Subscription::findByTopic( "http://topic/" );
		$this->assertFalse( $subscription->isUnsubscribed() );
	}

	public function testHandleUnsubscribeSuccessful() {
		$subscription = new Subscription( NULL, "http://another.topic/", 'secret', NULL, true, true );
		$subscription->update();

		$success = $this->mHandler->handleUnsubscribe( "http://another.topic/" );
		$this->assertTrue( $success );
		$subscription = Subscription::findByTopic( "http://another.topic/" );
		$this->assertNull( $subscription );
	}

	public function getXMLPushData() {
		return array(
			array(
				str_replace( "\r", "", <<< EOF
<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.8/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.8/ http://www.mediawiki.org/xml/export-0.8.xsd" version="0.8" xml:lang="en">
	<page>
		<title>Unit Test Page</title>
		<ns>0</ns>
		<id>5</id>
		<revision>
			<id>100</id>
			<parentid>99</parentid>
			<timestamp>2014-04-24T13:37:42Z</timestamp>
			<contributor>
				<ip>127.0.0.1</ip>
			</contributor>
			<text xml:space="preserve" bytes="20">This is a Test Page.</text>
			<sha1>lg0sq0pjm7cngi77vxtmmeko4o7pho6</sha1>
			<model>wikitext</model>
			<format>text/x-wiki</format>
		</revision>
	</page>
</mediawiki>
EOF
				)
			)
		);
	}

}
