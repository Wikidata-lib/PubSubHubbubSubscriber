<?php


namespace PubSubHubbubSubscriber;


use ContentHandler;
use ImportStringSource;
use Language;
use MediaWikiLangTestCase;
use Revision;
use Title;
use UploadSourceAdapter;
use User;
use WikiPage;
use XMLReader;

/**
 * @covers PubSubHubbubSubscriber\DeletionXMLImporter
 *
 * @group PubSubHubbubSubscriber
 *  @group Database
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class DeletionXMLImporterTest extends MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );
		stream_wrapper_register( 'uploadsource', 'UploadSourceAdapter' );
	}

	protected function tearDown() {
		stream_wrapper_unregister( 'uploadsource' );
		parent::tearDown();
	}

	protected function newDeletionXMLImporter( $XMLString ) {
		$reader = new XMLReader();
		$source = new ImportStringSource( $XMLString );
		$id = UploadSourceAdapter::registerSource( $source );
		$reader->open( "uploadsource://$id" );
		$reader->read();

		return new DeletionXMLImporter( $reader );
	}

	public function addData() {
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
		$dbw = wfGetDB( DB_MASTER );
		$page = WikiPage::factory( Title::newFromText( $titleName ) );

		if ( !$page->exists() ) {
			$pageId = $page->insertOn( $dbw );
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
		$revision->insertOn( $dbw );
		$changed = $page->updateIfNewerOn( $dbw, $revision );

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

	function testNodeContentsEmpty() {
		$importer = $this->newDeletionXMLImporter( $this->getEmptyXMLNode() );
		$content = $importer->nodeContents();
		$this->assertEquals( '', $content );
	}

	function testNodeContents() {
		$importer = $this->newDeletionXMLImporter( $this->getXMLNode() );
		$content = $importer->nodeContents();
		$this->assertEquals( '1', $content );
	}

	/**
	 * @depends testNodeContents
	 */
	public function testParseContributor() {
		$importer = $this->newDeletionXMLImporter( $this->getContributorXML() );
		$logInfo = $importer->parseContributor();
		$this->assertTrue( $logInfo['id'] == 1 && $logInfo['username'] == 'TestUser' );
	}

	/**
	 * @depends testParseContributor
	 */
	public function testParseLogItem() {
		$importer = $this->newDeletionXMLImporter( $this->getLogitemXML() );
		$actualLogInfo = $importer->parseLogItem();
		$this->assertEquals( $actualLogInfo, $this->getExpectedLogInfo() );
	}

	public function testDoDeletion() {
		$this->addData();
		$importer = $this->newDeletionXMLImporter( $this->getCompleteDeletionXML() );
		$importer->doDeletion( $this->getExpectedLogInfo() );
		$this->wikipageExistsTest();
	}

	/**
	 * @depends testParseLogItem
	 * @depends testDoDeletion
	 */
	public function testDoImport() {
		$this->addData();
		$importer = $this->newDeletionXMLImporter( $this->getCompleteDeletionXML() );
		$importer->doImport();
		$this->wikipageExistsTest();
	}

	private function  wikipageExistsTest() {
		$title = Title::newFromText( 'TestPage' );
		$wikipage = new WikiPage( $title );
		$this->assertFalse( $wikipage->exists() );
	}

	private function getExpectedLogInfo() {
		return array (
			'id'=> '1',
			'comment' => 'content was: "TestPage content"',
			'type' => 'delete',
			'action' => 'delete',
			'timestamp' => '2014-05-13T13:37:27Z',
			'logtitle' => 'TestPage',
			'params' => 'a:0:{}',
			'contributor' => array(
				'id' => 1,
				'username' => 'TestUser'
			)
		);
	}

	private function getCompleteDeletionXML() {
		$return = <<<EOF
<deletion>
<logitem>
<id>1</id>
<timestamp>2014-05-13T13:37:27Z</timestamp>
<contributor>
<username>TestUser</username>
<id>1</id>
</contributor>
<comment>content was: "TestPage content"</comment>
<type>delete</type>
<action>delete</action>
<logtitle>TestPage</logtitle>
<params xml:space="preserve">a:0:{}</params>
</logitem>
</deletion>
EOF;
		return $return;
	}

	private function getContributorXML() {
		$return = <<<EOF
<contributor>
<username>TestUser</username>
<id>1</id>
</contributor>
EOF;
		return $return;
	}

	private function getLogitemXML() {
		$return = <<<EOF
<logitem>
<id>1</id>
<timestamp>2014-05-13T13:37:27Z</timestamp>
<contributor>
<username>TestUser</username>
<id>1</id>
</contributor>
<comment>content was: "TestPage content"</comment>
<type>delete</type>
<action>delete</action>
<logtitle>TestPage</logtitle>
<params xml:space="preserve">a:0:{}</params>
</logitem>
EOF;
		return $return;
	}

	private function getEmptyXMLNode() {
		return '<id/>';
	}

	private function  getXMLNode() {
		return '<id>1</id>';
	}
}
 