<?php

namespace PubSubHubbubSubscriber;

use Title;
use User;
use WikiPage;
use XMLReader;

class DeletionXMLImporter {

	private $mReader = null;

	public function __construct( XMLReader $reader ) {
		$this->mReader = $reader;
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
				$this->warn( "Unhandled deletion XML tag $tag" );
			}
		}
		if ( isset( $logInfo ) ) {
			$this->doDeletion( $logInfo );
		}
	}

	private function warn( $data ) {
		wfDebug( "DeletionXMLImporter: $data\n" );
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
				$logInfo[$tag] = $this->nodeContents();
			} elseif ( $tag == 'contributor' ) {
				$logInfo['contributor'] = $this->parseContributor();
			} elseif ( $tag != '#text' ) {
				$this->warn( "Unhandled log-item XML tag $tag" );
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
			}
			if ( in_array( $tag, $fields ) ) {
				$info[$tag] = $this->nodeContents();
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

	function nodeContents() {
		if ( $this->mReader->isEmptyElement ) {
			return "";
		}
		$buffer = "";
		while ( $this->mReader->read() ) {
			switch ( $this->mReader->nodeType ) {
				case XmlReader::TEXT:
				case XmlReader::SIGNIFICANT_WHITESPACE:
					$buffer .= $this->mReader->value;
					break;
				case XmlReader::END_ELEMENT:
					return $buffer;
			}
		}

		$this->mReader->close();
		return '';
	}
}
