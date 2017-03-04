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


#### Testing this plugin

Execute tests by running `./console tests:run ExternalVisitId --group ExternalVisitId`.
