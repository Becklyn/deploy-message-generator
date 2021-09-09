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

| Variable Name | Purpose |
| --- | --- |
| SLACK_ACCESS_TOKEN | Token needed to send messages to slack |
| SLACK_DEPLOYMENT_CHANNEL | The channel id where deployment messages should be sent to |
| SLACK_TEST_CHANNEL | The channel id where the tests in this package may sent messages to |
