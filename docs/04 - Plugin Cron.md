# Plugin Cron

You can configure the cron task features in the Adobe Commerce administration by going to **Stores > Configuration > Sales > Payment Methods > UpStreamPay > Recurring payment**.

## General

### Subscriptions cron frequency
...

### Subscriptions retry cron frequency
...

## Cron group

You can configure the cron group by going to **Stores > Configuration > Advanced > System**

Under this tab you can retrieve cron groups configuration :
![CRON_GROUP](images/04-01.png)

| Parameter                  | Description                                                                                                      |
|----------------------------|------------------------------------------------------------------------------------------------------------------|
| `schedule_generate_every`  | Frequency (in minutes) that schedules are written to the `cron_schedule` table.                                  |
| `schedule_ahead_for`       | Time (in minutes) in advance that schedules are written to the `cron_schedule` table.                            |
| `schedule_lifetime`        | Window of time (in minutes) that a cron job must start or the cron job is considered missed (“too late” to run). |
| `history_cleanup_every`    | Time (in minutes) that cron history is kept in the database.                                                     |
| `history_success_lifetime` | Time (in minutes) that the record of successfully completed cron jobs is kept in the database.                   |
| `history_failure_lifetime` | Time (in minutes) that the record of failed cron jobs is kept in the database.                                   |
| `use_separate_process`     | Run this cron group’s jobs in a separate php process                                                             |