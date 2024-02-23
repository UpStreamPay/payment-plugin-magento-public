# Cancel subscription event

In order to cancel a subscription, you must dispatch the following event:

    event id => cancel_purse_subscription

The event must be called with the id of the subscription to cancel.

Example :

    $this->eventManager->dispatch('cancel_purse_subscription', ['subscriptionId' => $subscriptionId]);
