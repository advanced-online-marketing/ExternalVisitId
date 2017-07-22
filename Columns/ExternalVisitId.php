<?php

namespace Piwik\Plugins\ExternalVisitId\Columns;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Tracker\Action;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class ExternalVisitId extends VisitDimension
{
    protected $columnName = 'external_visit_id';
    protected $columnType = 'BIGINT NULL';

    /**
     * The installation is already implemented based on the $columnName and $columnType.
     * We overwrite this method to add an index on the new column too.
     *
     * @return array
     */
    public function install()
    {
        $changes = parent::install();
        $changes['log_visit'][] = 'ADD UNIQUE INDEX index_external_visit_id (external_visit_id)';

        return $changes;
    }

    /**
     * The onNewVisit method is triggered when a new visit is detected.
     * We fill the column with the externalVisitId.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return mixed The value to be saved in 'external_visit_id'. By returning boolean false no value will be saved.
     */
    public function onNewVisit(Request $request, Visitor $visitor, $action)
    {
        return $this->getExternalVisitIdFromRequest($request);
    }

    /**
     * This hook is executed when determining if an action is the start of a new visit or part of an existing one.
     * We force the creation of a new visit when the external_visit_id of the current action is both not null and
     * different from the visit's current external_visit_id.
     *
     * @param Request $request
     * @param Visitor $visitor
     * @param Action|null $action
     * @return bool
     */
    public function shouldForceNewVisit(Request $request, Visitor $visitor, Action $action = null)
    {
        $externalVisitId = $this->getExternalVisitIdFromRequest($request);

        // Keep usual behaviour if there is no external_visit_id
        if (false === $externalVisitId) {
            return false;
        }

        // Is this an existing visit?
        // Overflow would happen at 9223372036854775807 on 64-bit systems.
        $lastExternalVisitId = (int) Db::fetchOne(
            'SELECT external_visit_id FROM ' . Common::prefixTable('log_visit') . ' WHERE idvisit = ?',
            [$visitor->visitProperties->getProperty('idvisit')]
        );

        // Force new visit when we get the external_visit_id for the first time
        if (null === $lastExternalVisitId) {

            Common::printDebug(
                'Forcing new visit as there is no running visit with external_visit_id "' . $externalVisitId . '".'
            );

            return true;
        }

        // Force new visit when the external_visit_id changes within a Piwik visit
        if ($externalVisitId !== $lastExternalVisitId) {

            Common::printDebug(
                'Forcing new visit as external_visit_id "' . $externalVisitId
                . '" differs from the visit\'s current external_visit_id "' . $lastExternalVisitId . '".'
            );

            return true;
        }

        return false;
    }

    /**
     * Returns the external_visit_id when it exists and false otherwise.
     *
     * @param Request $request
     * @return bool|mixed
     */
    private function getExternalVisitIdFromRequest(Request $request)
    {
        return array_key_exists('external_visit_id', $request->getParams())
            ? $request->getParams()['external_visit_id']
            : false;
    }
}
