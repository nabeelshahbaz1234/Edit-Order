<?php


declare(strict_types=1);

/**
 * @author Amasty Team
 * @copyright Copyright (c) 2022 Amasty (https://www.amasty.com)
 * @package Subscriptions & Recurring Payments for Magento 2 (System)
 */

namespace Meiko\EditOrder\Controller\Index;

use Amasty\RecurringPayments\Api\Subscription\AddressRepositoryInterface;
use Amasty\RecurringPayments\Api\Subscription\RepositoryInterface;
use Amasty\RecurringPayments\Model\AddressFactory;
use Amasty\RecurringPayments\Model\Repository\SubscriptionPlanRepository;
use Amasty\RecurringPayments\Model\Repository\SubscriptionRepository;
use Amasty\RecurringPayments\Model\Subscription;
use Amasty\RecurringPayments\Model\Subscription\Info;
use Amasty\RecurringPayments\Model\Subscription\Operation\SubscriptionCancelOperation;
use Amasty\RecurringPayments\Model\SubscriptionFactory;
use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Pause Subscription Customer Controller
 *
 * @TODO: no errors are handled now, always send customer subscriptons' list
 */
class ResumeSubscription implements ActionInterface
{
    /**
     * @var Session
     */
    protected Session $session;

    /**
     * @var SubscriptionManagement
     */
    protected SubscriptionManagement $subscriptionManagement;

    /**
     * @var RepositoryInterface
     */
    protected RepositoryInterface $subscriptionRepository;

    /**
     * @var SubscriptionCancelOperation
     */
    protected SubscriptionCancelOperation $subscriptionCancelOperation;
    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;
    /**
     * @var SubscriptionRepository
     */
    protected SubscriptionRepository $payRepositoryInterface;
    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;
    /**
     * @var OrderManagementInterface
     */
    protected OrderManagementInterface $orderManagement;
    protected ResultFactory $resultFactory;

    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepositoryInterface;
    /**
     * @var AddressFactory
     */
    private AddressFactory $addressItemFactory;
    /**
     * @var SubscriptionFactory
     */
    private SubscriptionFactory $paymentCollectionFactory;
    /**
     * @var Subscription
     */
    private Subscription $subId;
    private SubscriptionPlanRepository $planRepository;
    private Info $subscriptionInfo;


    /**
     * @param Session $session
     * @param SubscriptionManagement $subscriptionManagement
     * @param SubscriptionCancelOperation $subscriptionCancelOperation
     * @param RepositoryInterface $subscriptionRepository
     * @param RequestInterface $request
     * @param SubscriptionRepository $payRepositoryInterface
     * @param AddressRepositoryInterface $addressRepositoryInterface
     * @param AddressFactory $addressItemFactory
     * @param SubscriptionFactory $paymentCollectionFactory
     * @param Subscription $subId
     * @param OrderRepositoryInterface $orderRepository
     * @param ManagerInterface $messageManager
     * @param OrderManagementInterface $orderManagement
     * @param ResultFactory $redirect
     * @param ResultFactory $resultFactory
     * @param SubscriptionPlanRepository $planRepository
     * @param Info $subscriptionInfo
     */
    public function __construct(
        Session                     $session,
        SubscriptionManagement      $subscriptionManagement,
        SubscriptionCancelOperation $subscriptionCancelOperation,
        RepositoryInterface         $subscriptionRepository,
        RequestInterface            $request,
        SubscriptionRepository      $payRepositoryInterface,
        AddressRepositoryInterface  $addressRepositoryInterface,
        AddressFactory              $addressItemFactory,
        SubscriptionFactory         $paymentCollectionFactory,
        Subscription                $subId,
        OrderRepositoryInterface    $orderRepository,
        ManagerInterface            $messageManager,
        OrderManagementInterface    $orderManagement,
        ResultFactory               $redirect,
        ResultFactory               $resultFactory,
        SubscriptionPlanRepository  $planRepository,
        Info                        $subscriptionInfo
    )
    {
        $this->session = $session;
        $this->subscriptionManagement = $subscriptionManagement;
        $this->subscriptionCancelOperation = $subscriptionCancelOperation;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->request = $request;
        $this->payRepositoryInterface = $payRepositoryInterface;
        $this->addressRepositoryInterface = $addressRepositoryInterface;
        $this->addressItemFactory = $addressItemFactory;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->subId = $subId;
        $this->orderRepository = $orderRepository;
        $this->messageManager = $messageManager;
        $this->orderManagement = $orderManagement;
        $this->redirect = $redirect;
        $this->resultFactory = $resultFactory;
        $this->planRepository = $planRepository;
        $this->subscriptionInfo = $subscriptionInfo;
    }


