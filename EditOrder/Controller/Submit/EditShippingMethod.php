<?php

declare(strict_types=1);

namespace Meiko\EditOrder\Controller\Submit;

use Amasty\RecurringPayments\Model\ResourceModel\Subscription\CollectionFactory;
use Exception;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order;

/**
 * @class EditShippingMethod
 */
class EditShippingMethod implements ActionInterface
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
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;
    /**
     * @var Rate
     */
    private Rate $shippingRate;
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;
    /**
     * @var Order
     */
    private Order $orderResourceModel;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $requestInterface
     * @param Redirect $resultRedirectFactory
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param ManagerInterface $messageManager
     * @param CollectionFactory $collectionFactory
     * @param Rate $shippingRate
     * @param CartRepositoryInterface $cartRepository
     * @param Order $orderResourceModel
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        RequestInterface         $requestInterface,
        Redirect                 $resultRedirectFactory,
        ResultFactory            $resultFactory,
        UrlInterface             $url,
        ManagerInterface         $messageManager,
        CollectionFactory        $collectionFactory,
        Rate                     $shippingRate,
        CartRepositoryInterface  $cartRepository,
        Order                    $orderResourceModel
    )
    {
        $this->orderRepository = $orderRepository;
        $this->requestInterface = $requestInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->collectionFactory = $collectionFactory;
        $this->shippingRate = $shippingRate;
        $this->cartRepository = $cartRepository;
        $this->orderResourceModel = $orderResourceModel;
    }

    /**
     * Edit shipping address
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        /** @var  $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        /** @var  $orderId */
        $orderId = $this->requestInterface->getParam('id');
        /** @var  $newShippingMethod */
        $newShippingMethod = $this->requestInterface->getParams();

        $pattern = '/-/';
        $shippingDetails = $newShippingMethod['shipping'];
        $shippingDetails = preg_split($pattern, $shippingDetails);

        $newMethodShipment = $shippingDetails[0];
        $shippingLabel = $shippingDetails[1];

        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order ID is missing.'));
            return $resultRedirect->setUrl($this->url->getUrl('sales/order/history'));
        }

        try {
            $order = $this->orderRepository->get($orderId);
            $quote = $this->cartRepository->get($order->getQuoteId());
            $this->shippingRate
                ->setCode($newMethodShipment)
                ->getPrice(1);
            $shippingAddress = $quote->getShippingAddress();

            $shippingAddress->setFreeShipping(1);
            $shippingAddress->setShippingMethod($newMethodShipment);
            $shippingAddress->setCollectShippingRates(true);

            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()->setShippingMethod($newMethodShipment); //shipping method
            $quote->getShippingAddress()->addShippingRate($this->shippingRate);

            $this->cartRepository->save($quote);
            $order->setShippingMethod($newMethodShipment);
            $order->setShippingDescription($shippingLabel);
            $this->orderResourceModel->save($order);
   $this->messageManager->addSuccessMessage(__('Shipping Method updated successfully'));
} catch (NoSuchEntityException $e) {
    $this->messageManager->addErrorMessage(__('Order not found:
    Something went wrong
    %1', $e->getMessage()));
} catch (Exception $e) {
    $this->messageManager->addErrorMessage(__('Error updating Billing address: %1', $e->getMessage()));
}

return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
