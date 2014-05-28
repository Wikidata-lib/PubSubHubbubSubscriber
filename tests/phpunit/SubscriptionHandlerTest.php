<?php

namespace PubSubHubbubSubscriber;

use ContentHandler;
use Language;
use MediaWikiLangTestCase;
use Revision;
use Title;
use User;
use WikiPage;
use WikiRevision;

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
		global $wgContLang, $wgLanguageCode;
		$wgContLang = Language::factory( 'en' );
		$wgLanguageCode = 'en';
		$this->tablesUsed[] = 'push_subscriptions';

		$this->mHandler = new SubscriptionHandler();
	}

	public function testHandlePushFail() {
		$success = $this->mHandler->handlePush( "" );
		$this->assertFalse( $success );
	}

	/**
	 * @dataProvider getXMLPushRedirectDataSuccessful
	 * @param string $xml The XML dump to import.
	 */
	public function testHandlePushRedirectSuccessful( $xml ) {
		$originalPage = $this->redirectSetup( $xml );
		$redirectTitle = Title::newFromText( 'Redirect TestPage' );
		$redirectPage = WikiPage::factory( $redirectTitle );

		$this->assertTrue( $redirectPage->exists() );

		$text = $redirectPage->getContent()->getNativeData();
		$this->assertEquals( "TestPage content", $text );

		$text =$originalPage->getContent()->getNativeData();
		$this->assertEquals( "#REDIRECT [[Redirect TestPage]]", $text );
	}

	/**
	 * @dataProvider getXMLPushRedirectDataExists
	 * @param string $xml The XML dump to import.
	 */
	public function testHandlePushRedirectExists( $xml ) {
		$originalPage = $this->redirectSetup( $xml );
		$redirectTitle = Title::newFromText( 'Test Page' );
		$redirectPage = WikiPage::factory( $redirectTitle );

		$text = $redirectPage->getContent()->getNativeData();
		$this->assertEquals( "Test Page content", $text );

		$text =$originalPage->getContent()->getNativeData();
		$this->assertEquals( "TestPage content version 2", $text );
	}

	/**
	 * @param $xml
	 * @return WikiPage
	 */
	public function redirectSetup( $xml ) {
		$this->addData();
		sleep( 1 );
		$xml = preg_replace('~<timestamp>.*?</timestamp>~', '<timestamp>' . wfTimestamp( TS_ISO_8601, wfTimestampNow() )
			. '</timestamp>', $xml );

		$file = 'data:application/xml,' . $xml;
		$this->mHandler->handlePush( $file );

		$originalTitle = Title::newFromText( 'TestPage' );
		return WikiPage::factory( $originalTitle );
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

	/**
	 * @dataProvider getDeletionRevision
	 */
	public function testDeletionPage( WikiRevision $revision ) {
		$this->addData();
		$wikiPage = new WikiPage( $revision->getTitle() );
		$this->mHandler->deletionPage( $revision );
		$this->assertFalse( $wikiPage->exists() );
	}

	/**
	 * @dataProvider getNoDeletionRevision
	 */
	public function testDeletionPageNoDelete( WikiRevision $revision ) {
		$this->addData();
		$wikiPage = new WikiPage( $revision->getTitle() );
		$this->mHandler->deletionPage( $revision );
		$this->assertTrue( $wikiPage->exists() );
	}

	/**
	 * @dataProvider getXMLPushDeletionData
	 * @param string $xml The XML dump to import.
	 */
	public function testHandlePushDeletion( $xml ) {
		$this->addData();
		$file = 'data:application/xml,' . $xml;
		$this->mHandler->handlePush( $file );

		$title = Title::newFromText( 'TestPage' );
		$wikiPage = new WikiPage( $title );
		$this->assertfalse( $wikiPage->exists() );
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

	public function addData() {
		$this->insertUser( 'TestUser' );
		$this->insertWikipage( 'TestPage', 'TestPage content', 'TestPage comment' );
		$this->insertWikipage( 'Test Page', 'Test Page content', 'Test Page comment' );
	}

	/**
	 * @param string $userName
	 */
	public function insertUser( $userName ) {
		$user = User::newFromName( $userName );
		$user->addToDatabase();
	}

	/**
	 * @param string $titleName
	 * @param string $contentText
	 * @param string $comment
	 */
	public function insertWikipage( $titleName, $contentText, $comment ) {
		$page = WikiPage::factory( Title::newFromText( $titleName ) );

		if ( !$page->exists() ) {
			$pageId = $page->insertOn( $this->db );
		} else {
			$pageId = $page->getId();
		}

		$user = User::newFromName( 'TestUser' );
		$revision = new Revision( array(
			'title' => $page->getTitle(),
			'page' => $pageId,
			'content_model' => $page->getTitle()->getContentModel(),
			'content_format' =>  $this->getFormat( $page->getTitle() ),
			'text' => $contentText,
			'comment' => $comment,
			'user' => $user->getId(),
			'user_text' => $user->getName(),
			'timestamp' => wfTimestamp( TS_ISO_8601 ),
			'minor_edit' => false,
		) );
		$revision->insertOn( $this->db );
		$changed = $page->updateIfNewerOn( $this->db, $revision );

		if ( $changed !== false ) {
			$page->doEditUpdates( $revision, $user );
		}
	}

	/**
	 * @param Title $title
	 * @return string
	 */
	private function getFormat( Title $title ) {
		return ContentHandler::getForTitle( $title )->getDefaultFormat();
	}

	public function  getDeletionRevision() {
		$data = array();

		$revision = $this->getBaseRevision();
		$revision->setType( 'delete' );
		$revision->setAction( 'delete' );
		$revision->setUserName( 'TestUser' );
		array_push( $data, array( $revision ) );

		$revision = $this->getBaseRevision();
		$revision->setType( 'delete' );
		$revision->setAction( 'delete' );
		array_push( $data, array( $revision ) );

		return $data;
	}

	public function  getNoDeletionRevision() {
		$revision = $this->getBaseRevision();
		$revision->setType( 'move' );
		$revision->setAction( 'move' );
		$revision->setUserName( 'TestUser' );
		return array( array( $revision ) );
	}

	public function getBaseRevision() {
		$revision = new WikiRevision;

		$revision->setID( 1 );
		$revision->setTimestamp( wfTimestampNow() );
		$revision->setParams( 'a:0:{}' );
		$revision->setTitle( Title::newFromText( 'TestPage' ) );
		$revision->setNoUpdates( true );

		return $revision;
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

	public function getXMLPushDeletionData() {
		return array(
			array(
				<<< EOF
<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.8/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.8/ http://www.mediawiki.org/xml/export-0.8.xsd" version="0.8" xml:lang="en">
	<logitem>
		<id>70</id>
		<timestamp>2014-05-20T09:49:33Z</timestamp>
		<contributor>
			<username>TestUser</username>
			<id>1</id>
		</contributor>
		<comment>content was: &quot;test&quot; (and the only contributor was &quot;[[Special:Contributions/Root|Root]]&quot;)</comment>
		<type>delete</type>
		<action>delete</action>
		<logtitle>TestPage</logtitle>
		<params xml:space="preserve">a:0:{}</params>
	</logitem>
</mediawiki>
EOF
			)
		);
	}

	public function getXMLPushRedirectDataSuccessful() {
		return array(
			array( <<< EOF
<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.8/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.8/ http://www.mediawiki.org/xml/export-0.8.xsd" version="0.8" xml:lang="en">
	<page>
		<title>TestPage</title>
		<ns>0</ns>
		<id>5</id>
		<redirect title="Redirect TestPage"/>
		<revision>
			<id>100</id>
			<parentid>99</parentid>
			<timestamp></timestamp>
			<contributor>
				<ip>127.0.0.1</ip>
			</contributor>
			<text xml:space="preserve" bytes="31">#REDIRECT [[TestPage]]</text>
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

	public function getXMLPushRedirectDataExists() {
		return array(
			array( <<< EOF
<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.8/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.mediawiki.org/xml/export-0.8/ http://www.mediawiki.org/xml/export-0.8.xsd" version="0.8" xml:lang="en">
	<page>
		<title>TestPage</title>
		<ns>0</ns>
		<id>5</id>
		<redirect title="Test Page"/>
		<revision>
			<id>100</id>
			<parentid>99</parentid>
			<timestamp></timestamp>
			<contributor>
				<ip>127.0.0.1</ip>
			</contributor>
			<text xml:space="preserve" bytes="31">TestPage content version 2</text>
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
