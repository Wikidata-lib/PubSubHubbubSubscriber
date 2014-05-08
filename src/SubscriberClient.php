<?php

namespace PubSubHubbubSubscriber;

use MWHttpRequest;

class SubscriberClient {

	/**
	 * @var string $mResourceURL
	 */
	private $mResourceURL;

	/**
	 * @var string $mHubURL
	 */
	private $mHubURL;

	public function __construct( $resourceURL ) {
		$this->mResourceURL = $resourceURL;
	}

	private function retrieveLinkHeaders() {
		$rawLinkHeaders = $this->findRawLinkHeaders( $this->mResourceURL );
		$linkHeaders = self::parseLinkHeaders( $rawLinkHeaders );
		$this->mHubURL = $linkHeaders['hub'];
		$this->mResourceURL = $linkHeaders['self'];
	}

	public function subscribe() {
		$this->retrieveLinkHeaders();

		$subscription = new Subscription( NULL, $this->mResourceURL );
		$subscription->update();

		$this->sendRequest( 'subscribe', $this->mHubURL, $this->mResourceURL );
	}

	public function unsubscribe() {
		$this->retrieveLinkHeaders();

		$subscription = Subscription::findByTopic( $this->mResourceURL );
		if ( !$subscription ) {
			// TODO: Error handling
		}
		$subscription->setUnsubscribed( true );
		$subscription->update();

		$this->sendRequest( 'unsubscribe', $this->mHubURL, $this->mResourceURL );
	}

	/**
	 * Retrieve raw link headers from the given URL.
	 *
	 * @param string $resourceURL The resource's URL.
	 * @return string[] an indexed array containing values of all HTTP Link headers.
	 */
	function findRawLinkHeaders( $resourceURL ) {
		$req = $this->createHttpRequest( 'HEAD', $resourceURL );
		$req->execute();
		$rawLinkHeaders = $req->getResponseHeaders();
		return $rawLinkHeaders['link'];
	}

	/**
	 * @param string $method
	 * @param string $url
	 * @param string[] $postData
	 * @return MWHttpRequest
	 */
	function createHttpRequest( $method, $url, $postData = NULL ) {
		$options = array(
			'method' => $method,
		);
		if ( $postData !== NULL ) {
			$options['postData'] = $postData;
		}
		return MWHttpRequest::factory( $url, $options );
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

	/**
	 * @param string $mode The action to perform. Must be either 'subscribe' or 'unsubscribe'.
	 * @param string $hubURL
	 * @param string $resourceURL
	 */
	function sendRequest( $mode, $hubURL, $resourceURL ) {
		$callbackURL = self::createCallbackURL( $resourceURL );
		$postData = $this->createPostData( $mode, $resourceURL, $callbackURL );

		$request = $this->createHttpRequest( 'POST', $hubURL, $postData );
		$request->execute();
		// TODO: Check for errors.
	}

	/**
	 * @param string $mode The action to perform. Must be either 'subscribe' or 'unsubscribe'.
	 * @param string $resourceURL
	 * @param string $callbackURL
	 * @return string[]
	 */
	function createPostData( $mode, $resourceURL, $callbackURL ) {
		$data = array(
			'hub.callback' => $callbackURL,
			'hub.mode' => $mode,
			'hub.verify' => 'async',
			'hub.topic' => $resourceURL,
		);
		#$data['hub.secret'] = ""; // TODO
		return $data;
	}

	public static function createCallbackURL( $resourceURL ) {
		$apiURL = wfExpandURL( wfScript( 'api' ) );
		return wfAppendQuery( $apiURL, array(
			'action' => 'pushcallback',
			'hub.mode' => 'push',
			'hub.topic' => $resourceURL,
		) );
	}

}
