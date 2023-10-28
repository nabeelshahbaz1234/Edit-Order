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
use DateTime;
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
class PauseSubscription implements ActionInterface
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

        $paramData = $this->request->getParam('PauseButton');
        $paramDate = $paramData;
        $orderId = $this->request->getParam('order_id');
        if ($paramData) {

            $order = $this->orderRepository->get($orderId);

            $incrementID = $order->getIncrementId();

            $subscriptionsData = $this->subscriptionInfo;
            {
                foreach ($subscriptionsData as $dataKey) {
                    if ($dataKey['order_increment_id'] == $incrementID) {

                        if ($dataKey['status'] == 'Aktiv') {
                            $dataKey['status'] = 'Pausiert';

                        }

                    }
                }

            }
            $customerid = $this->session->getCustomerId();
            $subscriptionCollections = $this->subscriptionManagement->getSubscriptions((int)$customerid);

            $activeSubscriptionsList = array();

            foreach ($subscriptionCollections as $key) {
                if ($key['subscription']['order_id'] == $orderId) {
                    if ($key['subscription']['status'] == 'active') {
                        $paymentId = $key['subscription']['id'];
                        $subscriptionStatus = $key['subscription']['status'];
                        $activeSubscriptionsList[] = $key['subscription'];

                    }

                }
                $activeSubscriptionsList = array_unique($activeSubscriptionsList, SORT_REGULAR);
            }


            try {
                if ($subscriptionStatus == 'active') {
                    foreach ($subscriptionCollections as $collection) {
                        if ($collection['status'] == 'Aktiv') {
                            if ($order->getIncrementId() == $collection->getData('order_increment_id')) {
                                $frequencyUnit = $collection->getData('subscription')['frequency_unit'];

                                $nextBillingDateTime = $collection->getNextBilling();
                                $nextBillingDateWithoutTime = date('Y-m-d', strtotime($nextBillingDateTime));
                                $shippingDate = $collection->getData('subscription')['ship_date'];
                                $days = date('l', strtotime(date('Y-m-d', strtotime($shippingDate))));
                                $nextBillingDate = substr($nextBillingDateTime, 0, 10);
                                $shippingDate = substr($shippingDate, 0, 10);

                                if ($collection->getData('status') == 'Aktiv') {
                                    if ($frequencyUnit == 'week') {
                                        $dayOfWeek = $days;
                                        $dateTime = new DateTime($paramDate);
//                                    $dateTime->modify($dayOfWeek);
                                        $nextWeekDates = array();
                                        for ($i = 0; $i < 7; $i++) {
                                            // add the date to the array
                                            $nextWeekDates[] = $dateTime->format('Y-m-d');
                                            // increment the date by one day
                                            $dateTime->modify('+1 day');
                                            if ($days == $dateTime->format('l')) {
                                                $date = $dateTime->format('Y-m-d');
                                            }
                                        }
                                        $billing = date('Y-m-d', strtotime('-4 day', strtotime($date)));
                                        if ($paramDate < $nextBillingDateWithoutTime) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'paused',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];


                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($date);
                                                $this->payRepositoryInterface->save($activeList);
                                            }
                                         $this->messageManager->addSuccessMessage(__('Your delivery for %1 will be delivered and your subscription was paused till %2',$shippingDate,$paramData));
                                            break;
                                        }


                                        if ($paramDate >= $nextBillingDateWithoutTime || $paramData < $shippingDate) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'paused',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];

                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($date);
                                                $this->payRepositoryInterface->save($activeList);
                                            }

                                            $this->messageManager->addSuccessMessage(__('Your delivery for %1 will not be delivered because your subscription is paused till %2 and your payment is pending',$shippingDate,$paramData));
                                            break;
                                        }

                                    }
                                    if ($frequencyUnit == 'month') {

                                        $specifiedDate = $paramData;
                                        $month = date('m', strtotime($specifiedDate));
                                        $year = date('Y', strtotime($specifiedDate));
                                        $paramsDate = $year . '-' . $month;

                                        $shipmentDay = date('d', strtotime($shippingDate));
                                        $finalDate = $paramsDate . '-' . $shipmentDay;

                                        $date = new DateTime($finalDate);
                                        $date->modify('+1 month');
                                        $finalDate = $date->format('Y-m-d');

                                        $billing = date('Y-m-d', strtotime('-4 day', strtotime($finalDate)));
                                        if ($paramDate < $nextBillingDateWithoutTime) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'paused',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];


                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($finalDate);
                                                $this->payRepositoryInterface->save($activeList);
                                            }
                                            $this->messageManager->addSuccessMessage(__('Your delivery for %1 will be delivered and your subscription was paused till %2',$shippingDate,$paramData));

                                            break;
                                        }
                                        if ($paramDate >= $nextBillingDateWithoutTime || $paramData < $shippingDate) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'paused',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];

                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($finalDate);
                                                $this->payRepositoryInterface->save($activeList);
                                            }

                                            $this->messageManager->addSuccessMessage(__('Your delivery for %1 will not be delivered because your subscription is paused till  %2 and your payment is pending' , $shippingDate,$paramData));
                                            break;
                                        }

                                    }

                                    $order->setState(Order::STATE_HOLDED);
                                    $order->setStatus(Order::STATE_HOLDED);
                                    $this->orderRepository->save($order);
                                    $this->messageManager->addSuccessMessage(__('Paused successfully.'));
                                }

                            }

                        } elseif ($collection['status'] == 'Actif') {
                            if ($order->getIncrementId() == $collection->getData('order_increment_id')) {
                                $frequencyUnit = $collection->getData('subscription')['frequency_unit'];

                                $nextBillingDateTime = $collection->getNextBilling();
                                $nextBillingDateTime = $this->getConvertedFrenchDate($nextBillingDateTime);
                                $nextBillingDateWithoutTime = date('Y-m-d', strtotime($nextBillingDateTime));
                                $shippingDate = $collection->getData('subscription')['ship_date'];
                                $days = date('l', strtotime(date('Y-m-d', strtotime($shippingDate))));
                                $nextBillingDate = substr($nextBillingDateTime, 0, 10);
                                $shippingDate = substr($shippingDate, 0, 10);

                                if ($collection->getData('status') == 'Actif') {
                                    if ($frequencyUnit == 'week') {
                                        $dayOfWeek = $days;
                                        $dateTime = new DateTime($paramDate);
//                                    $dateTime->modify($dayOfWeek);
                                        $nextWeekDates = array();
                                        for ($i = 0; $i < 7; $i++) {
                                            // add the date to the array
                                            $nextWeekDates[] = $dateTime->format('Y-m-d');
                                            // increment the date by one day
                                            $dateTime->modify('+1 day');
                                            if ($days == $dateTime->format('l')) {
                                                $date = $dateTime->format('Y-m-d');
                                            }
                                        }
                                        $billing = date('Y-m-d', strtotime('-4 day', strtotime($date)));
                                        if ($paramDate < $nextBillingDateWithoutTime) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'On Break',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];


                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($date);
                                                $this->payRepositoryInterface->save($activeList);
                                            }
                                            $this->messageManager->addSuccessMessage(__('Your delivery for %1  will be delivered and your subscription was paused till %2',$shippingDate,$paramData));
                                            break;
                                        }


                                        if ($paramDate >= $nextBillingDateWithoutTime || $paramData < $shippingDate) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'On Break',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];

                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($date);
                                                $this->payRepositoryInterface->save($activeList);
                                            }

                                            $this->messageManager->addSuccessMessage(__('Your delivery for %1 will not be delivered because your subscription is paused till %2 and your payment is pending' , $shippingDate,$paramData));
                                            break;
                                        }

                                    }
                                    if ($frequencyUnit == 'month') {

                                        $specifiedDate = $paramData;
                                        $month = date('m', strtotime($specifiedDate));
                                        $year = date('Y', strtotime($specifiedDate));
                                        $paramsDate = $year . '-' . $month;

                                        $shipmentDay = date('d', strtotime($shippingDate));
                                        $finalDate = $paramsDate . '-' . $shipmentDay;

                                        $date = new DateTime($finalDate);
                                        $date->modify('+1 month');
                                        $finalDate = $date->format('Y-m-d');

                                        $billing = date('Y-m-d', strtotime('-4 day', strtotime($finalDate)));
                                        if ($paramDate < $nextBillingDateWithoutTime) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'On Break',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];


                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($finalDate);
                                                $this->payRepositoryInterface->save($activeList);
                                            }
                                            $this->messageManager->addSuccessMessage(__('Your delivery for %1 will be delivered and your subscription was paused till %2',$shippingDate,$paramData));
                                            break;
                                        }
                                        if ($paramDate >= $nextBillingDateWithoutTime || $paramData < $shippingDate) {

                                            $arrayData = [
                                                'subscription' => $collection['subscription'],
                                                'start_date' => $billing,
                                                'address' => $collection['address'],
                                                'next_billing' => '',
                                                'next_billing_amount' => $collection['next_billing_amount'],
                                                'status' => 'On Break',
                                                'is_active' => $collection['is_active'],
                                                'order_increment_id' => $collection['order_increment_id'],
                                                'order_link' => $collection['order_link'],
                                                'subscription_name' => $collection['subscription_name']

                                            ];

                                            $collection->setData($arrayData);

                                            foreach ($activeSubscriptionsList as $activeList) {
                                                $activeList->setStatus('paused');
                                                $activeList->setStartDate($billing);
                                                $activeList->setShipDate($finalDate);
                                                $this->payRepositoryInterface->save($activeList);
                                            }

                                            $this->messageManager->addSuccessMessage(__('Your delivery for %1 will not be delivered because your subscription is paused till %2 and your payment is pending',$shippingDate,$paramData));
                                            break;
                                        }

                                    }

                                    $order->setState(Order::STATE_HOLDED);
                                    $order->setStatus(Order::STATE_HOLDED);
                                    $this->orderRepository->save($order);
                                    $this->messageManager->addSuccessMessage(__('Paused successfully.'));
                                }

                            }

                        }
                    }

                }

            } catch (Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());


            }
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
        } else {
            $this->messageManager->addErrorMessage(__('Please choose  a date to  paused .'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
        }
    }

        public function getConvertedFrenchDate($date)
        {
            setlocale(LC_TIME, 'fr_FR');
        $monthMapping = [
            'janv.' => 'January',
            'févr.' => 'February',
            'mars' => 'March',
            'avr.' => 'April',
            'mai' => 'May',
            'juin' => 'June',
            'juil.' => 'July',
            'août' => 'August',
            'sept.' => 'September',
            'oct.' => 'October',
            'nov.' => 'November',
            'déc.' => 'December',
        ];
        $dateString = strtr($date, $monthMapping);

        $dateParts = explode(' ', $dateString);
        $dateWithoutTime = $dateParts[0] . ' ' . $dateParts[1] . ' ' . $dateParts[2];

// Create a DateTime object from the date string
        $date = DateTime::createFromFormat('d F Y', $dateWithoutTime);

// Format the date without the timestamp
            return $date->format('Y-m-d');
        }
    }
