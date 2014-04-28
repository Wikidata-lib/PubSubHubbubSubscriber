<?php

namespace PubSubHubbubSubscriber;

use ApiMain;
use Language;
use MediaWikiLangTestCase;

/**
 * @covers PubSubHubbubSubscriber\ApiSubscription
 *
 * @group PubSubHubbubSubscriber
 *
 * @licence GNU GPL v2+
 * @author Sebastian Brückner < sebastian.brueckner@student.hpi.uni-potsdam.de >
 */
class ApiSubscriptionTest extends MediaWikiLangTestCase {

	protected function setUp() {
		parent::setUp();
		$this->setMwGlobals( array(
			'wgContLang' => Language::factory( 'en' ),
			'wgLanguageCode' => 'en',
		) );
	}

	/**
	 * @dataProvider getAcceptRequestData
	 */
	public function testAcceptRequest( $challenge, $expectedResultData ) {
		$api = new ApiSubscription( new ApiMain(), 'pushcallback' );
		$api->acceptRequest( $challenge );
		$result = $api->getResult();
		$data = $result->getData();
		$this->assertArrayEquals( $expectedResultData, $data, false, true );
	}

	public function testDeclineRequest() {
		$api = new ApiSubscription( new ApiMain(), 'pushcallback' );
		$api->declineRequest();
		$result = $api->getResult();
		$data = $result->getData();
		$this->assertArrayEquals( array(
			'mime' => 'text/plain',
			'text' => '',
		), $data, false, true );
	}

	public function getAcceptRequestData() {
		return array(
			array(
				'Challenge 1',
				array(
					'mime' => 'text/plain',
					'text' => 'Challenge 1',
				)
			),
			array(
				'This is another challenge',
				array(
					'mime' => 'text/plain',
					'text' => 'This is another challenge',
				)
			),
		);
	}

}
