<?php

namespace PubSubHubbubSubscriber;

use ContentHandler;
use ImportStringSource;
use MediaWikiLangTestCase;
use Revision;
use Title;
use User;
use WikiImporter;
use WikiPage;

/**
 * @covers PubSubHubbubSubscriber\DeletionXMLImporter
 *
 * @group Database
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Alexander Lehmann < alexander.lehmann@student.hpi.uni-potsdam.de >
 */
class DeletionXMLImporterTest extends MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();
	}

	protected function tearDown() {
		parent::tearDown();
	}

	protected function newDeletionXMLImporter( $XMLString ) {
		$source = new ImportStringSource( $XMLString );
		$wikiImporter = new WikiImporter( $source );
		return new DeletionXMLImporter( $wikiImporter );
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

	public function testParseContributor() {
		$importer = $this->newDeletionXMLImporter( $this->getContributorXML() );
		$logInfo = $importer->parseContributor();
		$this->assertEquals( 1, $logInfo['id'] );
		$this->assertEquals( 'TestUser', $logInfo['username'] );
		unset( $importer );
	}

	/**
	 * @depends testParseContributor
	 */
	public function testParseLogItem() {
		$importer = $this->newDeletionXMLImporter( $this->getLogitemXML() );
		$actualLogInfo = $importer->parseLogItem();
		$this->assertEquals( $actualLogInfo, $this->getExpectedLogInfo() );
		unset( $importer );
	}

	public function testDoDeletion() {
		$this->addData();
		$importer = $this->newDeletionXMLImporter( $this->getCompleteDeletionXML() );
		$importer->doDeletion( $this->getExpectedLogInfo() );
		$this->assertWikiPageExists();
		unset( $importer );
	}

	/**
	 * @depends testParseLogItem
	 * @depends testDoDeletion
	 */
	public function testDoImport() {
		$this->addData();
		$importer = $this->newDeletionXMLImporter( $this->getCompleteDeletionXML() );
		$importer->doImport();
		$this->assertWikiPageExists();
		unset( $importer );
	}

	private function  assertWikiPageExists() {
		$title = Title::newFromText( 'TestPage' );
		$wikiPage = new WikiPage( $title );
		$this->assertFalse( $wikiPage->exists() );
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
