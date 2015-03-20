IntegerNet_FixBundleCreditMemo
==============================

Fix Magento Bug: Wrong quantity calculation if you want to refund a bundle product using a credit memo

Facts
-----
- version: 1.0.0
- extension on [GitHub](https://github.com/integer-net/IntegerNet_FixBundleCreditMemo)

Description
-----------
Currently there is a Magento bug, which will miscount your child products of a bundle product, if you put your product back to stock after refunding it using a credit memo.
Steps to reproduce:
1. Create a bundle product, which contains twice or more of the same simple product.
2. Create an order, which contains one or more of this bundle product.
3. Create an invoice for this order.
4. Refund the bundle product using a credit memo. Make sure you have ticked „Back to stock“.
5. Check the stock quantity. It is less than it should have been.

Requirements
------------
- PHP >= 5.2.0
- PHP <= 5.5.x

Installation Instructions
-------------------------
1. Install the extension by copying the `app` directory into your Magento document root.
2. Clear the cache.

Uninstallation
--------------
1. Remove all extension files from your Magento installation
 - app/code/community/IntegerNet/FixBundleCreditMemo
 - app/etc/modules/IntegerNet_FixBundleCreditMemo.xml
2. Clear the cache.

Support
-------
If you have any issues with this extension, open an issue on [GitHub](https://github.com/integer-net/IntegerNet_FixBundleCreditMemo/issues).

Contribution
------------
Any contribution is highly appreciated. The best way to contribute code is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

Developer
---------
Soeren Zorn

WWW [http://www.integer-net.de/](http://www.integer-net.de/)  
Twitter [@integer_net](https://twitter.com/integer_net)

Licence
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2013-2015 integer_net GmbH
