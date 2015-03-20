<?php
/**
 * integer_net Magento Module
 *
 * @category IntegerNet
 * @package IntegerNet_FixBundleCreditMemo
 * @copyright  Copyright (c) 2012-2015 integer_net GmbH (http://www.integer-net.de/)
 * @license http://opensource.org/licenses/osl-3.0.php Open Software Licence 3.0 (OSL-3.0)
 * @author Soeren Zorn <sz@integer-net.de>
 */
class IntegerNet_FixBundleCreditMemo_Model_Service_Order extends Mage_Sales_Model_Service_Order
{
    /**
     * Prepare order creditmemo based on order items and requested params
     *
     * @param array $data
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function prepareCreditmemo($data = array())
    {
        $totalQty = 0;
        $creditmemo = $this->_convertor->toCreditmemo($this->_order);
        $qtys = isset($data['qtys']) ? $data['qtys'] : array();
        $this->updateLocaleNumbers($qtys);
        foreach ($this->_order->getAllItems() as $orderItem) {
            if (!$this->_canRefundItem($orderItem, $qtys)) {
                continue;
            }
            $item = $this->_convertor->itemToCreditmemoItem($orderItem);
            if ($orderItem->isDummy()) {
                $parentItem = $orderItem->getParentItem();
                if (is_object($parentItem) && $parentItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                    if (isset($qtys[$parentItem->getId()])) {
                        $qtyParent = (float)$qtys[$parentItem->getId()];
                    } else {
                        $qtyParent = $parentItem->getQtyToRefund();
                    }
                    $qty = (float)$orderItem->getQtyOrdered() / (float)$parentItem->getQtyOrdered();
                }
                if(!isset($qty)) {
                    $qty = 1;
                }
                $orderItem->setLockedDoShip(true);
            } else {
                if (isset($qtys[$orderItem->getId()])) {
                    $qty = (float) $qtys[$orderItem->getId()];
                } elseif (!count($qtys)) {
                    $qty = $orderItem->getQtyToRefund();
                } else {
                    continue;
                }
            }
            $totalQty += $qty;
            $item->setQty($qty);
            $creditmemo->addItem($item);
        }
        $creditmemo->setTotalQty($totalQty);

        $this->_initCreditmemoData($creditmemo, $data);

        $creditmemo->collectTotals();
        return $creditmemo;
    }

    /**
     * Prepare order creditmemo based on invoice items and requested requested params
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param array $data
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function prepareInvoiceCreditmemo($invoice, $data = array())
    {
        $totalQty = 0;
        $qtys = isset($data['qtys']) ? $data['qtys'] : array();
        $this->updateLocaleNumbers($qtys);

        $creditmemo = $this->_convertor->toCreditmemo($this->_order);
        $creditmemo->setInvoice($invoice);

        $invoiceQtysRefunded = array();
        foreach($invoice->getOrder()->getCreditmemosCollection() as $createdCreditmemo) {
            if ($createdCreditmemo->getState() != Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED
                && $createdCreditmemo->getInvoiceId() == $invoice->getId()) {
                foreach($createdCreditmemo->getAllItems() as $createdCreditmemoItem) {
                    $orderItemId = $createdCreditmemoItem->getOrderItem()->getId();
                    if (isset($invoiceQtysRefunded[$orderItemId])) {
                        $invoiceQtysRefunded[$orderItemId] += $createdCreditmemoItem->getQty();
                    } else {
                        $invoiceQtysRefunded[$orderItemId] = $createdCreditmemoItem->getQty();
                    }
                }
            }
        }

        $invoiceQtysRefundLimits = array();
        foreach($invoice->getAllItems() as $invoiceItem) {
            $invoiceQtyCanBeRefunded = $invoiceItem->getQty();
            $orderItemId = $invoiceItem->getOrderItem()->getId();
            if (isset($invoiceQtysRefunded[$orderItemId])) {
                $invoiceQtyCanBeRefunded = $invoiceQtyCanBeRefunded - $invoiceQtysRefunded[$orderItemId];
            }
            $invoiceQtysRefundLimits[$orderItemId] = $invoiceQtyCanBeRefunded;
        }


        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();

            if (!$this->_canRefundItem($orderItem, $qtys, $invoiceQtysRefundLimits)) {
                continue;
            }

            $item = $this->_convertor->itemToCreditmemoItem($orderItem);
            if ($orderItem->isDummy()) {
                $parentItem = $orderItem->getParentItem();
                if (is_object($parentItem) && $parentItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
                    if (isset($qtys[$parentItem->getId()])) {
                        $qtyParent = (float)$qtys[$parentItem->getId()];
                    } else {
                        $qtyParent = $parentItem->getQtyToRefund();
                    }
                    $qty = (float)$orderItem->getQtyOrdered() / (float)$parentItem->getQtyOrdered();
                }
                if(!isset($qty)) {
                    $qty = 1;
                }
            } else {
                if (isset($qtys[$orderItem->getId()])) {
                    $qty = (float) $qtys[$orderItem->getId()];
                } elseif (!count($qtys)) {
                    $qty = $orderItem->getQtyToRefund();
                } else {
                    continue;
                }
                if (isset($invoiceQtysRefundLimits[$orderItem->getId()])) {
                    $qty = min($qty, $invoiceQtysRefundLimits[$orderItem->getId()]);
                }
            }
            $qty = min($qty, $invoiceItem->getQty());
            $totalQty += $qty;
            $item->setQty($qty);
            $creditmemo->addItem($item);
        }
        $creditmemo->setTotalQty($totalQty);

        $this->_initCreditmemoData($creditmemo, $data);
        if (!isset($data['shipping_amount'])) {
            $order = $invoice->getOrder();
            $isShippingInclTax = Mage::getSingleton('tax/config')->displaySalesShippingInclTax($order->getStoreId());
            if ($isShippingInclTax) {
                $baseAllowedAmount = $order->getBaseShippingInclTax()
                    - $order->getBaseShippingRefunded()
                    - $order->getBaseShippingTaxRefunded();
            } else {
                $baseAllowedAmount = $order->getBaseShippingAmount() - $order->getBaseShippingRefunded();
                $baseAllowedAmount = min($baseAllowedAmount, $invoice->getBaseShippingAmount());
            }
            $creditmemo->setBaseShippingAmount($baseAllowedAmount);
        }

        $creditmemo->collectTotals();
        return $creditmemo;
    }
}
