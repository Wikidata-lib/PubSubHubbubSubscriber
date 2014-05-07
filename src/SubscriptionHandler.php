<?php

namespace PubSubHubbubSubscriber;

use ImportStreamSource;
use WikiImporter;

class SubscriptionHandler {

	/**
	 * @param string $file The file to read the POST data from. Defaults to stdin.
	 * @return bool whether the pushed data could be accepted.
	 */
	public function handlePush( $file = "php://input" ) {
		// The hub is POSTing new data.
		$source = ImportStreamSource::newFromFile( $file );
		if ( $source->isGood() ) {
			$importer = new WikiImporter( $source->value );
			$importer->doImport();
			return true;
		} else {
			return false;
		}
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
			$subscription->setExpires( time() + $lease_seconds );
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
