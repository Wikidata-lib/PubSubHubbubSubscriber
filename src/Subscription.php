<?php

namespace PubSubHubbubSubscriber;

class Subscription {

	private $mId;
	private $mTopic;
	private $mExpires;
	private $mConfirmed;

	public function __construct( $id = NULL, $topic = NULL, $expires = NULL, $confirmed = false ) {
		$this->mId = $id;
		$this->mTopic = $topic;
		$this->mExpires = $expires;
		$this->mConfirmed = !!$confirmed;
	}

	/**
	 * Find a subscription by its topic URL.
	 *
	 * @param string $topicURL The URL to look for.
	 * @return Subscription|null the Subscription found or <code>null<code> if none exists.
	 */
	public static function findByTopic( $topicURL ) {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select( 'push_subscriptions',
			array( 'psb_id', 'psb_expires', 'psb_confirmed' ),
			array( 'psb_topic' => $topicURL ) );

		if ( $result->numRows() == 0 ) {
			return NULL;
		}

		$data = $result->fetchObject();
		return new Subscription( $data->psb_id, $topicURL, $data->psb_expires, $data->psb_confirmed );
	}

	public function isConfirmed() {
		return $this->mConfirmed;
	}

}
