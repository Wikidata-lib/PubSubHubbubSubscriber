<?php

namespace PubSubHubbubSubscriber;

use ImportStreamSource;
use Title;
use User;
use MWTimestamp;
use WikiImporter;
use WikiPage;
use WikiRevision;

class SubscriptionHandler {

	private $previousPageOutCallback;

	/**
	 * @param string $file The file to read the POST data from. Defaults to stdin.
	 * @return bool whether the pushed data could be accepted.
	 */
	public function handlePush( $file = "php://input" ) {
		// The hub is POSTing new data.
		$source = ImportStreamSource::newFromFile( $file );
		if ( $source->isGood() ) {
			$importer = new WikiImporter( $source->value );
			$importer->setLogItemCallback( array( &$this, 'deletionPage' ) );
			$this->previousPageOutCallback = $importer->setPageOutCallback( array( &$this, ' createRedirectPage' ) );
			$importer->doImport();
			return true;
		} else {
			return false;
		}
	}

	public function deletionPage( WikiRevision $revision ) {
		if ( $revision->getAction() != 'delete' ){
			return;
		}
		$username = $revision->getUser();
		if ( !empty( $username ) ) {
			$user = User::newFromName( $username );
		}
		else {
			$user = null;
		}
		$error = array();
		$title = $revision->getTitle();
		$wikipage = new WikiPage( $title );
		$wikipage->doDeleteArticle( $revision->getComment(), false, 0, true, $error, $user );
	}

	private function callOriginalPageOutCallback( Title $title, $origTitle, $revCount, $sucCount, $pageInfo ) {
		if ( is_callable( $this->previousPageOutCallback) ) {
			call_user_func_array( $this->previousPageOutCallback, func_get_args() );
		}
	}

	public function createRedirectPage( Title $title, $origTitle, $revCount, $sucCount, $pageInfo ) {
		if ( !array_key_exists( 'redirect', $pageInfo ) ) {
			$this->callOriginalPageOutCallback( $title, $origTitle, $revCount, $sucCount, $pageInfo );
			return;
		}
		$wikipage = new WikiPage( $title );
		$redirectTitle = Title::newFromText( $pageInfo['redirect'] );
		if ($redirectTitle->exists()){
			$this->callOriginalPageOutCallback( $title, $origTitle, $revCount, $sucCount, $pageInfo );
			return;
		}

		$dbw = wfGetDB( DB_MASTER );
		$pageId = $wikipage->getId();
		$currentRevision = $wikipage->getRevision();
		$contentRevision = $currentRevision->getPrevious();
		$currentRevisionID = $currentRevision->getId();
		$contentRevisionID = $contentRevision->getId();

		$dbw->delete( 'revision', array( 'rev_id' => $currentRevisionID ) );
		$dbw->update( 'page', array( 'page_latest' => $contentRevisionID ), array( 'page_id' => $pageId ) );
		$title->moveTo( $redirectTitle );

		$this->callOriginalPageOutCallback( $title, $origTitle, $revCount, $sucCount, $pageInfo );
	}

	/**
	 * @param string $topic
	 * @param int $lease_seconds
	 * @return bool whether the requested subscription could be confirmed.
	 */
	public function handleSubscribe( $topic, $lease_seconds ) {
		$subscription = Subscription::findByTopic( $topic );

		if ( $subscription && !$subscription->isConfirmed() ) {
			$subscription->setConfirmed(true);
			$subscription->setExpires( new MWTimestamp( $_SERVER['REQUEST_TIME'] + $lease_seconds ) );
			$subscription->update();
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @param string $topic
	 * @return bool whether the requested unsubscription could be confirmed.
	 */
	public function handleUnsubscribe( $topic ) {
		$subscription = Subscription::findByTopic( $topic );

		if ( $subscription && $subscription->isUnsubscribed() ) {
			$subscription->delete();
			return true;
		} else {
			return false;
		}
	}

}
