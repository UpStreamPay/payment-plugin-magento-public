# Plugin Events

To facilitate the integration of the module into the application, UpStreamPay plugin provides different events:

| Event                          | Description                                                   |
|--------------------------------|---------------------------------------------------------------|
| payment_usp_before_session     | Before generating a session                                   |
| payment_usp_after_session      | After generating a session                                    |
| payment_usp_before_webhook     | Webhook call (before processing)                              |
| payment_usp_after_webhook      | Webhook call (after processing)                               |
| payment_usp_write_log          | Before writing a log                                          |
| payment_usp_write_method       | Before writing payment method information (primary/secondary) |
| sales_order_usp_before_capture | Before a capture                                              |
| sales_order_usp_after_capture  | After a capture                                               |
| sales_order_usp_payment_error  | In case of payment errors                                     |
| order_sent_to_purse_event      | After the order has been received by Purse.                   |
| subscription_usp_price_update  | After a subscription price has changed.                       |
| cancel_purse_subscription      | To cancel a subscription not linked to an order.              |
| new_usp_subscription_saved     | After a new subscription has been saved.                      |

For more information on using events and observers, please refer to the official documentation:
https://developer.adobe.com/commerce/php/development/components/events-and-observers/

You can search the code to see where the events are dispatched in order to get a list of data sent with the event.
