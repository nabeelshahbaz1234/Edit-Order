<?php
declare(strict_types=1);


namespace Meiko\EditOrder\ViewModel;

use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Payment\Model\Config as paymentConfig;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Shipping\Model\Config;
use Amasty\RecurringPayments\Model\Subscription\Mapper\BillingFrequencyLabelMapper;
use Amasty\RecurringPayments\Model\Product;
use Amasty\RecurringPayments\Model\Amount;
use Amasty\RecurringPayments\Service\GetCurrentProductService;
use Amasty\RecurringPayments\Model\Config\Source\BillingFrequencyUnit;


class OrderExportView implements ArgumentInterface
{
    protected Config $shippingConfig;
    /** @var null|OrderInterface */
    protected ?OrderInterface $order = null;
    /** @var RequestInterface */
    protected RequestInterface $request;
    /** @var OrderRepositoryInterface */
    protected OrderRepositoryInterface $orderRepository;
    /** @var TimezoneInterface */
    protected $timezone;
    /** @var UrlInterface */
    protected UrlInterface $urlBuilder;
    /** @var PageConfig */
    protected PageConfig $pageConfig;
    protected RateRequest $rateRequest;
    protected ScopeConfigInterface $scopeConfig;
    protected paymentConfig $paymentConfig;
    private SessionFactory $session;
    private SubscriptionManagement $subscriptionManagement;
    private BillingFrequencyLabelMapper $billingFrequencyLabelMapper;
    private Product $product;
    private Amount $amount;
    private BillingFrequencyUnit $billingFrequencyUnit;

    public function __construct(
        RequestInterface         $request,
        OrderRepositoryInterface $orderRepository,
        TimezoneInterface        $timezone,
        UrlInterface             $urlBuilder,
        PageConfig               $pageConfig,
        Config                   $shippingConfig,
        RateRequest              $rateRequest,
        ScopeConfigInterface     $scopeConfig,
        paymentConfig            $paymentConfig,
        SessionFactory           $session,
        SubscriptionManagement   $subscriptionManagement,
        BillingFrequencyLabelMapper $billingFrequencyLabelMapper,
        Product              $product,
        Amount              $amount,
        BillingFrequencyUnit   $billingFrequencyUnit
    ) {
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->timezone = $timezone;
        $this->urlBuilder = $urlBuilder;
        $this->pageConfig = $pageConfig;
        $this->shippingConfig = $shippingConfig;
        $order = $this->getOrder();
        if ($order) {
            $this->pageConfig->getTitle()->set(__('Order # %1', $order->getRealOrderId()));
        }
        $this->rateRequest = $rateRequest;
        $this->scopeConfig = $scopeConfig;
        $this->paymentConfig = $paymentConfig;
        $this->session = $session;
        $this->subscriptionManagement = $subscriptionManagement;
        $this->billingFrequencyLabelMapper = $billingFrequencyLabelMapper;
        $this->product = $product;
        $this->amount = $amount;
        $this->billingFrequencyUnit = $billingFrequencyUnit;
    }


    public function getOrder(): ?OrderInterface
    {
        if ($this->order === null) {
            $orderId = (int)$this->request->getParam('order_id');
            if (!$orderId) {
                return null;
            }

            try {
                $order = $this->orderRepository->get($orderId);
            } catch (NoSuchEntityException $e) {
                return null;
            }
            $this->order = $order;
        }

        return $this->order;
    }

    public function getOrderDetails($orderId)
    {
        $customerSession = $this->session->create();
        $customerid = $customerSession->getCustomerId();
        $order = $this->orderRepository->get($orderId);
        $incrementID = $order->getIncrementId();
        $data = $order->getAllItems();
        $subData = $this->subscriptionManagement->getSubscriptions((int)$customerid);
        foreach ($subData as $newKey) {
            if ($order->getIncrementId() == $newKey->getData('order_increment_id')) {
                if (($newKey->getData('status') == 'Aktiv') || ($newKey->getData('status') == 'Actif')) {
                    return $newKey;

                }
                else{
                    return $newKey;
                }


            }
        }
    }


    public function getOrderViewUrl(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }

        return $this->urlBuilder->getUrl(
            'sales/order/view',
            [
                'order_id' => $order->getEntityId()
            ]
        );
    }

    /**
     * @return string
     */
    public function getSaveUrl(): string
    {
        return $this->urlBuilder->getUrl('order_edit/submit/save');
    }

    public function getSaveBundleUrl(): string
    {
        return $this->urlBuilder->getUrl('bundle_name/bundlename/editBundleName');
    }

    /**
     * @return string
     */
    public function editAddressUrl(): string
    {
        return $this->urlBuilder->getUrl('order_edit/submit/editshippingaddress');
    }
    public function editDeliveryUrl(): string
    {
        return $this->urlBuilder->getUrl('delivery/deliveryschedule/editdelivery');
    }
    public function editBillingAddressUrl(): string
    {
        return $this->urlBuilder->getUrl('order_edit/submit/editbillingaddress');
    }

    public function editShippingMethodUrl(): string
    {
        return $this->urlBuilder->getUrl('order_edit/submit/editshippingmethod');
    }

    public function editPaymentMethodUrl(): string
    {
        return $this->urlBuilder->getUrl('order_edit/submit/editpaymentmethod');
    }
    public function getDeleteAction($itemId)
    {

        return $this->urlBuilder->getUrl('order_delete/delete/OrderItem/item_id/' . $itemId);
    }
    public function getShippingMethods(): array
    {
        $activeCarriers = $this->shippingConfig->getActiveCarriers();

        foreach ($activeCarriers as $carrierCode => $carrierModel) {
            $options = array();

            if ($carrierMethods = $carrierModel->getAllowedMethods()) {
                foreach ($carrierMethods as $methodCode => $method) {
                    $code = $carrierCode . '_' . $methodCode;
                    $options[] = array('value' => $code, 'label' => $method,);
                }
                $carrierTitle = $this->scopeConfig
                    ->getValue('carriers/' . $carrierCode . '/title');
            }

            $methods[] = array('value' => $options, 'label' => $carrierTitle);
        }

        return $methods;
    }

    public function getPaymentMethod(): array
    {
        $payments = $this->paymentConfig->getActiveMethods();
        $methods = array();
        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = $this->scopeConfig
                ->getValue('payment/' . $paymentCode . '/title');
            $methods[$paymentCode] = array(
                'label' => $paymentTitle,
                'value' => $paymentCode
            );
        }
        return $methods;
    }

    /**
     * @throws LocalizedException
     */
    public function payment(): string
    {
        $payment = $this->order->getPayment();
        return $payment->getMethodInstance()->getTitle();
    }







}
