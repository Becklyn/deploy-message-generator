2.0.0
======
*   (bc) `ChatSystem:getChatMessage` was replaced in favor of `ChatSystem:getChatMessageThread`
*   (feature) Added `ChatSystem::getChatMessageThread` which will return a list of messages that can be sent as a thread
*   (feature) Added utility function `ChatSystem::sendThread` to handle mass sending messages as thread
*   (internal) The SendDeployMessageRunner will now automatically send threads

1.0.1
=====

*   (bug) Manually bootstrap app in order to support installing and running it via `composer global`.


1.0.0
=====

*   (feature) Initial release. 🙌
