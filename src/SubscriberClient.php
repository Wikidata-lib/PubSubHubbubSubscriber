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
		$linkHeaders = self::findLinkHeaders( $this->mResourceURL );
		$hubURL = $linkHeaders['hub'];
		$this->mResourceURL = $linkHeaders['self'];

		$subscription = new Subscription( NULL, $this->mResourceURL );
		$subscription->update();

		self::sendSubscriptionRequest( $hubURL, $this->mResourceURL );
	}

	private static function findLinkHeaders( $resourceURL ) {
		$req = MWHttpRequest::factory( $resourceURL, array(
			'method' => 'HEAD',
		) );
		$req->execute();
		$rawLinkHeaders = $req->getResponseHeaders();
		$rawLinkHeaders = $rawLinkHeaders['link'];
		$linkHeaders = array();
		foreach ( $rawLinkHeaders as $rawLinkHeader ) {
			$count = preg_match_all( "/<(?<url>[^>]+)>;\\s*rel=\"(?<rel>hub|self)\"/", $rawLinkHeader, $matches );
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
				'hub.topic' => $resourceURL,
				#'hub.secret' => "", // TODO
			)
		) );
		// TODO: Check for errors.
	}

}
