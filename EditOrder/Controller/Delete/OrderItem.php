<?php
declare(strict_types=1);
namespace Meiko\EditOrder\Controller\Delete;
use Amasty\RecurringPayments\Model\Repository\SubscriptionRepository;
use Amasty\RecurringPayments\Model\Subscription;
use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\InvoiceFactory;
/**
* @class OrderItem
*/
class OrderItem implements ActionInterface
{
    /**
     * @var RequestInterface
     */
    public RequestInterface $requestInterface;
    /**
     * @var ManagerInterface
     */
    public ManagerInterface $messageManager;
    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;
    /**
     * @var Redirect
     */
    protected Redirect $resultRedirectFactory;
    /**
     * @var Registry
     */
    protected Registry $registry;
    /**
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;
    /**
     * @var UrlInterface
     */
    private UrlInterface $url;
    /**
     * @var OrderItemRepositoryInterface
     */
    private OrderItemRepositoryInterface $orderItemRepository;
    /**
     * @var Session
     */
    private Session $session;
    /**
     * @var SubscriptionManagement
     */
    private SubscriptionManagement $subscriptionManagement;
    /**
     * @var SubscriptionRepository
     */
    private SubscriptionRepository $payRepositoryInterface;
    /**
     * @var Subscription
     */
    private Subscription $subscription;
    /**
     * @var InvoiceFactory
     */
    private InvoiceFactory $invoiceFactory;
    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $requestInterface
     * @param Redirect $resultRedirectFactory
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param ManagerInterface $messageManager
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param Registry $registry
     * @param Session $session
     * @param SubscriptionManagement $subscriptionManagement
     * @param SubscriptionRepository $payRepositoryInterface
     * @param Subscription $subscription
     * @param InvoiceFactory $invoiceFactory
     */
    public function __construct(
        OrderRepositoryInterface     $orderRepository,
        RequestInterface             $requestInterface,
        Redirect                     $resultRedirectFactory,
        ResultFactory                $resultFactory,
        UrlInterface                 $url,
        ManagerInterface             $messageManager,
        OrderItemRepositoryInterface $orderItemRepository,
        Registry                     $registry,
        Session                      $session,
        SubscriptionManagement       $subscriptionManagement,
        SubscriptionRepository       $payRepositoryInterface,
        Subscription                 $subscription,
        InvoiceFactory               $invoiceFactory
    )
    {
        $this->orderRepository = $orderRepository;
        $this->requestInterface = $requestInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->orderItemRepository = $orderItemRepository;
        $this->registry = $registry;
        $this->session = $session;
        $this->subscriptionManagement = $subscriptionManagement;
        $this->payRepositoryInterface = $payRepositoryInterface;
        $this->subscription = $subscription;
        $this->invoiceFactory = $invoiceFactory;
    }
    /**
     * Edit shipping address
     * @return ResponseInterface|ResultInterface
     * @throws Exception
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $this->registry->register('isSecureArea', true);
        $paramData = $this->requestInterface->getParam('item_id');
        $pattern = '/-/';
        $paramData = preg_split($pattern, $paramData);
        $itemId = $paramData[0];
        $orderId = $paramData[1];
        if (!$itemId) {
            $this->messageManager->addErrorMessage(__('Order ID is missing.'));
            return $resultRedirect->setUrl($this->url->getUrl('sales/order/history'));
        }
        $order = $this->orderRepository->get($orderId);
        $lastInvoiceData = $this->getLastInvoiceArray($orderId);
        if (isset($lastInvoiceData['entity_id'])) {
            $invoice = $this->invoiceFactory->create()->load($lastInvoiceData['entity_id']);
            $subTotal = 0;
            foreach ($invoice->getAllItems() as $invoiceItems) {
                if ($invoiceItems->getOrderItemId() == $itemId) {
                    $subTotal = $invoiceItems->getRowTotal();
                    $invoiceItems->delete($itemId);
                    break;
                }
            }
            $baseSubTotal = $invoice->getBaseSubtotal() - $invoice->getBaseDiscountAmount() - $subTotal;
            $subtotal = $invoice->getSubtotal() - $invoice->getDiscountAmount() - $subTotal;
            $baseGrandTotal = $invoice->getBaseGrandTotal() - $subTotal;
            $grandTotal = $invoice->getGrandTotal() - $subTotal;
            $invoice->setBaseSubtotal($baseSubTotal);
            $invoice->setSubtotal($subtotal);
            $invoice->setBaseGrandTotal($baseGrandTotal);
            $invoice->setGrandTotal($grandTotal);
            // Save the updated invoice
            $invoiceResourceModel = $this->invoiceFactory->create()->getResource();
            $invoiceResourceModel->save($invoice);
        }
        if (count($order->getItems()) == 1) {
            $customerId = $this->session->getCustomerId();
            $subscriptionCollections = $this->subscriptionManagement->getSubscriptions((int)$customerId);
            foreach ($subscriptionCollections as $key) {
                if ($key['subscription']['order_id'] == $orderId) {
                    if ($key['subscription']['status'] != 'canceled') {
                        $subscriptionsList[] = $key['subscription'];
                    }
                    if (isset($subscriptionsList)) {
                        foreach ($subscriptionsList as $list) {
                            $list->setStatus('Canceled');
                            $this->payRepositoryInterface->save($list);
                        }
                    }
                }
            }
            $subscriptionCollection = $this->subscription->getCollection();
            foreach ($subscriptionCollection as $key) {
                /** Delete the subscription */
                if ($key->getData('order_id') == $orderId) {
                    $subscriptionId = $key->getData('id');
                    $subscription = $this->payRepositoryInterface->getById($subscriptionId);
                    $this->payRepositoryInterface->delete($subscription);
                }
            }
            $this->orderItemRepository->deleteById($itemId);
            $this->orderRepository->delete($order);
        } else {
            try {
                $item = $order->getItemById($itemId);
                $order->setGrandTotal($order->getGrandTotal() - $item->getRowTotal());
                $order->setBaseGrandTotal($order->getBaseGrandTotal() - $item->getBaseRowTotal());
                $order->setSubtotal($order->getSubtotal() - $item->getRowTotal());
                $order->setBaseSubtotal($order->getBaseSubtotal() - $item->getBaseRowTotal());
                $order->setSubtotalInclTax($order->getSubtotalInclTax() - $item->getRowTotalInclTax());
                $order->setBaseSubtotalInclTax($order->getBaseSubtotalInclTax() - $item->getBaseRowTotalInclTax());
                $order->setTaxAmount($order->getTaxAmount() - $item->getTaxAmount());
                $order->setBaseTaxAmount($order->getBaseTaxAmount() - $item->getBaseTaxAmount());
                $order->setDiscountAmount($order->getDiscountAmount() - $item->getDiscountAmount());
                $order->setBaseDiscountAmount($order->getBaseDiscountAmount() - $item->getBaseDiscountAmount());
                $order->setShippingAmount($order->getShippingAmount() - $item->getBaseShippingAmount());
                $order->setBaseShippingAmount($order->getBaseShippingAmount() - $item->getBaseShippingAmount());
                $order->save();
                $this->orderItemRepository->deleteById($itemId);
                $this->messageManager->addSuccessMessage(__('your order && subscription Items have been deleted successfully.'));
                return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('Order not found:
            Something went wrong
            %1', $e->getMessage()));
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage(__('Error updating Billing address: %1', $e->getMessage()));
            }
        }
        $response['redirectUrlSubscription'] = $this->url->getUrl('amasty_recurring/product/subscriptionslistbyfrequencyunit/');
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($response);
        $this->messageManager->addSuccessMessage(__('your order && subscription Items have been deleted successfully.'));
        return $resultJson;
    }
    /**
     * @param $orderId
     * @return mixed
     */
    public function getLastInvoiceArray($orderId): mixed
    {
        $invoiceCollection = $this->invoiceFactory->create()->getCollection()
            ->addAttributeToFilter('order_id', $orderId)
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1);
        $lastInvoice = $invoiceCollection->getFirstItem();
        return $lastInvoice->getData();
    }
}

