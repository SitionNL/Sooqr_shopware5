Roadmap SitionSooqr
===================

#### M - Add sooqr javascript to search bar via plugin
To use the sooqr api in the webshop, a small sooqr script needs to be injected into the site.

#### C - Generate xml in a cronjob
The xml is generated after x time, to make this faster the xml could already be generated in a cronjob, so the requests are faster.

#### C - Fix Gzipping of the xml
In the specification of Sooqr, it is stated that when a xml is big, the xml should be offered in gzipped form.
The current code for this doesn't work.
