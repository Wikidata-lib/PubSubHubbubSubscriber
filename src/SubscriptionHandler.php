<?php

namespace PubSubHubbubSubscriber;

use ImportStreamSource;
use WikiImporter;

class SubscriptionHandler {

	public function handlePush() {
		// The hub is POSTing new data.
		$source = ImportStreamSource::newFromFile( "php://input" );
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
	 * @return bool whether the requested subscription could be confirmed.
	 */
	public function handleSubscribe( $topic ) {
		$subscription = Subscription::findByTopic( $topic );

		if ( $subscription && !$subscription->isConfirmed() ) {
			$subscription->setConfirmed(true);
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
