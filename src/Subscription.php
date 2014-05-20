<?php

namespace PubSubHubbubSubscriber;

use MWTimestamp;

class Subscription {

	/**
	 * @var null|int $mId
	 */
	private $mId;
	/**
	 * @var string $mTopic
	 */
	private $mTopic;
	/**
	 * @var string $mSecret
	 */
	private $mSecret;
	/**
	 * @var null|MWTimestamp $mExpires
	 */
	private $mExpires;
	/**
	 * @var bool $mConfirmed
	 */
	private $mConfirmed;
	/**
	 * @var bool $mUnsubscribe
	 */
	private $mUnsubscribe;

	public function __construct( $id = NULL, $topic = NULL, $secret = NULL, $expires = NULL, $confirmed = false, $unsubscribe = false ) {
		$this->mId = $id;
		$this->mTopic = $topic;
		$this->mSecret = $secret;
		$this->mExpires = $expires;
		$this->mConfirmed = (bool) $confirmed;
		$this->mUnsubscribe = (bool) $unsubscribe;
	}

	public static function createFromDBObject( $object ) {
		return new self(
			$object->psb_id,
			$object->psb_topic,
			$object->psb_secret,
			$object->psb_expires === NULL ? NULL : new MWTimestamp( $object->psb_expires ),
			(bool) $object->psb_confirmed,
			(bool) $object->psb_unsubscribe
		);
	}

	public static function findByID( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select( 'push_subscriptions',
			array( 'psb_id', 'psb_topic', 'psb_secret', 'psb_expires', 'psb_confirmed', 'psb_unsubscribe' ),
			array( 'psb_id' => $id ) );

		if ( $result->numRows() == 0 ) {
			return NULL;
		}

		$data = $result->fetchObject();
		return self::createFromDBObject( $data );
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
			array( 'psb_id', 'psb_topic', 'psb_secret', 'psb_expires', 'psb_confirmed', 'psb_unsubscribe' ),
			array( 'psb_topic' => $topicURL ) );

		if ( $result->numRows() == 0 ) {
			return NULL;
		}

		$data = $result->fetchObject();
		return self::createFromDBObject( $data );
	}

	/**
	 * @return Subscription[]
	 */
	public static function getAll() {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select( 'push_subscriptions',
			array( 'psb_id', 'psb_topic', 'psb_secret', 'psb_expires', 'psb_confirmed', 'psb_unsubscribe' ) );

		$subscriptions = array();

		while ( ( $data = $result->fetchObject() ) !== false ) {
			$subscriptions[] = self::createFromDBObject( $data );
		}
		return $subscriptions;
	}

	public function update() {
		$dbw = wfGetDB( DB_MASTER );
		if ( $this->mId ) {
			$dbw->update( 'push_subscriptions',
				array(
					'psb_secret' => $this->mSecret,
					'psb_expires' => $this->mExpires === NULL ? NULL
						: $dbw->timestamp( $this->mExpires->getTimestamp( TS_MW ) ),
					'psb_confirmed' => $this->mConfirmed,
					'psb_unsubscribe' => $this->mUnsubscribe,
				),
				array( 'psb_id' => $this->mId ) );
		} else {
			$dbw->insert( 'push_subscriptions', array(
				'psb_topic' => $this->mTopic,
				'psb_secret' => $this->mSecret,
				'psb_expires' => $this->mExpires === NULL ? NULL
					: $dbw->timestamp( $this->mExpires->getTimestamp( TS_MW ) ),
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
	 * @return int|null the ID of the subscription or NULL if it has not been saved to the DB yet.
	 */
	public function getID() {
		return $this->mId;
	}

	/**
	 * @return string
	 */
	public function getTopic() {
		return $this->mTopic;
	}

	/**
	 * @codeCoverageIgnore
	 * @return MWTimestamp|null
	 */
	public function getExpires() {
		return $this->mExpires;
	}

	/**
	 * @codeCoverageIgnore
	 * @param MWTimestamp|null $expires
	 */
	public function setExpires( $expires ) {
		$this->mExpires = $expires;
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
