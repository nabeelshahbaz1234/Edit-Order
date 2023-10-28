<?php

declare(strict_types=1);

namespace Meiko\EditOrder\Controller\Submit;

use Amasty\RecurringPayments\Model\ResourceModel\Subscription\CollectionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * @class EditPaymentMethod
 */
class EditPaymentMethod implements ActionInterface
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
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;
    /**
     * @var UrlInterface
     */
    private UrlInterface $url;
    /**
     * @var OrderPaymentRepositoryInterface
     */
    private OrderPaymentRepositoryInterface $paymentRepository;
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $requestInterface
     * @param Redirect $resultRedirectFactory
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param ManagerInterface $messageManager
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        OrderRepositoryInterface        $orderRepository,
        RequestInterface                $requestInterface,
        Redirect                        $resultRedirectFactory,
        ResultFactory                   $resultFactory,
        UrlInterface                    $url,
        ManagerInterface                $messageManager,
        OrderPaymentRepositoryInterface $paymentRepository,
        CollectionFactory               $collectionFactory
    )
    {
        $this->orderRepository = $orderRepository;
        $this->requestInterface = $requestInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->paymentRepository = $paymentRepository;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Edit shipping address
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var  $orderId */
        $orderId = $this->requestInterface->getParam('id');
        /** @var  $newPaymentMethodMethod */
        $newPaymentMethodMethod = $this->requestInterface->getParams();

        $pattern = '/-/';
        $paymentDetails = $newPaymentMethodMethod['payment_method'];
        $paymentDetails = preg_split($pattern, $paymentDetails);

        $newMethodPayment = $paymentDetails[0];
        $PaymentLabel = $paymentDetails[1];
        try {
            /** @var  $order */
            $order = $this->orderRepository->get($orderId);
            if (!$order->getId()) {
                throw new LocalizedException(__('Order not found.'));
            }
            /** @var  $subscriptionCollection */
            $subscriptionCollection = $this->collectionFactory->create();
            $subscriptionCollection->addFieldToFilter('order_id', $orderId)->addFieldToFilter('status', ['neq' => 'canceled']);

            foreach ($subscriptionCollection as $subscription) {
                if ($subscription->getProductId()) {
                    $subscription->setPaymentMethod($newMethodPayment);
                    $subscription->save();
                }
            }
            $payment = $order->getPayment();
            $payment->setMethod($newMethodPayment);
            $payment->setAdditionalInformation(['method_title' => $PaymentLabel]);
            $this->paymentRepository->save($payment);

            $order->setState(Order::STATE_PROCESSING)
                ->setStatus(Order::STATE_PROCESSING)
                ->addStatusToHistory(Order::STATE_PROCESSING, 'Payment method updated.');
            $this->orderRepository->save($order);

            $this->messageManager->addSuccessMessage(__('Payment method updated.'));


        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);

    }
}
