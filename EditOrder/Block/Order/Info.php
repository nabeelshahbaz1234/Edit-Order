<?php
declare(strict_types=1);
namespace Meiko\EditOrder\Block\Order;
use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Block\Order\Info as SalesInfo;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address\Renderer as AddressRenderer;
/**
* @class Info
*/
class Info extends SalesInfo
{
    /**
     * @var string
     */
    protected $_template = 'Meiko_EditOrder::order/info.phtml';
    /**
     * @var RequestInterface
     */
    private RequestInterface $request;
    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;
    /**
     * @var TemplateContext
     */
    private TemplateContext $context;
    /**
     * @var Registry
     */
    private Registry $registry;
    /**
     * @var Order
     */
    private Order $order;
    /**
     * @var array
     */
    private array $data;
    /**
     * @var SessionFactory
     */
    private SessionFactory $session;
    /**
     * @var SubscriptionManagement
     */
    private SubscriptionManagement $subscriptionManagement;
    /**
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;
    /**
     * @param TemplateContext $context
     * @param Registry $registry
     * @param PaymentHelper $paymentHelper
     * @param AddressRenderer $addressRenderer
     * @param RequestInterface $request
     * @param UrlInterface $urlBuilder
     * @param Order $order
     * @param SessionFactory $session
     * @param SubscriptionManagement $subscriptionManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param array $data
     */
    public function __construct(
        TemplateContext          $context,
        Registry                 $registry,
        PaymentHelper            $paymentHelper,
        AddressRenderer          $addressRenderer,
        RequestInterface         $request,
        UrlInterface             $urlBuilder,
        Order                    $order,
        SessionFactory           $session,
        SubscriptionManagement   $subscriptionManagement,
        OrderRepositoryInterface $orderRepository,
        array                    $data = []
    )
    {
        parent::__construct($context, $registry, $paymentHelper, $addressRenderer, $data);
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->context = $context;
        $this->registry = $registry;
        $this->paymentHelper = $paymentHelper;
        $this->addressRenderer = $addressRenderer;
        $this->order = $order;
        $this->data = $data;
        $this->session = $session;
        $this->subscriptionManagement = $subscriptionManagement;
        $this->orderRepository = $orderRepository;
    }
    /**
     * @return string
     */
    public function editAddress(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/view/address',
            [
                'order_id' => (int)$orderId,
            ]
        );
    }
    /**
     * @return string
     */
    public function shippingMethod(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/view/shippingmethod',
            [
                'order_id' => (int)$orderId,
            ]
        );
    }
    /**
     * @return string
     */
    public function billingAddress(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/view/billingaddress',
            [
                'order_id' => (int)$orderId,
            ]
        );
    }
    /**
     * @return string
     */
    public function paymentMethod(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/view/paymentmethod',
            [
                'order_id' => (int)$orderId,
            ]
        );
    }
    /**
     * Retrieve current order model instance
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->coreRegistry->registry('current_order');
    }
    /**
     * @param $orderId
     * @return array|mixed
     */
    public function getOrderDetails($orderId): mixed
    {
        $customerSession = $this->session->create();
        $customerid = $customerSession->getCustomerId();
        $order = $this->orderRepository->get($orderId);
        $subData = $this->subscriptionManagement->getSubscriptions((int)$customerid);
        $subscriptionData = [];
        foreach ($subData as $newKey) {
            if ($order->getIncrementId() == $newKey->getData('order_increment_id')) {
                    $subscriptionData = $newKey;
            }
        }
        return $subscriptionData;
    }
}
