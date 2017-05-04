<?php

namespace Piwik\Plugins\ExternalVisitId;

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
            'Tracker.makeNewVisitObject' => 'validateVisitorRecognizerOverride',
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
    public function validateVisitorRecognizerOverride(&$visit)
    {
        $code = file_get_contents(PIWIK_INCLUDE_PATH . '/core/Tracker/VisitorRecognizer.php');

        // We just check that some random strings exist
        if (!strpos($code, 'This method has been manually overridden by the ExternalVisitId plugin')
            || !strpos($code, '$externalVisitIds')
            || !strpos($code, 'WHERE idsite = ? AND idvisitor = ? AND external_visit_id = ?')
        ) {
            throw new \Exception(
                'ExternalVisitId requires override of Piwik\Tracker\VisitorRecognizer.findKnownVisitor()'
            );
        }
    }
}
