# Piwik ExternalVisitId Plugin

## Description

By default, [Piwik's Tracking HTTP API](http://developer.piwik.org/api-reference/tracking-api) does not accept to pass 
an `external_visit_id`. Whether or not a new visit will be created within Piwik depends on various logic, e.g.:

* A visitor starts a new visit after a period of inactivity (30 minutes by default, see 
[here](http://piwik.org/faq/general/faq_36/))
* A visitor comes from a different marketing campaign (enabled by default, see 
[here](https://piwik.org/faq/how-to/faq_19616/))
* A visitor comes from a different HTTP referrer (disabled by default, see 
[here](https://piwik.org/faq/how-to/faq_19616/))
* A user ID is being passed, see [here](https://piwik.org/docs/user-id/#how-requests-with-a-user-id-are-tracked)

You can only force Piwik [to create a new visit](https://piwik.org/faq/how-to/faq_187/) by passing `&new_visit=1` to 
the Tracking HTTP API. 

This plugin extends the Piwik Tracker API and allows external applications to pass an `external_visit_id` that is added 
as a [VisitDimension](https://developer.piwik.org/guides/dimensions) to the Piwik visit. Whenever there is no Piwik
visit with the `external_visit_id`, a new visit is being created.


#### Disabling Piwik's default logic for creating new visits

To make Piwik visits exactly match the `external_visit_id`, Piwik and its plugins must be configured accordingly by 
setting in `config/config.ini.php`:  

    [Tracker]
    visit_standard_length = 86400
    create_new_visit_when_campaign_changes = 0
    create_new_visit_when_website_referrer_changes = 0

As Piwik merges and splits visits also based on 
[user IDs](https://piwik.org/docs/user-id/#how-requests-with-a-user-id-are-tracked), you are not allowed to pass them
any more.

You might also need to disable external plugin functionality that forces the creation of new visits, e.g. in 
[Piwik AOM](https://github.com/advanced-online-marketing/AOM) by disabling the config option "Create new visit when 
campaign changes". 

The worst thing is that you must manually override `Piwik\Tracker\VisitorRecognizer.findKnownVisitor()` as there is
currently no hook/event or something similar to do so:

    /**
     * This method has been manually overridden by the ExternalVisitId plugin
     * 
     * @param $configId
     * @param VisitProperties $visitProperties
     * @param Request $request
     * @return bool
     * @throws \Exception
     */
    public function findKnownVisitor($configId, VisitProperties $visitProperties, Request $request)
    {
        // Make sure that both are supplied, visitorId and visitId!
        if (!$request->getVisitorId()) {
            throw new \Exception('VisitorRecognizer.findKnownVisitor() override requires visitorId in request.');
        }
        $idVisitor = $request->getVisitorId();

        $requestParams = $request->getParams();
        if (!isset($requestParams['external_visit_id'])) {
            throw new \Exception('VisitorRecognizer.findKnownVisitor() override requires externalVisitId in request.');
        }
        $externalVisitId = $requestParams['external_visit_id'];

        Common::printDebug(
            'Matching visitor based on visitorId ' . hexdec(bin2hex($idVisitor))
            . ' and visit based on externalVisitId ' . $externalVisitId . '.'
        );

        $visitProperties->setProperty('idvisitor', $idVisitor);
        $persistedVisitAttributes = $this->getVisitFieldsPersist();

        // We must not care about system config, visit_last_action_time etc. as only the externalVisitId is relevant!
        $visitRow = \Piwik\Tracker::getDatabase()->fetch(
            'SELECT ' . implode(', ', $persistedVisitAttributes) . ' FROM ' . Common::prefixTable('log_visit')
            . ' WHERE idsite = ? AND idvisitor = ? AND external_visit_id = ? '
            . ' ORDER BY visit_last_action_time DESC LIMIT 1',
            [
                $request->getIdSite(),
                $idVisitor,
                $externalVisitId,
            ]
        );


        if ($visitRow && count($visitRow) > 0) {

            // These values will be used throughout the request
            foreach ($persistedVisitAttributes as $field) {
                $visitProperties->setProperty($field, $visitRow[$field]);
            }

            // TODO: Compare if later/earlier?!
            $visitProperties->setProperty('visit_last_action_time', strtotime($visitRow['visit_last_action_time']));
            $visitProperties->setProperty('visit_first_action_time', strtotime($visitRow['visit_first_action_time']));

            // Custom Variables copied from Visit in potential later conversion
            if (!empty($numCustomVarsToRead)) {
                for ($i = 1; $i <= $numCustomVarsToRead; $i++) {
                    if (isset($visitRow['custom_var_k' . $i])
                        && strlen($visitRow['custom_var_k' . $i])
                    ) {
                        $visitProperties->setProperty('custom_var_k' . $i, $visitRow['custom_var_k' . $i]);
                    }
                    if (isset($visitRow['custom_var_v' . $i])
                        && strlen($visitRow['custom_var_v' . $i])
                    ) {
                        $visitProperties->setProperty('custom_var_v' . $i, $visitRow['custom_var_v' . $i]);
                    }
                }
            }

            Common::printDebug(
                'Matched Piwik visit ' . $visitRow['idvisit'] . ' based on visitorId ' . hexdec(bin2hex($idVisitor))
                . ' and externalVisitId ' . $externalVisitId . '.'
            );

            return true;
        }

        Common::printDebug('The visit could not be matched with an existing one...');

        return false;
    }


#### Testing this plugin

Execute tests by running `./console tests:run ExternalVisitId --group ExternalVisitId`.


## Installation on Ubuntu

As this plugin is not available on Piwik's marketplace, it must be installed manually.

From Piwik's plugin directory:

    sudo wget https://github.com/advanced-online-marketing/ExternalVisitId/archive/master.zip && sudo unzip master.zip && sudo rm master.zip && sudo chown -R www-data:www-data ExternalVisitId-master
    sudo rm -Rf ExternalVisitId && sudo mv ExternalVisitId-master ExternalVisitId
