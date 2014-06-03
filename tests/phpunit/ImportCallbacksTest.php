<?php


namespace PubSubHubbubSubscriber;


use ContentHandler;
use MediaWikiLangTestCase;
use Revision;
use Title;
use User;
use WikiPage;
use WikiRevision;
use WikitextContent;

/**
 * @covers PubSubHubbubSubscriber\ImportCallbacks
 *
 * @group Database
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Alexander Lehmann < alexander.lehmann@student.hpi.uni-potsdam.de >
 */
class ImportCallbacksTest extends MediaWikiLangTestCase {
	/**
	 * @var ImportCallbacks $mImportCallbacks;
	 */
	private $mImportCallbacks;

	protected function setUp() {
		parent::setUp();
		$this->mImportCallbacks = new ImportCallbacks();
		$this->addDBData();
	}

	protected function tearDown() {
		parent::tearDown();
	}

	/**
	 * @dataProvider getDeletionRevision
	 */
	public function testDeletionPage( WikiRevision $revision ) {
		$wikiPage = new WikiPage( $revision->getTitle() );
		$this->mImportCallbacks->deletionPage( $revision );
		$this->assertFalse( $wikiPage->exists() );
	}

	/**
	 * @dataProvider getNoDeletionRevision
	 */
	public function testDeletionPageNoDelete( WikiRevision $revision ) {
		$wikiPage = new WikiPage( $revision->getTitle() );
		$this->mImportCallbacks->deletionPage( $revision );
		$this->assertTrue( $wikiPage->exists() );
	}

	/**
	 * @dataProvider getNoRedirectData
	 *
	 * @param Title $title
	 * @param $origTitle
	 * @param $revCount
	 * @param $sucCount
	 * @param $pageInfo
	 */
	public function testCreateRedirectNoRedirect( Title $title, $origTitle, $revCount, $sucCount, $pageInfo ) {
		$this->mImportCallbacks->createRedirect( $title, $origTitle, $revCount, $sucCount, $pageInfo );

		$originalTitle = Title::newFromText( 'TestPage' );
		$originalPage = WikiPage::factory( $originalTitle );
		$redirectTitle = Title::newFromText( 'Redirect TestPage' );
		$redirectPage = WikiPage::factory( $redirectTitle );

		$this->assertFalse( $redirectPage->exists() );

		$text = $originalPage->getContent()->getNativeData();
		$this->assertEquals( "TestPage content", $text );
	}

	/**
	 *  @dataProvider getRedirectExistsData
	 *
	 * @param Title $title
	 * @param $origTitle
	 * @param $revCount
	 * @param $sucCount
	 * @param $pageInfo
	 */
	public function testCreateRedirectExists( Title $title, $origTitle, $revCount, $sucCount, $pageInfo ) {
		$this->insertWikipage( 'Existing Page', 'Existing Page content', 'Existing Page comment' );
		$this->mImportCallbacks->createRedirect( $title, $origTitle, $revCount, $sucCount, $pageInfo );

		$originalTitle = Title::newFromText( 'TestPage' );
		$originalPage = WikiPage::factory( $originalTitle );
		$redirectTitle = Title::newFromText( 'Existing Page' );
		$redirectPage = WikiPage::factory( $redirectTitle );

		$text = $redirectPage->getContent()->getNativeData();
		$this->assertEquals( "Existing Page content", $text );

		$text =$originalPage->getContent()->getNativeData();
		$this->assertEquals( "TestPage content", $text );
	}

	/**
	 *  @dataProvider getRedirectSuccessful
	 *
	 * @param Title $title
	 * @param $origTitle
	 * @param $revCount
	 * @param $sucCount
	 * @param $pageInfo
	 */
	public function testCreateRedirectSuccessful(  Title $title, $origTitle, $revCount, $sucCount, $pageInfo ) {
		$originalTitle = Title::newFromText( 'TestPage' );
		$originalPage = WikiPage::factory( $originalTitle );
		$content = new WikitextContent( '#REDIRECT [[Redirect TestPage]]' );
		$originalPage->doEditContent( $content, '' );

		$this->mImportCallbacks->createRedirect( $title, $origTitle, $revCount, $sucCount, $pageInfo );

		$redirectTitle = Title::newFromText( 'Redirect TestPage' );
		$redirectPage = WikiPage::factory( $redirectTitle );

		$this->assertTrue( $redirectPage->exists() );

		$text = $redirectPage->getContent()->getNativeData();
		$this->assertEquals( "TestPage content", $text );

		$text =$originalPage->getContent()->getNativeData();
		$this->assertEquals( "#REDIRECT [[Redirect TestPage]]", $text );
	}

	public function addDBData() {
		$this->insertUser( 'TestUser' );
		$this->insertWikipage( 'TestPage', 'TestPage content', 'TestPage comment' );
	}

	/**
	 * @param string $userName
	 */
	private function insertUser( $userName ) {
		$user = User::newFromName( $userName );
		$user->addToDatabase();
	}

	/**
	 * @param string $titleName
	 * @param string $contentText
	 * @param string $comment
	 */
	private function insertWikipage( $titleName, $contentText, $comment ) {
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

	private function getBaseRevision() {
		$revision = new WikiRevision;

		$revision->setID( 1 );
		$revision->setTimestamp( wfTimestampNow() );
		$revision->setParams( 'a:0:{}' );
		$revision->setTitle( Title::newFromText( 'TestPage' ) );
		$revision->setNoUpdates( true );

		return $revision;
	}

	public function getNoRedirectData() {
		$title = Title::newFromText( 'TestPage' );
		$origTitle = $title;
		$revCount = 1;
		return array(
			array( $title, $origTitle, $revCount, 1, array() ),
			array( $title, $origTitle, $revCount, 1, array( 'redirect' => "" ) ),
			array( $title, $origTitle, $revCount, 0, array( 'redirect' => "Redirect TestPage" ) )
		);
	}

	public function getRedirectExistsData() {
		$title = Title::newFromText( 'TestPage' );
		$origTitle = $title;
		return array(
			array( $title, $origTitle, 1, 1, array( 'redirect' => "Existing Page"  ) )
		);
	}

	public function getRedirectSuccessful() {
		$title = Title::newFromText( 'TestPage' );
		$origTitle = $title;
		return array(
			array( $title, $origTitle, 1, 1, array( 'redirect' => "Redirect TestPage"  ) )
		);
	}
}
 