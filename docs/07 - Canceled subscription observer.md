# Canceled subscription observer

After cancelling a subscription, an event is dispatched.

## With creditmemo (refund)

Event name : usp_creditmemo_subscription_canceled

Provided data :

    - subscription_id => entity_id of the subscription

    - subscription_identifier => identifier of the subscription
      (product sku and order increment id separated by an underscore)

    - creditmemo_id => id of the creditmemo

## Without creditmemo

Event name : usp_subscription_canceled

Provided data :

    - subscription_id => entity_id of the subscription

    - subscription_identifier => identifier of the subscription
      (product sku and order increment id separated by an underscore) 