<?php

namespace PubSubHubbubSubscriber;

class Subscription {

	private $mId;
	private $mTopic;
	private $mExpires;
	private $mConfirmed;
	private $mUnsubscribe;

	public function __construct( $id = NULL, $topic = NULL, $expires = NULL, $confirmed = false, $unsubscribe = false ) {
		$this->mId = $id;
		$this->mTopic = $topic;
		$this->mExpires = $expires;
		$this->mConfirmed = (bool) $confirmed;
		$this->mUnsubscribe = (bool) $unsubscribe;
	}

	public static function findByID( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select( 'push_subscriptions',
			array( 'psb_topic', 'psb_expires', 'psb_confirmed', 'psb_unsubscribe' ),
			array( 'psb_id' => $id ) );

		if ( $result->numRows() == 0 ) {
			return NULL;
		}

		$data = $result->fetchObject();
		return new Subscription(
			$id,
			$data->psb_topic,
			$data->psb_expires,
			$data->psb_confirmed,
			$data->psb_unsubscribe
		);
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
			array( 'psb_id', 'psb_expires', 'psb_confirmed', 'psb_unsubscribe' ),
			array( 'psb_topic' => $topicURL ) );

		if ( $result->numRows() == 0 ) {
			return NULL;
		}

		$data = $result->fetchObject();
		return new Subscription(
			$data->psb_id,
			$topicURL,
			$data->psb_expires,
			$data->psb_confirmed,
			$data->psb_unsubscribe
		);
	}

	public function update() {
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->mId ) {
			$dbw->update( 'push_subscriptions',
				array(
					'psb_expires' => $this->mExpires,
					'psb_confirmed' => $this->mConfirmed,
					'psb_unsubscribe' => $this->mUnsubscribe,
				),
				array( 'psb_id' => $this->mId ) );
		} else {
			$dbw->insert( 'push_subscriptions', array(
				'psb_topic' => $this->mTopic,
				'psb_expires' => $this->mExpires,
				'psb_confirmed' => $this->mConfirmed,
				'psb_unsubscribe' => $this->mUnsubscribe,
			) );
		}
	}

	public function delete() {
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->mId ) {
			$dbw->delete( 'push_subscriptions', array( 'psb_id' => $this->mId ) );
		}
	}

	/**
	 * @codeCoverageIgnore
	 * @return string
	 */
	public function getTopic() {
		return $this->mTopic;
	}

	/**
	 * @codeCoverageIgnore
	 * @return bool whether this Subscription is already confirmed.
	 */
	public function isConfirmed() {
		return $this->mConfirmed;
	}

	/**
	 * @codeCoverageIgnore
	 * @param bool $confirmed
	 */
	public function setConfirmed( $confirmed ) {
		$this->mConfirmed = (bool) $confirmed;
	}

	/**
	 * @codeCoverageIgnore
	 * @return bool
	 */
	public function isUnsubscribed() {
		return $this->mUnsubscribe;
	}

	/**
	 * @codeCoverageIgnore
	 * @param bool $unsubscribe
	 */
	public function setUnsubscribed( $unsubscribe ) {
		$this->mUnsubscribe = (bool) $unsubscribe;
	}

}