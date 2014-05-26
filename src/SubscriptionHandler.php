<?php

namespace PubSubHubbubSubscriber;

use ImportStreamSource;
use MWTimestamp;
use WikiImporter;

class SubscriptionHandler {

	/**
	 * @param string $topic
	 * @param string $hmacSignature
	 * @param string $file The file to read the POST data from. Defaults to stdin.
	 * @return bool whether the pushed data could be accepted.
	 */
	public function handlePush( $topic, $hmacSignature, $file = "php://input" ) {
		$subscription = Subscription::findByTopic( $topic );
		if ( $subscription && $subscription->isConfirmed() ) {
			$source = ImportStreamSource::newFromFile( $file );
			if ( $source->isGood() ) {
				// Strip 'sha1='.
				$hmacSignature = substr( trim( $hmacSignature ), 5 );

				$content = file_get_contents( $file );
				$expectedSignature = hash_hmac( 'sha1', $content, bin2hex( $subscription->getSecret() ), false );

				if ( $expectedSignature !== $hmacSignature ) {
					wfDebug( '[PubSubHubbubSubscriber] HMAC signature not matching. Ignoring data.' . PHP_EOL );
					// Still need to return success according to specification.
					return true;
				}

				$importer = new WikiImporter( $source->value );
				$importer->doImport();
				return true;
			} else {
				return false;
			}
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
