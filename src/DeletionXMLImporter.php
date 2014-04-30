<?php
/**
 * Created by PhpStorm.
 * User: alexander.lehmann
 * Date: 4/29/14
 * Time: 4:28 PM
 */

namespace PubSubHubbubSubscriber;

use Article;
use Title;
use XMLReader;

class DeletionXMLImporter {

	private $reader;

	function __construct( XMLReader $reader ) {
		$this->reader = $reader;
	}

	public function doImport() {
		while ( $this->reader->read()) {
			$tag = $this->reader->name;
			$type = $this->reader->nodeType;

			if ( $tag == 'deletion' && $type == XmlReader::END_ELEMENT ) {
				break;
			} elseif ( $tag == 'logitem' ) {
				$loginfo = $this->parseLogItem();
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled deletion XML tag $tag" );
			}
		}
		$this->doDeletion( $loginfo );
	}

	private function warn( $data ) {
		wfDebug( "DeletionXMLImporter: $data\n" );
	}

	private function parseLogItem() {
		$logInfo = array();
		$normalFields = array( 'id', 'comment', 'type', 'action', 'timestamp',
			'logtitle', 'params' );

		while ( $this->reader->read()) {
			$tag = $this->reader->name;
			$type = $this->reader->nodeType;
			if ( $tag == 'logitem' && $type == XmlReader::END_ELEMENT ) {
				break;
			}elseif ( in_array( $tag, $normalFields ) ) {
				$logInfo[$tag] = $this->reader->readInnerXml();
			} elseif ( $tag == 'contributor' ) {
				$logInfo['contributor'] = $this->parseContributor();
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled log-item XML tag $tag" );
			}
		}
		return $logInfo;
	}

	private function parseContributor() {
		$fields = array( 'id', 'ip', 'username' );
		$info = array();

		while ( $this->reader->read()) {
			$tag = $this->reader->name;
			$type = $this->reader->nodeType;
			if ( $tag == 'contributor' && $type == XmlReader::END_ELEMENT ) {
				break;
			}
			if ( in_array( $tag, $fields ) ) {
				$info[$tag] = $this->reader->readInnerXml();
			}
		}
		return $info;
	}

	private function doDeletion( $logInfo ) {
		$title = Title::newFromText( $logInfo['logtitle'] );
		$article = new Article( $title );
		$article->doDelete('');
	}
}
