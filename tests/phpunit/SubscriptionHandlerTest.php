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
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );
		$this->tablesUsed[] = 'push_subscriptions';

		$this->mHandler = new SubscriptionHandler();
	}

	public function testHandlePushFail() {
		$success = $this->mHandler->handlePush( "" );
		$this->assertFalse( $success );
	}

	/**
	 * @dataProvider getXMLPushData
	 * @param string $xml The XML dump to import.
	 */
	public function testHandlePushSuccessful( $xml ) {
		$file = 'data:application/xml,' . $xml;
		$this->mHandler->handlePush( $file );

		$title = Title::newFromText( 'Unit Test Page' );
		$page = WikiPage::factory( $title );
		$revision = Revision::newFromPageId( $page->getId() );
		$this->assertEquals( "lg0sq0pjm7cngi77vxtmmeko4o7pho6", $revision->getSha1() );
		$text = $revision->getContent()->getNativeData();
		$this->assertEquals( "This is a Test Page.", $text );
	}

	public function testHandleSubscribeNonExistent() {
		$success = $this->mHandler->handleSubscribe( "http://some.nonexistent.topic/", 1337 );
		$this->assertFalse( $success );
	}

	public function testHandleSubscribeAlreadyConfirmed() {
		$subscription = new Subscription( NULL, "http://topic/", NULL, true, false );
		$subscription->update();

		$success = $this->mHandler->handleSubscribe( "http://topic/", 42 );
		$this->assertFalse( $success );
		$subscription = Subscription::findByTopic( "http://topic/" );
		$this->assertTrue( $subscription->isConfirmed() );
	}

	public function testHandleSubscribeSuccessful() {
		$subscription = new Subscription( NULL, "http://another.topic/", NULL, false, false );
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

	public function getXMLPushData() {
		return array(
			array(
				<<< EOF
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
		);
	}

}
