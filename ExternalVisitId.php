<?php

namespace Piwik\Plugins\ExternalVisitId;

use Piwik\Config;
use Piwik\Tracker\TrackerConfig;

class ExternalVisitId extends \Piwik\Plugin
{
    /**
     * @param bool|string $pluginName
     */
    public function __construct($pluginName = false)
    {
        parent::__construct($pluginName);
    }

    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return [
            'Tracker.makeNewVisitObject' => 'validatePluginRequirements',
        ];
    }

    /**
     * @return bool
     */
    public function isTrackerPlugin()
    {
        return true;
    }

    /**
     * We do not change the Visit object (which is the original idea of this hook); instead we use this moment in time
     * to validate that the VisitorRecognizer override described in the README.md is in place.
     *
     * @param $visit
     * @throws \Exception
     */
    public function validatePluginRequirements(&$visit)
    {
        if (86400 > Config::getInstance()->Tracker['visit_standard_length']) {
            throw new \Exception(
                'Tracker config "visit_standard_length" should be at least 86400'
            );
        }

        if (0 !== TrackerConfig::getConfigValue('create_new_visit_when_campaign_changes')) {
            throw new \Exception(
                'Tracker config "create_new_visit_when_campaign_changes" must be 0'
            );
        }

        if (0 !== TrackerConfig::getConfigValue('create_new_visit_when_website_referrer_changes')) {
            throw new \Exception(
                'Tracker config "create_new_visit_when_website_referrer_changes" must be 0'
            );
        }

        $this->validateVisitorRecognizerOverride();
    }

    private function validateVisitorRecognizerOverride()
    {
        $code = file_get_contents(PIWIK_INCLUDE_PATH . '/core/Tracker/VisitorRecognizer.php');

        // We just check that some random strings exist
        if (!strpos($code, 'This method has been manually overridden by the ExternalVisitId plugin')
            || !strpos($code, '$externalVisitId')
            || !strpos($code, 'WHERE idsite = ? AND idvisitor = ? AND external_visit_id = ?')
        ) {
            throw new \Exception(
                'ExternalVisitId requires override of Piwik\Tracker\VisitorRecognizer.findKnownVisitor()'
            );
        }
    }
}
