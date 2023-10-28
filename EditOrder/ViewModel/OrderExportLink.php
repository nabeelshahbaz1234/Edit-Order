<?php
declare(strict_types=1);


namespace Meiko\EditOrder\ViewModel;

use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Amasty\RecurringPayments\Model\SubscriptionManagement;






class OrderExportLink implements ArgumentInterface
{
    /** @var RequestInterface */
    private $request;
    /** @var UrlInterface */
    private $urlBuilder;
    private OrderRepositoryInterface $orderRepository;
    private SubscriptionManagement $subscriptionManagement;
    private SessionFactory $session;


    public function __construct(
        RequestInterface $request,
        UrlInterface $urlBuilder,
        OrderRepositoryInterface $orderRepository,
        SubscriptionManagement $subscriptionManagement,
        SessionFactory           $session

    ) {
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
        $this->orderRepository = $orderRepository;
        $this->subscriptionManagement = $subscriptionManagement;

        $this->session = $session;
    }

    public function getOrderDetails($orderId)
    {
        $customerSession = $this->session->create();
        $customerid = $customerSession->getCustomerId();
        $order = $this->orderRepository->get($orderId);
        $incrementID = $order->getIncrementId();
        $data = $order->getAllItems();
        $subData = $this->subscriptionManagement->getSubscriptions((int)$customerid);
        $subscriptionData = [];
        foreach ($subData as $newKey) {
            if ($order->getIncrementId() == $newKey->getData('order_increment_id')) {
                if (($newKey->getData('status') == 'Aktiv')  || ($newKey->getData('status') == 'Pausiert') || ($newKey->getData('status') == 'Actif')  || ($newKey->getData('status') == 'En pause')){


                    $subscriptionData = $newKey;

                }


            }
        }
        return  $subscriptionData;

    }
    public function getOrderExportUrl(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/view/index',
            [
                'order_id' => (int) $orderId,
            ]
        );
    }

    public function deleteOrderUrl(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_delete/delete/orderDelete',
            [
                'order_id' => (int) $orderId,
            ]
        );
    }
    public function getPauseSubscription(): string
    {
        $date = $this->request->getParam('numone');
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/index/pausesubscription',
            [
                'order_id' => (int) $orderId,

            ]
        );
    }
    public function getResumeSubscription(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/index/resumesubscription',
            [
                'order_id' => (int) $orderId,
            ]
        );
    }

    public function getDeliverySubscription(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/view/deliverydate',
            [
                'order_id' => (int) $orderId,
            ]
        );
    }
    public function getCancelSubscription(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_edit/index/cancelorderwithsubsription',
            [
                'order_id' => (int) $orderId,
            ]
        );
    }

    public function getSubscriptionExtended(): string
    {
        $orderId = $this->request->getParam('order_id');
        return $this->urlBuilder->getUrl(
            'order_extend/index/subscriptionextended',
            [
                'order_id' => (int) $orderId,
            ]
        );
    }
    public function getAllItems($orderId)
    {
        $customerSession = $this->session->create();
        $customerid = $customerSession->getCustomerId();
        $order = $this->orderRepository->get($orderId);
        $incrementID = $order->getIncrementId();
        $data = $order->getAllItems();
        return $data;
    }



}
