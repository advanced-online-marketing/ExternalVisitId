<?php

namespace Piwik\Plugins\ExternalVisitId\tests\Fixtures;

use Piwik;
use Piwik\Date;

class Fixtures extends Piwik\Tests\Framework\Fixture
{
    public $dateTime = '2017-02-01 01:23:45';
    public $idSite = 1;
    public $tokenAuth;

    public function setUp()
    {
        $idSite = self::createWebsite('2017-01-01 01:23:45', $ecommerce = 1, 'Example Website');
        $this->assertTrue($idSite === 1);

        $user = self::createSuperUser();
        $this->tokenAuth = $user['token_auth'];

        // since we're changing the list of activated plugins, we have to make sure file caches are reset
        Piwik\Cache::flushAll();

        $this->trackActions();
    }

    /**
     * Tracks some actions and generates visitors, visits etc. from them.
     */
    private function trackActions()
    {
        $dateTime = $this->dateTime;

        $t = self::getTracker($this->idSite, $dateTime, $defaultInit = true, $useLocal = false);
        $t->setTokenAuth($this->tokenAuth);


        // First visitor with one visit with two actions without externalVisitId
        $t->setUserId('d9857faa8002a8eebd0bc75b63dfacef');

        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://example.com/');
        self::checkResponse($t->doTrackPageView('Viewing homepage'));

        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl('http://example.com/');
        self::checkResponse($t->doTrackPageView('Second visit, should belong to existing visit'));


        // Second visitor with one visit with two actions with external_visit_id
        $t->setUserId('919c1aed2f5b1f79d27951b0b309ff42');

        $this->moveTimeForward($t, 0.1, $dateTime);
        $t->setUrl('http://example.com/');
        $t->setCustomTrackingParameter('external_visit_id', 12345);
        self::checkResponse($t->doTrackPageView('Viewing homepage'));

        $this->moveTimeForward($t, 0.3, $dateTime);
        $t->setUrl('http://example.com/');
        $t->setCustomTrackingParameter('external_visit_id', 12345);
        self::checkResponse($t->doTrackPageView('Second visit, should belong to existing visit'));
    }

    /**
     * @param \PiwikTracker $t
     * @param $hourForward
     * @param $dateTime
     * @throws \Exception
     */
    public function moveTimeForward(\PiwikTracker $t, $hourForward, $dateTime)
    {
        $t->setForceVisitDateTime(Date::factory($dateTime)->addHour($hourForward)->getDatetime());
    }

    public function provideContainerConfig()
    {
        return [
            'observers.global' => \DI\add([
                ['Environment.bootstrapped', function () {
                    $plugins = Piwik\Config::getInstance()->Plugins['Plugins'];
                    $plugins[] = 'ExternalVisitId';
                    Piwik\Config::getInstance()->Plugins['Plugins'] = $plugins;
                }],
            ]),
        ];
    }

}
