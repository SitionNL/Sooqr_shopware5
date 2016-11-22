SitionSooqr
===========

Generates a xml for [Sooqr](https://www.sooqr.com/)

## Overview

With this module you can generate a xml feed for Sooqr. 
Sooqr can read this feed and generates a fast search for your shop.
You can then enable this feed in your Shopware shop.

## Urls

** Installation url: **
`http://<domain>/<subshop_virtual_url>/frontend/sition_sooqr/installation`

** Url to xml feed: **
`http://<domain>/<subshop_virtual_url>/frontend/sition_sooqr/xml`

## Installation

1. Fill in the form to apply for a Sooqr account at [Sooqr signup](https://www.sooqr.com/sign-up/sooqr-commerce-search)
2. Let Sooqr contact you, and get an account for Sooqr, provide the installation url to Sooqr
3. Login into the Sooqr account
4. Get the account identifier from the current project, to do that, click on the current project at the top of the screen. In the dropdown you will see the account identifier. (It begins with `SQ-`, like `SQ-101101-1`)

5. Login the Shopware backend of your Shopware shop
6. Install and activate the [SitionSooqr plugin](http://store.shopware.com/en/search?sSearch=sitio79088666357)
7. Go to the SitionSooqr plugin configuration (Configuration - Basic settings - Additional settings - SitionSooqr)
8. Put the account identifier from Sooqr in the corresponding box
9. Provide the category parent, this is the id of the root category for the articles. This is needed to get the correct sub-tree of the categories. The main category is regarded as the first level under the category parent.
10. Type 'yes' in 'Override searchbar' to enable searching with Sooqr on the Shopware shop

11. Get the url to the xml feed for the correct subdomain. (To get the virtual url of the subdomain, go to `Configuration - Basic settings - Shop settings - Shops`)
12. Login info the Sooqr account
13. Add datafeed in `My Data - My Datafeeds`
