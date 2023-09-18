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

For more information on using events and observers, please refer to the official documentation:
https://developer.adobe.com/commerce/php/development/components/events-and-observers/
