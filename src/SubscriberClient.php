<?php

namespace PubSubHubbubSubscriber;

use Http;
use MWHttpRequest;

class SubscriberClient {

	private $mResourceURL;

	public function __construct( $resourceURL ) {
		$this->mResourceURL = $resourceURL;
	}

	public function subscribe() {
		$rawLinkHeaders = self::findRawLinkHeaders( $this->mResourceURL );
		$linkHeaders = self::parseLinkHeaders( $rawLinkHeaders );
		$hubURL = $linkHeaders['hub'];
		$this->mResourceURL = $linkHeaders['self'];

		$subscription = new Subscription( NULL, $this->mResourceURL );
		$subscription->update();

		self::sendSubscriptionRequest( $hubURL, $this->mResourceURL );
	}

	/**
	 * Retrieve raw link headers from the given URL.
	 *
	 * @param string $resourceURL The resource's URL.
	 * @return string[] an indexed array containing values of all HTTP Link headers.
	 */
	private static function findRawLinkHeaders( $resourceURL ) {
		$req = MWHttpRequest::factory( $resourceURL, array(
			'method' => 'HEAD',
		) );
		$req->execute();
		$rawLinkHeaders = $req->getResponseHeaders();
		return $rawLinkHeaders['link'];
	}

	/**
	 * Parse raw HTTP Link headers.
	 *
	 * @param string[] $rawLinkHeaders An indexed array of raw HTTP Link headers as returned by findRawLinkHeaders.
	 * @return string[] an associative array with the link rel as key and the URL as value.
	 */
	public static function parseLinkHeaders( $rawLinkHeaders ) {
		$linkHeaders = array();
		foreach ( $rawLinkHeaders as $rawLinkHeader ) {
			$count = preg_match_all( "/<(?<url>[^>]+)>;\\s*rel=\"(?<rel>[^\"]+)\"/", $rawLinkHeader, $matches );
			for ( $i = 0; $i < $count; $i++ ) {
				$linkHeaders[ $matches['rel'][$i] ] = $matches['url'][$i];
			}
		}
		return $linkHeaders;
	}

	private static function sendSubscriptionRequest( $hubURL, $resourceURL ) {
		$apiURL = wfExpandURL( wfScript( 'api' ) );
		$callbackURL = wfAppendQuery( $apiURL, array(
			'action' => 'pushcallback',
			'hub.mode' => 'push',
			'hub.topic' => $resourceURL,
		) );

		Http::post( $hubURL, array(
			'postData' => array(
				'hub.callback' => $callbackURL,
				'hub.mode' => 'subscribe',
				'hub.verify' => 'async',
				'hub.topic' => $resourceURL,
				#'hub.secret' => "", // TODO
			)
		) );
		// TODO: Check for errors.
	}

}
