# Plugin Config

You can configure the module in the Adobe Commerce administration by going to **Stores > Configuration > Sales > Payment Methods > UpStreamPay**.

## General

![General](images/02-01.png)

| Parameter                   | Description                                                                                             |
|-----------------------------|---------------------------------------------------------------------------------------------------------|
| Enabled                     | Enable/Disable payment method                                                                           |
| Title                       | Label displayed in checkout                                                                             |
| Error message               | Generic error message to display on frontend in case of error.                                          |
| New order status            | Initial status for new orders                                                                           |
| Payment action              | **Authorize only**<br/>**Immediate capture**<br/>**Authorize only** See below                           |
| Debug mode                  | **Disabled**: Logs transactions only. <br/>**Simple**: Logs events. <br/>**Debug**: Logs all API calls. |
| API mode                    | Use **sandbox** for development environment.<br/>Use **production** for production environment.         |
| Customer TIN attribute code | Customer Attribute (non static) name containing the Tax Identification Number                           |

### Payments modes

**Authorize Only**: The plugin will not perform payment capture. This will remain the responsibility of the seller.

**Immediat Capture**: To use the Immediat Capture (or Direct Debit) mode, you need to modify ALL payment methods from the UpStreamPay administration to Immediat Capture mode. The plugin does not perform Immediat Capture itself.

**Order**: The plugin will automatically perform the payment capture upon invoicing.

### Currency used

By default, the plugin uses the currency configured at the Website level. It is not possible to have stores with different currencies.

If you want to have different currencies in the same Adobe Commerce application, you will need to create multiple Websites.

### Debug mode

**Disabled**: Log only transaction and payment, searchable in the administration at: **Sales > Payments Listing** and **Sales > Transactions listing**

**Simple**: Log all payment and transaction events in the `upstreampay_notify.log` file.

> :warning: To be used with caution in production environments.

**Debug**: Log all API calls in the log files and save them in `upstreampay_api.log` file. 
> :warning: Generates a large amount of data. To be used with extreme caution in production environments.

## API

![API](images/02-02.png)

| Parameter     | Description                              |
|---------------|------------------------------------------|
| Entity ID     | Entity ID provided by UpStreamPay.       |
| Client ID     | Client ID provided by UpStreamPay.       |
| Client secret | Client Secret provided by UpStreamPay.   |
| API key       | API Key provided by UpStreamPay.         |
| Widget url    | URL using for include widget if specific |

## 3D Secure settings

![3DS](images/02-03.png)

| Parameter                          | Description                                                   |
|------------------------------------|---------------------------------------------------------------|
| 3DS exemption attribute code       | Quote attribute name using for 3DS exemption                  |
| Challenge indicator attribute code | Quote attribute name using for 3DS challenge indicator (rate) |

These parameters represent the names of the attributes (non static) to be transmitted to the payment provider to exempt a
cart or provide a note. The creation and content of these attributes are the responsibility of the store if it has them.