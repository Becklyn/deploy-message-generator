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

These variables can also be defined by placing `.deploy-message-generator.env` in your `$Home` (or `%USERPROFILE%` for Windows) directory.

| Variable Name            | Purpose |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------ |
| SLACK_ACCESS_TOKEN       | Token needed to send messages to Slack                                                                                   |
|                          |                                                                                                                          |
| JIRA_USER_EMAIL          | The email address of the user used to lookup the ticket information                                                      |
| JIRA_ACCESS_TOKEN        | The access token for the `JIRA_USER_EMAIL`                                                                               | 



Configuration
-------------

The generator is configured using the `.deploy-message-generator.yaml` file. Check the `examples` dir to learn more.
