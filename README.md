Deploy message Generator
========================

A generator that produces beautiful deploy messages and integrates nicely with Slack and Jira.

Installation
------------

```bash
composer global require becklyn/deploy-message-generator
```

Required Environment Variables
------------------------------

| Variable Name            | Purpose |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| SLACK_ACCESS_TOKEN       | Token needed to send messages to Slack                                                                                   |
| SLACK_DEPLOYMENT_CHANNEL | The channel id where deployment messages should be sent to                                                               |
| SLACK_TEST_CHANNEL       | The channel id where the tests in this package may sent messages to                                                      |
|                          |                                                                                                                          |
| JIRA_DOMAIN              | The base domain where the Jira installation lives. e.g. `example.atlassian.net`                                          |
| JIRA_USER                | The user used to lookup the ticket information                                                                           |
| JIRA_ACCESS_TOKEN        | The access token for the `JIRA_USER`                                                                                     | 
| JIRA_TEST_TICKET         | A test ticket with has the summary `TEST TICKET FOR deploy-message-generator` which is used by the Jira integration test |
