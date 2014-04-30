<?php

namespace PubSubHubbubSubscriber;

use ApiBase;
use ApiFormatJson;
use ApiFormatRaw;

class ApiSubscription extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$challenge = $params['hub.challenge'];

		$success = false;
		$handler = $this->createSubscriptionHandler();

		switch ( $params['hub.mode'] ) {
			case 'push':
				if ( !$this->getRequest()->wasPosted() ) {
					$this->dieUsage( 'push mode requires POST request', 'post_required', 400 );
				} // @codeCoverageIgnore
				$challenge = "";

				$success = $handler->handlePush();
				break;
			case 'subscribe':
				$success = $handler->handleSubscribe( $params['hub.topic'] );
				break;
			case 'unsubscribe':
				$success = $handler->handleUnsubscribe( $params['hub.topic'] );
				break;
		}

		if ( $success ) {
			$this->acceptRequest( $challenge );
		} else {
			$this->declineRequest();
		}
	}

	/**
	 * @codeCoverageIgnore
	 * @return SubscriptionHandler
	 */
	function createSubscriptionHandler() {
		return new SubscriptionHandler();
	}

	/**
	 * Signal an accepted request.
	 *
	 * @param string $challenge The text to add to the response.
	 */
	public function acceptRequest( $challenge ) {
		$result = $this->getResult();
		$result->addValue( null, 'mime', "text/plain" );
		$result->addValue( null, 'text', $challenge );
	}

	/**
	 * Signal a declined request.
	 */
	public function declineRequest() {
		@header( "HTTP/1.1 404 Not Found", true, 404 );
		$result = $this->getResult();
		$result->addValue( null, 'mime', "text/plain" );
		$result->addValue( null, 'text', "" );
	}

	/**
	 * @codeCoverageIgnore
	 * @return ApiFormatRaw the formatter used to format this API module's output.
	 */
	public function getCustomPrinter() {
		return new ApiFormatRaw( $this->getMain(), new ApiFormatJson( $this->getMain(), 'json' ) );
	}

	/**
	 * Return an array of allowed parameters.
	 *
	 * @codeCoverageIgnore
	 * @return array all allowed parameters.
	 */
	public function getAllowedParams() {
		return array(
			'hub.mode' => array(
				ApiBase::PARAM_TYPE => array( 'push', 'subscribe', 'unsubscribe' ),
				ApiBase::PARAM_REQUIRED => true,
			),
			'hub.topic' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'hub.challenge' => array(
				ApiBase::PARAM_TYPE => 'string',
			),
			'hub.lease_seconds' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
		);
	}

	/**
	 * @codeCoverageIgnore
	 * @return string[] a description of all valid parameters.
	 */
	public function getParamDescription() {
		return array_merge( parent::getParamDescription(), array(
			'hub.mode' => 'The literal string "subscribe" or "unsubscribe", which matches the original request to the '
				. 'hub from the subscriber.',
			'hub.topic' => 'The topic URL given in the corresponding subscription request.',
			'hub.challenge' => 'A hub-generated, random string that MUST be echoed by the subscriber to verify the '
				. 'subscription.',
			'hub.lease_seconds' => 'The hub-determined number of seconds that the subscription will stay active before '
				. 'expiring, measured from the time the verification request was made from the hub to the subscriber. '
				. 'Hubs MUST supply this parameter for subscription requests. This parameter MAY be present for '
				. 'unsubscribe requests and MUST be ignored by subscribers during unsubscription.',
		) );
	}

	/**
	 * Returns the description for this API module.
	 *
	 * @codeCoverageIgnore
	 * @return string the description for this API module.
	 */
	public function getDescription() {
		return "API module to handle requests from the PubSubHubbub hub.";
	}

}
