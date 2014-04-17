<?php

namespace PubSubHubbubSubscriber;

use ApiBase;
use ApiFormatJson;
use ApiFormatRaw;
use ApiMain;
use ImportStreamSource;
use MWException;
use WikiImporter;

class ApiSubscription extends ApiBase {

	public function __construct( ApiMain $main, $name, $prefix = '' ) {
		parent::__construct( $main, $name, $prefix );
	}

	public function execute() {
		$params = $this->extractRequestParams();

		switch ( $params['hub.mode'] ) {
			case 'push':
				if ( !$this->getRequest()->wasPosted() ) {
					throw new MWException("Illegal PuSH request.");
				}

				// The hub is POSTing new data.
				$source = ImportStreamSource::newFromFile( "php://input" );
				if ( $source->isGood() ) {
					$importer = new WikiImporter( $source->value );
					$importer->doImport();
					$this->acceptSubscriptionChange( "" );
				} else {
					$this->declineSubscriptionChange();
				}
				break;
			case 'subscribe':
				$subscription = Subscription::findByTopic( $params['hub.topic'] );

				if ( $subscription && !$subscription->isConfirmed() ) {
					$subscription->setConfirmed(true);
					$subscription->update();
					$this->acceptSubscriptionChange( $params['hub.challenge'] );
				} else {
					$this->declineSubscriptionChange();
				}
				break;
			case 'unsubscribe':
				$subscription = Subscription::findByTopic( $params['hub.topic'] );

				if ( $subscription && $subscription->isUnsubscribed() ) {
					$subscription->delete();
					$this->acceptSubscriptionChange( $params['hub.challenge'] );
				} else {
					$this->declineSubscriptionChange();
				}
				break;
		}
	}

	private function acceptSubscriptionChange( $challenge ) {
		$result = $this->getResult();
		$result->addValue( null, 'mime', "text/plain" );
		$result->addValue( null, 'text', $challenge );
	}

	private function declineSubscriptionChange() {
		header( "HTTP/1.1 404 Not Found", true, 404 );
		$result = $this->getResult();
		$result->addValue( null, 'mime', "text/plain" );
		$result->addValue( null, 'text', "" );
	}

	public function getCustomPrinter() {
		return new ApiFormatRaw( $this->getMain(), new ApiFormatJson( $this->getMain(), 'json' ) );
	}

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

	public function getDescription() {
		return "API module to handle requests from the PubSubHubbub hub.";
	}

}
