<?php

namespace PubSubHubbubSubscriber\Maintenance;

use Maintenance;
use PubSubHubbubSubscriber\PubSubHubbubException;
use PubSubHubbubSubscriber\SubscriberClient;
use PubSubHubbubSubscriber\Subscription;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';
require_once $basePath . '/maintenance/Maintenance.php';

class ManageSubscriptions extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Manage PubSubHubbub subscriptions";
		$this->addOption( 'list', 'Show all subscriptions with their state.', false, false, 'l' );
		$this->addOption( 'create', 'Create a new subscription to the URL specified.', false, true, 'c' );
		$this->addOption( 'delete', 'Delete the subscription to the URL specified.', false, true, 'd' );
	}

	public function execute() {
		$modeList = $this->hasOption( 'list' );
		$modeCreate = $this->hasOption( 'create' );
		$modeDelete = $this->hasOption( 'delete' );

		if ( $modeList ) {
			$subscriptions = Subscription::getAll();
			foreach ( $subscriptions as $subscription ) {
				echo $subscription->getTopic() . PHP_EOL;
				echo "Verified: " . ( $subscription->isConfirmed() ? 'Yes' : 'No') . PHP_EOL;
				if ( $subscription->isConfirmed() ) {
					echo "  Valid until: " . $subscription->getExpires()->getHumanTimestamp() . PHP_EOL;
				}
				echo "Marked for deletion: " . ( $subscription->isUnsubscribed() ? 'Yes' : 'No' ) . PHP_EOL;
				echo PHP_EOL;
			}
		}
		if ( $modeCreate ) {
			$url = $this->getOption( 'create' );
			$client = new SubscriberClient( $url );
			try {
				$client->subscribe();
			} catch ( PubSubHubbubException $e ) {
				echo "Error occurred:" . PHP_EOL . $e->getMessage() . PHP_EOL;
			}
		}
		if ( $modeDelete ) {
			$url = $this->getOption( 'delete' );
			$client = new SubscriberClient( $url );
			try {
				$client->unsubscribe();
			} catch ( PubSubHubbubException $e ) {
				echo "Error occurred: " . PHP_EOL . $e->getMessage() . PHP_EOL;
			}
		}
	}
}

$maintClass = 'PubSubHubbubSubscriber\Maintenance\ManageSubscriptions';
require_once RUN_MAINTENANCE_IF_MAIN;