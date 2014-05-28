<?php

namespace PubSubHubbubSubscriber;


use Title;
use User;
use WikiPage;
use WikiRevision;

class ImportCallbacks {

	private $previousPageOutCallback = null;

	/**
	 * @codeCoverageIgnore
	 * @param callable $previousPageOutCallback
	 */
	public function setPreviousPageOutCallback( callable $previousPageOutCallback ) {
		$this->previousPageOutCallback = $previousPageOutCallback;
	}

	/**
	 * @param WikiRevision $revision
	 */
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

	/**
	 * @param Title $title
	 * @param $origTitle
	 * @param $revCount
	 * @param $sucCount
	 * @param $pageInfo
	 */
	private function callOriginalPageOutCallback( Title $title, $origTitle, $revCount, $sucCount, $pageInfo ) {
		if ( is_callable( $this->previousPageOutCallback) ) {
			call_user_func_array( $this->previousPageOutCallback, func_get_args() );
		}
	}

	/**
	 * @param Title $title
	 * @param $origTitle
	 * @param $revCount
	 * @param $sucCount
	 * @param $pageInfo
	 */
	public function createRedirect( Title $title, $origTitle, $revCount, $sucCount, $pageInfo ) {
		if ( !array_key_exists( 'redirect', $pageInfo ) || $pageInfo['redirect'] == "" || $sucCount < 1 ) {
			$this->callOriginalPageOutCallback( $title, $origTitle, $revCount, $sucCount, $pageInfo );
			return;
		}

		$wikipage = new WikiPage( $title );
		$redirectTitle = Title::newFromText( $pageInfo['redirect'] );
		if ( $redirectTitle->exists() ){
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
		$title->moveTo( $redirectTitle, false );

		$this->callOriginalPageOutCallback( $title, $origTitle, $revCount, $sucCount, $pageInfo );
	}

} 