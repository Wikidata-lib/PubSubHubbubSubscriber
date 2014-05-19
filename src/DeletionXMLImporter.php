<?php

namespace PubSubHubbubSubscriber;

use Title;
use User;
use WikiImporter;
use WikiPage;
use XMLReader;

class DeletionXMLImporter {

	private $mReader = null;
	private $mImporter = null;

	public function __construct( WikiImporter $importer ) {
		$this->mImporter = $importer;
		$this->mReader = $importer->getReader();
	}

	public function doImport() {
		$logInfo = null;
		while ( $this->mReader->read() ) {
			$tag = $this->mReader->name;
			$type = $this->mReader->nodeType;

			if ( $tag == 'deletion' && $type == XmlReader::END_ELEMENT ) {
				break;
			} elseif ( $tag == 'logitem' ) {
				$logInfo = $this->parseLogItem();
			} elseif ( $tag != '#text' ) {
				$this->mImporter->warn( "Unhandled deletion XML tag $tag" );
			}
		}
		if ( isset( $logInfo ) ) {
			$this->doDeletion( $logInfo );
		}
	}

	function parseLogItem() {
		$logInfo = array();
		$normalFields = array( 'id', 'comment', 'type', 'action', 'timestamp', 'logtitle', 'params' );

		while ( $this->mReader->read() ) {
			$tag = $this->mReader->name;
			$type = $this->mReader->nodeType;
			if ( $tag == 'logitem' && $type == XmlReader::END_ELEMENT ) {
				break;
			} elseif ( in_array( $tag, $normalFields ) ) {
				$logInfo[$tag] = $this->mImporter->nodeContents();
			} elseif ( $tag == 'contributor' ) {
				$logInfo['contributor'] = $this->parseContributor();
			} elseif ( $tag != '#text' ) {
				$this->mImporter->warn( "Unhandled log-item XML tag $tag" );
			}
		}
		return $logInfo;
	}

	function parseContributor() {
		$fields = array( 'id', 'ip', 'username' );
		$info = array();

		while ( $this->mReader->read() ) {
			$tag = $this->mReader->name;
			$type = $this->mReader->nodeType;
			if ( $tag == 'contributor' && $type == XmlReader::END_ELEMENT ) {
				break;
			} elseif ( in_array( $tag, $fields ) ) {
				$info[$tag] = $this->mImporter->nodeContents();
			}
		}
		return $info;
	}

	function doDeletion( $logInfo ) {
		if ( isset( $logInfo['contributor']['username'] ) ) {
			$user = User::newFromName( $logInfo['contributor']['username'] );
		}
		else {
			$user = null;
		}
		$error = array();
		$title = Title::newFromText( $logInfo['logtitle'] );
		$wikipage = new WikiPage( $title );
		$wikipage->doDeleteArticle( $logInfo['comment'], false, 0, true, $error, $user );
	}
}
