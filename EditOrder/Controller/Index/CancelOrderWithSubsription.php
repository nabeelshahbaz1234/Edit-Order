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
use Amasty\RecurringPayments\Api\Subscription\SubscriptionInterface;
use Amasty\RecurringPayments\Model\AddressFactory;
use Amasty\RecurringPayments\Model\Config;
use Amasty\RecurringPayments\Model\Repository\ScheduleRepository;
use Amasty\RecurringPayments\Model\Repository\SubscriptionPlanRepository;
use Amasty\RecurringPayments\Model\Repository\SubscriptionRepository;
use Amasty\RecurringPayments\Model\Subscription;
use Amasty\RecurringPayments\Model\Subscription\Email\EmailNotifier;
use Amasty\RecurringPayments\Model\Subscription\Info;
use Amasty\RecurringPayments\Model\Subscription\Operation\SubscriptionCancelOperation;
use Amasty\RecurringPayments\Model\SubscriptionFactory;
use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Framework\UrlInterface;


/**
 * Cancel Subscription Customer Controller
 *
 * @TODO: no errors are handled now, always send customer subscriptons' list
 */
class CancelOrderWithSubsription implements ActionInterface
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
    /**
     * @var ResultFactory
     */
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
    /**
     * @var SubscriptionPlanRepository
     */
    private SubscriptionPlanRepository $planRepository;
    /**
     * @var Info
     */
    private Info $subscriptionInfo;
    /**
     * @var SubscriptionInterface
     */
    private SubscriptionInterface $subscription;
    /**
     * @var Config
     */
    private Config $config;
    /**
     * @var ScheduleRepository
     */
    private ScheduleRepository $scheduleRepository;
    /**
     * @var EmailNotifier
     */
    private EmailNotifier $emailNotifier;
    private UrlInterface $url;


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
     * @param SubscriptionInterface $subscription
     * @param Config $config
     * @param ScheduleRepository $scheduleRepository
     * @param EmailNotifier $emailNotifier
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
        Info                        $subscriptionInfo,
        SubscriptionInterface       $subscription,
        Config                      $config,
        ScheduleRepository          $scheduleRepository,
        EmailNotifier               $emailNotifier,
        UrlInterface $url
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
        $this->subscription = $subscription;
        $this->config = $config;
        $this->scheduleRepository = $scheduleRepository;
        $this->emailNotifier = $emailNotifier;
        $this->url = $url;
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws CouldNotSaveException
     */
    public function execute()
    {

        $orderId = $this->request->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        $customerid = $this->session->getCustomerId();
        $subscriptionCollections = $this->subscriptionManagement->getSubscriptions((int)$customerid);

        foreach ($subscriptionCollections as $key) {
            if ($key['subscription']['order_id'] == $orderId) {
                if ($key['subscription']['status'] != 'canceled') {
                    $paymentId = $key['subscription']['id'];
                    $subscriptionStatus = $key['subscription']['status'];
                    $subscriptionsList[] = $key['subscription'];

                }
                if (isset($subscriptionsList)) {
                    foreach ($subscriptionsList as $list) {
                        $list->setStatus('canceled');
                        $this->payRepositoryInterface->save($list);
                    }
                    $storeId = $this->subscription->getStoreId();
                    $this->config->isNotifySubscriptionCanceled((int)$storeId);
                    if ($this->config->isNotifySubscriptionCanceled((int)$storeId)) {
                        $template = $this->config->getEmailTemplateSubscriptionCanceled((int)$storeId);
                        $this->emailNotifier->sendEmail(
                            $list,
                            $template
                        );
                    }

                }
            }
        }
        $order->setState(Order::STATE_CANCELED);
        $order->setStatus(Order::STATE_CANCELED);
        $this->orderRepository->save($order);
        $response['redirectUrlSubscription'] = $this->url->getUrl('amasty_recurring/product/subscriptionslistbyfrequencyunit/');
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setData($response);
        $this->messageManager->addSuccessMessage(__('Order & Subscriptions were canceled successfully.'));
        return $resultJson;
    }

}
