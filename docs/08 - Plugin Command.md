# Plugin command
There are several Magento commands available inside the plugin, they all are inside the `Console` folder.

## Renew subscription command
The command `upstreampay:subscription:renew` is used to trigger a manual renew of the subscription for today's date.

To be eligible to a renewal, a subscription must:
- have the next payment date set to today's date.
- have the payment status set to `to_pay`.
- have the subscription status set to `disabled`.
- have the order id set to `null`.

This command is used for manual renew in case the cron is not working or if you'd prefer to run an external cron with
a script that would run this command daily.
