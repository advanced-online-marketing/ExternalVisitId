<?php

namespace Piwik\Plugins\ExternalVisitId\tests\System;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugins\ExternalVisitId\Columns\ExternalVisitId;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visit\VisitProperties;
use Piwik\Tracker\Visitor;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;
use Piwik;
use Piwik\Tests\Framework\Fixture;

/**
 * @group ExternalVisitId
 * @group ExternalVisitId_Integration
 */
class ExternalVisitIdTest extends IntegrationTestCase
{
    /**
     * @var Fixture
     */
    public static $fixture = null; // initialized below class definition

    /**
     * Tests if the fixtures have filled up the external_visit_id column correctly.
     */
    public function testFixturesWithExternalVisitId()
    {
        // Visit without external_visit_id
        $data =  Db::fetchOne(
            'SELECT external_visit_id FROM ' . Common::prefixTable('log_visit') . ' WHERE user_id = ?',
            ['d9857faa8002a8eebd0bc75b63dfacef']
        );

        $this->assertEquals(null, $data);


        // Visit with external_visit_id
        $data =  Db::fetchOne(
            'SELECT external_visit_id FROM ' . Common::prefixTable('log_visit') . ' WHERE user_id = ?',
            ['919c1aed2f5b1f79d27951b0b309ff42']
        );

        $this->assertEquals('12345', $data);
    }

    /**
     * Tests if the onNewVisit method works and returns the external_visit_id that should be written to the DB.
     */
    public function testOnNewVisit()
    {
        $request = new Request(['idsite' => 1]);
        $visitor = new Visitor(new VisitProperties());

        /** @var Action $action */
        $action = $this->getMockBuilder('\Piwik\Tracker\Action')
            ->disableOriginalConstructor()->getMock();

        $request->setParam('external_visit_id', 4711);

        $externalVisitIdColumn = new ExternalVisitId();

        $this->assertEquals(4711, $externalVisitIdColumn->onNewVisit($request, $visitor, $action));
    }

    /**
     * Tests if visits are split when the external_visit_id changes.
     */
    public function testNewVisitWhenExternalVisitIdChanges()
    {
        $dateTime = '2017-02-01 01:23:45';

        /** @var Piwik\Plugins\ExternalVisitId\tests\Fixtures\Fixtures $fixture */
        $fixture = ExternalVisitIdTest::$fixture;
        $tracker = $fixture::getTracker(1, $dateTime, $defaultInit = true, $useLocal = false);
        $tracker->setTokenAuth($fixture->tokenAuth);


        $tracker->setUserId('12857faa8002a8eebd0bc75b63dfacef');

        $fixture->moveTimeForward($tracker, 0.1, $dateTime);
        $tracker->setUrl('http://example.com/');
        $tracker->setCustomTrackingParameter('external_visit_id', 345678);
        $tracker->doTrackPageView('Viewing homepage');

        $fixture->moveTimeForward($tracker, 0.3, $dateTime);
        $tracker->setUrl('http://example.com/');
        $tracker->setCustomTrackingParameter('external_visit_id', 987654);
        $tracker->doTrackPageView('Viewing homepage');

        $this->assertNotEquals(
            Db::fetchOne(
                'SELECT idvisit FROM ' . Common::prefixTable('log_visit') . ' WHERE external_visit_id = ?',
                [345678]
            ),
            Db::fetchOne(
                'SELECT idvisit FROM ' . Common::prefixTable('log_visit') . ' WHERE external_visit_id = ?',
                [987654]
            )
        );
    }

    /**
     * Tests if plugin has no effect to visit splitting when no external_visit_id is passed.
     */
    public function testNoExternalVisitId()
    {
        $dateTime = '2017-02-01 01:23:45';

        /** @var Piwik\Plugins\ExternalVisitId\tests\Fixtures\Fixtures $fixture */
        $fixture = ExternalVisitIdTest::$fixture;
        $tracker = $fixture::getTracker(1, $dateTime, $defaultInit = true, $useLocal = false);
        $tracker->setTokenAuth($fixture->tokenAuth);


        // First visitor with one visit with two actions without externalVisitId
        $tracker->setUserId('23857faa8002a8eebd0bc75b63dfacef');

        $fixture->moveTimeForward($tracker, 0.1, $dateTime);
        $tracker->setUrl('http://example.com/');
        $tracker->setCustomTrackingParameter('external_visit_id', 456789);
        $tracker->doTrackPageView('Viewing homepage');

        $fixture->moveTimeForward($tracker, 0.1, $dateTime);
        $tracker->setUrl('http://example.com/');
        // No external_visit_id here, i.e. should be part of previous visit.
        $tracker->doTrackPageView('Viewing homepage');

        $fixture->moveTimeForward($tracker, 0.3, $dateTime);
        $tracker->setUrl('http://example.com/');
        $tracker->setCustomTrackingParameter('external_visit_id', 876543);
        $tracker->doTrackPageView('Viewing homepage');

        // As second action should belong to first visit, the first visit's visit_total_interactions should be 2
        $this->assertEquals(
            2,
            Db::fetchOne(
                'SELECT visit_total_interactions FROM ' . Common::prefixTable('log_visit')
                    . ' WHERE external_visit_id = ?',
                [456789]
            )
        );

        // First and last action should be two different visits
        $this->assertNotEquals(
            Db::fetchOne(
                'SELECT idvisit FROM ' . Common::prefixTable('log_visit') . ' WHERE external_visit_id = ?',
                [456789]
            ),
            Db::fetchOne(
                'SELECT idvisit FROM ' . Common::prefixTable('log_visit') . ' WHERE external_visit_id = ?',
                [876543]
            )
        );
    }
}

ExternalVisitIdTest::$fixture = new Piwik\Plugins\ExternalVisitId\tests\Fixtures\Fixtures();
