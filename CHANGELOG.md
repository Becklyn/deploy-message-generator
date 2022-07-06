2.3.0
=====

* (improvement) Added the deploying user to the slack deploy message.
* (improvement) Added a link to be able to open the jira ticket from the deploy message.


2.2.0
=====

*   (internal) Replace CircleCI with GitHub Actions.
*   (internal) Add proper support for running in PHP 7.4 with Symfony 5 and PHP >8 with Symfony 6.
*   (feature) Added new Command `ticket:show-ticket-information`, which will display the ticket information from the given Commit Range.
*   (internal) Clarified Command description for `deployment:send-message`.


2.1.0
=====

*   (feature) Add a fancier CLI output.
*   (feature) Integrate into the Jira Deployments functionality. See https://developer.atlassian.com/cloud/jira/software/open-devops/.
*   (feature) Add new CLI flag `--mentions`, which adds a list of Slack Member Ids on top to the pre-defined list of members that are being mentioned upon Deployment. 
*   (feature) Validate entire `.deploy-message-generator.yaml` using the `symfony/config` system.
*   (feature) Added new `.deploy-message-generator.yaml` flags. See `examples/.deploy-message-generator.yaml` for a more thorough explanation and additional resources.
    - `urls.staging` (type `string` or  `string[]` — required): Allows you to define one or multiple URLs that are associated with a Staging environment.
    - `urls.production` (type `string` or  `string[]` — required): Allows you to define one or multiple URLs that are associated with a Production environment.
    - `mentions` (type `string[]` — optional): Allows you to define a list of Slack Member Ids, which are being mentioned whenever a Deployment Message is being sent.
*   (feature) Added new `~/.deploy-message-generator.env` flags. See `examples/.deploy-message-generator.env` for a more thorough explanation and additional resources.
    - `JIRA_JWT_TOKEN` (type `string` — optional): A pre-generated Jira JWT Token from an OAuth application that has access to the „Deployments” feature.
    - `JIRA_CLOUD_ID` (type `string` — required): This is the Jira Cloud Id that identifies your installation.
    - `JIRA_CLIENT_ID` (type `string` — required): An OAuth Client Id with access to the „Deployments” feature.
    - `JIRA_CLIENT_SECRET` (type `string` — required): The corresponding OAuth Client Secret for the above Client Id.
      See `examples/.deploy-message-generator.yaml` for a more thorough explanation and additional resources.
*   (improvement) General code clean up and refactorings.
*   (feature) Add new `urls.*` to the Slack Deployment Message as interactive buttons.


2.0.0
======

*   (bc) `ChatSystem:getChatMessage` was replaced in favour of `ChatSystem:getChatMessageThread`
*   (feature) Added `ChatSystem::getChatMessageThread` which will return a list of messages that can be sent as a thread
*   (feature) Added utility function `ChatSystem::sendThread` to handle mass sending messages as thread
*   (internal) The SendDeployMessageRunner will now automatically send threads


1.0.1
=====

*   (bug) Manually bootstrap app in order to support installing and running it via `composer global`.


1.0.0
=====

*   (feature) Initial release. 🙌
