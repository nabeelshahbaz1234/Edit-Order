<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Meiko\EditOrder\Block\Order\Item\Renderer;

use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Exception;
use Magento\Catalog\Model\Product\OptionFactory;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditMemoItem;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Item as OrderItem;


/**
 * Order item render block
 *
 * @api
 * @since 100.0.2
 */
class DefaultRenderer extends Template
{
    /**
     * Magento string lib
     *
     * @var StringUtils
     */
    protected StringUtils $string;

    /**
     * @var OptionFactory
     */
    protected OptionFactory $_productOptionFactory;
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;
    /**
     * @var SessionFactory
     */
    private SessionFactory $session;
    /**
     * @var SubscriptionManagement
     */
    private SubscriptionManagement $subscriptionManagement;

    /**
     * @param Context $context
     * @param StringUtils $string
     * @param OptionFactory $productOptionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SessionFactory $session
     * @param SubscriptionManagement $subscriptionManagement
     * @param array $data
     */
    public function __construct(
        Context                  $context,
        StringUtils              $string,
        OptionFactory            $productOptionFactory,
        OrderRepositoryInterface $orderRepository,
        SessionFactory           $session,
        SubscriptionManagement   $subscriptionManagement,
        array                    $data = []
    )
    {
        $this->string = $string;
        $this->_productOptionFactory = $productOptionFactory;
        $this->orderRepository = $orderRepository;
        $this->session = $session;
        $this->subscriptionManagement = $subscriptionManagement;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve current order model instance
     *
     * @return Order
     */
    public function getOrder()
    {
        return $this->getOrderItem()->getOrder();
    }

    /**
     * Get order item.
     *
     * @return array|null
     */
    public function getOrderItem()
    {
        if ($this->getItem() instanceof OrderItem) {
            return $this->getItem();
        } else {
            return $this->getItem()->getOrderItem();
        }
    }

    /**
     * Get item.
     *
     * @return array|null
     */
    public function getItem()
    {
        return $this->_getData('item');
    }

    /**
     * Get item options.
     *
     * @return array
     */
    public function getItemOptions()
    {
        $result = [];
        $options = $this->getOrderItem()->getProductOptions();
        if ($options) {
            if (isset($options['options'])) {
                $result[] = $options['options'];
            }
            if (isset($options['additional_options'])) {
                $result[] = $options['additional_options'];
            }
            if (isset($options['attributes_info'])) {
                $result[] = $options['attributes_info'];
            }
        }
        return array_merge([], ...$result);
    }

    /**
     * Accept option value and return its formatted view
     *
     * @param mixed $optionValue
     * Method works well with these $optionValue format:
     *      1. String
     *      2. Indexed array e.g. array(val1, val2, ...)
     *      3. Associative array, containing additional option info, including option value, e.g.
     *          array
     *          (
     *              [label] => ...,
     *              [value] => ...,
     *              [print_value] => ...,
     *              [option_id] => ...,
     *              [option_type] => ...,
     *              [custom_view] =>...,
     *          )
     *
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getFormatedOptionValue($optionValue)
    {
        $optionInfo = [];
        if (is_array($optionValue)) {
            if (isset($optionValue['option_id'])) {
                $optionInfo = $optionValue;
                if (isset($optionInfo['value'])) {
                    $optionValue = $optionInfo['value'];
                }
            } elseif (isset($optionValue['value'])) {
                $optionValue = $optionValue['value'];
            }
        }

        if (isset($optionInfo['custom_view']) && $optionInfo['custom_view']) {
            $_default = ['value' => $optionValue];
            if (isset($optionInfo['option_type'])) {
                try {
                    $group = $this->_productOptionFactory->create()->groupFactory($optionInfo['option_type']);
                    return ['value' => $group->getCustomizedView($optionInfo)];
                } catch (Exception $e) {
                    return $_default;
                }
            }
            return $_default;
        }
        if (is_array($optionValue)) {
            $truncatedValue = implode("\n", $optionValue);
            $truncatedValue = nl2br($truncatedValue);
            return ['value' => $truncatedValue];
        } else {
            $truncatedValue = $this->filterManager->truncate($optionValue, ['length' => 55, 'etc' => '']);
            $truncatedValue = nl2br($truncatedValue);
        }

        $result = ['value' => $truncatedValue];

        if ($this->string->strlen($optionValue) > 55) {
            $result['value'] = $result['value']
                . ' ...';
            $optionValue = nl2br($optionValue);
            $result = array_merge($result, ['full_view' => $optionValue]);
        }

        return $result;
    }

    /**
     * Return sku of order item.
     *
     * @return string
     */
    public function getSku()
    {
        return $this->getItem()->getSku();
    }

    /**
     * Return product additional information block
     *
     * @return AbstractBlock
     */
    public function getProductAdditionalInformationBlock()
    {
        return $this->getLayout()->getBlock('additional.product.info');
    }

    /**
     * Prepare SKU
     *
     * @param string $sku
     * @return string
     */
    public function prepareSku($sku)
    {
        return $this->escapeHtml($this->string->splitInjection($sku));
    }

    /**
     * Return item unit price html
     *
     * @param OrderItem|InvoiceItem|CreditmemoItem $item child item in case of bundle product
     * @return string
     */
    public function getItemPriceHtml($item = null)
    {
        $block = $this->getLayout()->getBlock('item_unit_price');
        if (!$item) {
            $item = $this->getItem();
        }
        $block->setItem($item);
        return $block->toHtml();
    }

    /**
     * Set item.
     *
     * @param DataObject $item
     * @return $this
     */
    public function setItem(DataObject $item)
    {
        $this->setData('item', $item);
        return $this;
    }

    /**
     * Return item row total html
     *
     * @param OrderItem|InvoiceItem|CreditmemoItem $item child item in case of bundle product
     * @return string
     */
    public function getItemRowTotalHtml($item = null)
    {
        $block = $this->getLayout()->getBlock('item_row_total');
        if (!$item) {
            $item = $this->getItem();
        }
        $block->setItem($item);
        return $block->toHtml();
    }

    /**
     * Return the total amount minus discount
     *
     * @param OrderItem|InvoiceItem|CreditmemoItem $item
     * @return mixed
     */
    public function getTotalAmount($item)
    {
        $totalAmount = $item->getRowTotal()
            + $item->getTaxAmount()
            + $item->getDiscountTaxCompensationAmount()
            + $item->getWeeeTaxAppliedRowAmount()
            - $item->getDiscountAmount();

        return $totalAmount;
    }


    /**
     * @param $orderId
     * @return array
     */
    public function getOrderDetails($orderId)
    {
        $customerSession = $this->session->create();
        $customerid = $customerSession->getCustomerId();
        $order = $this->orderRepository->get($orderId);
        $incrementId = $order->getIncrementId();
        $subscriptionData = [];
        $subData = $this->subscriptionManagement->getSubscriptions((int)$customerid);
        foreach ($subData as $newKey) {
            if ($incrementId == $newKey->getData('order_increment_id')) {
                if (($newKey->getData('status') == 'Aktiv') || ($newKey->getData('status') == 'Pausiert') || ($newKey->getData('status') == 'Actif') || ($newKey->getData('status') == 'En pause')) {
                    $subscriptionData[] = $newKey;

                }

            }

        }
        $uniqueDaySubscriptionList = array_unique($subscriptionData, SORT_REGULAR);
        return $uniqueDaySubscriptionList;
    }

}