    public function execute()
    {

        $orderId = $this->request->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        $incrementID = $order->getIncrementId();
        $subscriptionsData = $this->subscriptionInfo;
        {
            foreach ($subscriptionsData as $dataKey) {
                if ($dataKey['order_increment_id'] == $incrementID) {

                    if (($dataKey['status'] == 'Aktiv') || ($dataKey['status'] == 'Actif')){
                        $dataKey['status'] = 'paused';

                    }

                }
            }

        }

        $customerid = $this->session->getCustomerId();
        $subscriptionCollections = $this->subscriptionManagement->getSubscriptions((int)$customerid);


        foreach ($subscriptionCollections as $key) {

            if ($key['subscription']['order_id'] == $orderId) {

                if ($key['subscription']['status'] == 'paused') {
                    $billingAmount = $key['next_billing_amount'];
                    $paymentId = $key['subscription']['id'];
                    $subscriptionStatus = $key['subscription']['status'];
                    $subscriptionStartDate = $key['subscription']['start_date'];
                    $subscriptionStartDate = date('Y-m-d' , strtotime($subscriptionStartDate));
                    $pausedSubscriptionsList[] = $key['subscription'];

                }
            }
        }

        $shippingDate = date('Y-m-d', strtotime('+4 day', strtotime($subscriptionStartDate)));

        try {

            foreach ($subscriptionCollections as $collection) {
                if ($order->getIncrementId() == $collection->getData('order_increment_id')) {

                    if ($collection->getData('status') == 'Pausiert')  {

                        $arrayData = [
                            'subscription' => $collection['subscription'],
                            'start_date' => $subscriptionStartDate,
                            'address' => $collection['address'],
                            'next_billing' => $subscriptionStartDate,
                            'next_billing_amount' => $billingAmount,
                            'status' => 'Aktiv',
                            'is_active' => $collection['is_active'],
                            'order_increment_id' => $collection['order_increment_id'],
                            'order_link' => $collection['order_link'],
                            'subscription_name' => $collection['subscription_name']

                        ];

                        $collection->setData($arrayData);


                        foreach ($pausedSubscriptionsList as $pauseList) {
                            $pauseList->setStatus('active');
                            $pauseList->setStartDate($subscriptionStartDate);
                            $pauseList->setShipDate($shippingDate);
                            $this->payRepositoryInterface->save($pauseList);
                        }

                        $order->setState(Order::STATE_NEW);
                        $order->setStatus('pending');
                        $this->orderRepository->save($order);
                        $this->messageManager->addSuccessMessage(__('Resumed successfully.'));
                    }elseif ($collection->getData('status') == 'En pause')  {

                        $arrayData = [
                            'subscription' => $collection['subscription'],
                            'start_date' => $subscriptionStartDate,
                            'address' => $collection['address'],
                            'next_billing' => $subscriptionStartDate,
                            'next_billing_amount' => $billingAmount,
                            'status' => 'Actif',
                            'is_active' => $collection['is_active'],
                            'order_increment_id' => $collection['order_increment_id'],
                            'order_link' => $collection['order_link'],
                            'subscription_name' => $collection['subscription_name']

                        ];

                        $collection->setData($arrayData);


                        foreach ($pausedSubscriptionsList as $pauseList) {
                            $pauseList->setStatus('active');
                            $pauseList->setStartDate($subscriptionStartDate);
                            $pauseList->setShipDate($shippingDate);
                            $this->payRepositoryInterface->save($pauseList);
                        }

                        $order->setState(Order::STATE_NEW);
                        $order->setStatus('pending');
                        $this->orderRepository->save($order);
                        $this->messageManager->addSuccessMessage(__('Resumed successfully.'));
                    }
                }
            }

        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());


        }
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }

}
