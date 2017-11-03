# WinMan Bridge for Magento 2

**Current version: 1.0.0**

The WinMan Bridge for Magento 2 makes integration between a WinMan application and a Magento installation possible. The Bridge can populate the following in Magento from WinMan:

- Products.
- Product Images.
- Product Categories.
- Product Stock Levels.
- Customers (actually CRM Contacts in WinMan).

The Bridge can also create new CRM Contacts in WinMan from Magento, and send Sales Orders from Magento to WinMan.

The Bridge utilises Magento's cron scheduler in order to keep data up-to-date. It can be configured to run at set intervals, fetching only information from WinMan that has changed since the last run, or it can be set to fetch all information from WinMan (i.e. run full updates).

The Bridge populates data in Magento at a website level (NOT store level), so can be configured to communicate with multiple instances of the WinMan REST API.

## Pre-requisites

- Magento v2.1 or higher (tested and developed for v2.1.7).
- At least one instance of the WinMan REST API v1.0.0.

The WinMan REST API has its own set of pre-requisites. Please see the WinMan REST API knowledgebase for more information.

## Limitations

The following WinMan features are not currently supported by the WinMan Bridge, but are planned for future versions:

- Price Lists.
- Discounts.
- Promotions.
- Multiple Tax Codes for Customers.
- Product Configurator.