<?php

declare(strict_types=1);

/**
 * @author Amasty Team
 * @copyright Copyright (c) 2022 Amasty (https://www.amasty.com)
 * @package Subscriptions & Recurring Payments for Magento 2 (System)
 */

namespace Meiko\EditOrder\Controller\DeliverySchedule;

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
use DateInterval;
use DateTime;
use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Meiko\EditOrder\Controller\Index\PauseSubscription;

/**
 * Pause Subscription Customer Controller
 *
 * @TODO: no errors are handled now, always send customer subscriptons' list
 */
class EditDelivery implements ActionInterface
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
    private OrderItemRepositoryInterface $orderItemRepository;
private PauseSubscription $pauseSubscription;

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
        Info                        $subscriptionInfo,
        OrderItemRepositoryInterface  $orderItemRepository,
        PauseSubscription $pauseSubscription
    ) {
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
        $this->orderItemRepository = $orderItemRepository;
        $this->pauseSubscription = $pauseSubscription;
    }


    public function execute()
    {

        $days = [ 1 => 'Monday' , 2 =>'Tuesday', 3=> 'Wednesday' , 4 =>'Thursday' , 5 =>'Friday'];
        $orderId = $this->request->getParam('id');
        $order = $this->orderRepository->get($orderId);
        $is_weekly = false;
        $paramData = $this->request->getParam('delivery_date');

        if($paramData == ''){
            $paramData = $this->request->getParam('delivery_day');
            $is_weekly = true;
            if($paramData == 1)
                $day_of_week = 'Monday';
            if($paramData == 2)
                $day_of_week = 'Tuesday';
            if($paramData == 3)
                $day_of_week = 'Wednesday';
            if($paramData == 4)
                $day_of_week = 'Thursday';
            if($paramData == 5)
                $day_of_week = 'Friday';
        }

        if(!$is_weekly) {
            $date_input = $paramData; // Assuming the date input field has the name 'date'
            $date_obj = new DateTime($date_input);
            $day = $date_obj->format('d');
            $c_date_obj = new DateTime();
            $c_day = (int) $c_date_obj->format('d') + 4;
            $month = $date_obj->format('m');
            $c_month = $c_date_obj->format('m');
            $year = $date_obj->format('Y');
            $cropped_month = substr($month, 0, 2);
            $cropped_year = substr($year, 2);
        }
        $customerid = $this->session->getCustomerId();
        $subscriptionCollections = $this->subscriptionManagement->getSubscriptions((int)$customerid);

        foreach ($subscriptionCollections as $key) {
            if ($key['subscription']['order_id'] == $orderId && $key['subscription']['status'] == 'active') {
                $subscriptionStatus = $key['subscription']['status'];
                $activeSubscriptionsList[] = $key['subscription'];
            }
        }

        try {
            if ($subscriptionStatus == 'active') {
                foreach ($subscriptionCollections as $collection) {
                    $newUpdatedDate = '';
                    $previousEndDate = $collection->getData('subscription')['end_date'];
                    if ($order->getIncrementId() == $collection->getData('order_increment_id') && $collection->getData('status') == 'Aktiv') {
                        if($is_weekly) {
                            $previousDate = $collection->getData('start_date');
                            $date = new DateTime($previousDate);
                            $day_of_week_start = $date->format('l');
                            $diff = array_search($day_of_week, $days) - array_search($day_of_week_start, $days);
                            if ($diff < 0) {
                                $diff += 7;
                            }
                            $newDate = clone $date;
                            $newDate->modify("+$diff day");
                            $newDate->modify('+1 week');
                            $newShipDate = $newDate->format('Y-m-d');
                            $newDate = date('Y-m-d' , strtotime('-4 day' , strtotime($newShipDate)));
                            $newUpdatedDate = $collection->getData('subscription')['end_date'];
                            if($newUpdatedDate != null) {
                                $endDate = new DateTime($newUpdatedDate);
                                $endDate = $endDate->modify('+1 week');
                                $endDate = $endDate->format('Y-m-d');
                                $newUpdatedDate = $endDate;
                            }
                        }else if(!$is_weekly){
                            $previousDate = $collection->getData('start_date');
                            $previousEndDate = $collection->getData('subscription')['end_date'];
                            $date = new DateTime($previousDate);
                            if($previousEndDate != null) {
                                $endDate = new DateTime($previousEndDate);
                            }

                            $previousday = $date->format('d');
                            $previousmonth = (int)$date->format('m');
                            if($c_day >= (int) $day && $c_month == $previousmonth) {
                                $previousmonth = (int)$date->format('m') + 1;
                            }
                            $previousyear = $date->format('Y');
                            $newShipDate = $previousyear . '-' . $previousmonth . '-' . $day;
                            $newDateObj = new DateTime($previousyear . '-' . $previousmonth . '-' . $day);
                            $newDateObj->sub(new DateInterval('P4D')); // Remove 4 days to the date
                            $newDate = $newDateObj->format('Y-m-d');

                            if($previousEndDate != null) {
                                $previousEndday = $endDate->format('d');
                                $previousEndMonth = $endDate->format('m');
                                $previousEndYear = $endDate->format('Y');
                                $newEndDateObj = new DateTime($previousEndYear . '-' . $previousEndMonth . '-' . $day);
                                $newEndDateObj->sub(new DateInterval('P4D')); // Remove 4 days to the date
                                $newUpdatedDate = $newEndDateObj->format('Y-m-d');

                            }
                        }
                        if(!$collection->getData('end_date')) {
                            $arrayData = [
                                'subscription' => $collection['subscription'],
                                'start_date' => $newDate,
                                'ship_date' => $newShipDate,
                                'address' => $collection['address'],
                                'next_billing' => $newDate,
                                'next_billing_amount' => $collection['next_billing_amount'],
                                'status' => 'active',
                                'is_active' => $collection['is_active'],
                                'order_increment_id' => $collection['order_increment_id'],
                                'order_link' => $collection['order_link'],
                                'subscription_name' => $collection['subscription_name']
                            ];
                        }else {
                            $arrayData = [
                                'subscription' => $collection['subscription'],
                                'start_date' => $newDate,
                                'end_date' => $newUpdatedDate,
                                'ship_date' => $newShipDate,
                                'address' => $collection['address'],
                                'next_billing' => $newDate,
                                'next_billing_amount' => $collection['next_billing_amount'],
                                'status' => 'active',
                                'is_active' => $collection['is_active'],
                                'order_increment_id' => $collection['order_increment_id'],
                                'order_link' => $collection['order_link'],
                                'subscription_name' => $collection['subscription_name']
                            ];

                        }

                        foreach ($order->getItems() as $item) {

                            if ($collection->getData('subscription')['order_item_id'] == $item->getData('quote_item_id')) {

                                $option = $item->getProductOptions();
                                if (isset($option['info_buyRequest']['am_rec_end_date'])) {
                                    if ($option['info_buyRequest']['am_rec_start_date'] == $option['info_buyRequest']['am_rec_end_date']) {
                                        $newUpdatedDate = $newDate;
                                    }
                                }
                                if ($is_weekly) {
                                    $deliveryDays = date('l' , strtotime($newShipDate));
                                    $infoBuyRequestData = [
                                        'uenc' => $option['info_buyRequest']['uenc'],
                                        'product' => $collection->getData('subscription')['product_id'],
                                        'am_rec_start_date' => $newDate,
                                        'ship_date' => $newShipDate,
                                        'qty' => $collection->getData('subscription')['qty'],
                                        'am_rec_end_date' => $newUpdatedDate ?? '',
                                        'selected_configurable_option' => $option['info_buyRequest']['selected_configurable_option'],
                                        'related_product' => $option['info_buyRequest']['related_product'],
                                        'am_subscription_end_type' => $option['info_buyRequest']['am_subscription_end_type'],
                                        'item' => $option['info_buyRequest']['item'],
                                        'subscribe' => $option['info_buyRequest']['subscribe'],
                                        'bundlename' => $option['info_buyRequest']['bundlename'],
                                        'am_rec_subscription_plan_id' => $option['info_buyRequest']['am_rec_subscription_plan_id'],
                                        'am_rec_timezone' => $option['info_buyRequest']['am_rec_timezone'],
                                        'deliverydays' => $deliveryDays
                                    ];
                                } elseif (!$is_weekly)
                                {
                                    $infoBuyRequestData = [
                                        'uenc' => $option['info_buyRequest']['uenc'],
                                        'product' => $collection->getData('subscription')['product_id'],
                                        'am_rec_start_date' => $newDate,
                                        'ship_date' => $newShipDate,
                                        'qty' => $collection->getData('subscription')['qty'],
                                        'am_rec_end_date' => $newUpdatedDate ?? '',
                                        'selected_configurable_option' => $option['info_buyRequest']['selected_configurable_option'],
                                        'related_product' => $option['info_buyRequest']['related_product'],
                                        'am_subscription_end_type' => $option['info_buyRequest']['am_subscription_end_type'],
                                        'item' => $option['info_buyRequest']['item'],
                                        'subscribe' => $option['info_buyRequest']['subscribe'],
                                        'bundlename' => $option['info_buyRequest']['bundlename'],
                                        'am_rec_subscription_plan_id' => $option['info_buyRequest']['am_rec_subscription_plan_id'],
                                        'am_rec_timezone' => $option['info_buyRequest']['am_rec_timezone'],
                                    ];

                                }
                                $option['info_buyRequest'] = $infoBuyRequestData ;
                                $item->setProductOptions($option);
                                $this->orderItemRepository->save($item);
                                break;

                            }

                        }

                        $collection->setData($arrayData);

                        foreach ($activeSubscriptionsList as $activeList) {
                            if ($activeList['id'] == $collection['subscription']['id']) {
                                if ($activeList->getEndDate()) {
                                    if ($activeList->getEndDate() == $activeList->getStartDate()) {
                                        $activeList->setEndDate($newDate);
                                        $activeList->setStartDate($newDate);
                                        $activeList->setShipDate($newShipDate);
                                        $this->payRepositoryInterface->save($activeList);
                                        break;
                                    }

                                }
                                $activeList->setStartDate($newDate);
                                if($previousEndDate != null) {
                                    $activeList->setEndDate($newUpdatedDate);
                                }
                                $activeList->setShipDate($newShipDate);
                                $this->payRepositoryInterface->save($activeList);
                                break;
                            }

                        }
                    }elseif ($order->getIncrementId() == $collection->getData('order_increment_id') && $collection->getData('status') == 'Actif') {
                        if($is_weekly) {
                            $previousDate = $collection->getData('start_date');
                            $previousDate = $this->pauseSubscription->getConvertedFrenchDate($previousDate);
                            $date = new DateTime($previousDate);
                            $day_of_week_start = $date->format('l');
                            $diff = array_search($day_of_week, $days) - array_search($day_of_week_start, $days);
                            if ($diff < 0) {
                                $diff += 7;
                            }
                            $newDate = clone $date;
                            $newDate->modify("+$diff day");
                            $newDate->modify('+1 week');
                            $newShipDate = $newDate->format('Y-m-d');
                            $newDate = date('Y-m-d' , strtotime('-4 day' , strtotime($newShipDate)));
                            $newUpdatedDate = $collection->getData('subscription')['end_date'];
                            if($newUpdatedDate != null) {
                                $endDate = new DateTime($newUpdatedDate);
                                $endDate = $endDate->modify('+1 week');
                                $endDate = $endDate->format('Y-m-d');
                                $newUpdatedDate = $endDate;
                            }
                        }else if(!$is_weekly){
                            $previousDate = $collection->getData('start_date');
                            $previousEndDate = $collection->getData('subscription')['end_date'];
                            $date = new DateTime($previousDate);
                            if($previousEndDate != null) {
                                $endDate = new DateTime($previousEndDate);
                            }

                            $previousday = $date->format('d');
                            $previousmonth = (int)$date->format('m');
                            if($c_day >= (int) $day && $c_month == $previousmonth) {
                                $previousmonth = (int)$date->format('m') + 1;
                            }
                            $previousyear = $date->format('Y');
                            $newShipDate = $previousyear . '-' . $previousmonth . '-' . $day;
                            $newDateObj = new DateTime($previousyear . '-' . $previousmonth . '-' . $day);
                            $newDateObj->sub(new DateInterval('P4D')); // Remove 4 days to the date
                            $newDate = $newDateObj->format('Y-m-d');

                            if($previousEndDate != null) {
                                $previousEndday = $endDate->format('d');
                                $previousEndMonth = $endDate->format('m');
                                $previousEndYear = $endDate->format('Y');
                                $newEndDateObj = new DateTime($previousEndYear . '-' . $previousEndMonth . '-' . $day);
                                $newEndDateObj->sub(new DateInterval('P4D')); // Remove 4 days to the date
                                $newUpdatedDate = $newEndDateObj->format('Y-m-d');

                            }
                        }
                        if(!$collection->getData('end_date')) {
                            $arrayData = [
                                'subscription' => $collection['subscription'],
                                'start_date' => $newDate,
                                'ship_date' => $newShipDate,
                                'address' => $collection['address'],
                                'next_billing' => $newDate,
                                'next_billing_amount' => $collection['next_billing_amount'],
                                'status' => 'active',
                                'is_active' => $collection['is_active'],
                                'order_increment_id' => $collection['order_increment_id'],
                                'order_link' => $collection['order_link'],
                                'subscription_name' => $collection['subscription_name']
                            ];
                        }else {
                            $arrayData = [
                                'subscription' => $collection['subscription'],
                                'start_date' => $newDate,
                                'end_date' => $newUpdatedDate,
                                'ship_date' => $newShipDate,
                                'address' => $collection['address'],
                                'next_billing' => $newDate,
                                'next_billing_amount' => $collection['next_billing_amount'],
                                'status' => 'active',
                                'is_active' => $collection['is_active'],
                                'order_increment_id' => $collection['order_increment_id'],
                                'order_link' => $collection['order_link'],
                                'subscription_name' => $collection['subscription_name']
                            ];

                        }

                        foreach ($order->getItems() as $item) {

                            if ($collection->getData('subscription')['order_item_id'] == $item->getData('quote_item_id')) {

                                $option = $item->getProductOptions();
                                if (isset($option['info_buyRequest']['am_rec_end_date'])) {
                                    if ($option['info_buyRequest']['am_rec_start_date'] == $option['info_buyRequest']['am_rec_end_date']) {
                                        $newUpdatedDate = $newDate;
                                    }
                                }
                                if ($is_weekly) {
                                    $deliveryDays = date('l' , strtotime($newShipDate));
                                    $infoBuyRequestData = [
                                        'uenc' => $option['info_buyRequest']['uenc'],
                                        'product' => $collection->getData('subscription')['product_id'],
                                        'am_rec_start_date' => $newDate,
                                        'ship_date' => $newShipDate,
                                        'qty' => $collection->getData('subscription')['qty'],
                                        'am_rec_end_date' => $newUpdatedDate ?? '',
                                        'selected_configurable_option' => $option['info_buyRequest']['selected_configurable_option'],
                                        'related_product' => $option['info_buyRequest']['related_product'],
                                        'am_subscription_end_type' => $option['info_buyRequest']['am_subscription_end_type'],
                                        'item' => $option['info_buyRequest']['item'],
                                        'subscribe' => $option['info_buyRequest']['subscribe'],
                                        'bundlename' => $option['info_buyRequest']['bundlename'],
                                        'am_rec_subscription_plan_id' => $option['info_buyRequest']['am_rec_subscription_plan_id'],
                                        'am_rec_timezone' => $option['info_buyRequest']['am_rec_timezone'],
                                        'deliverydays' => $deliveryDays
                                    ];
                                } elseif (!$is_weekly)
                                {
                                    $infoBuyRequestData = [
                                        'uenc' => $option['info_buyRequest']['uenc'],
                                        'product' => $collection->getData('subscription')['product_id'],
                                        'am_rec_start_date' => $newDate,
                                        'ship_date' => $newShipDate,
                                        'qty' => $collection->getData('subscription')['qty'],
                                        'am_rec_end_date' => $newUpdatedDate ?? '',
                                        'selected_configurable_option' => $option['info_buyRequest']['selected_configurable_option'],
                                        'related_product' => $option['info_buyRequest']['related_product'],
                                        'am_subscription_end_type' => $option['info_buyRequest']['am_subscription_end_type'],
                                        'item' => $option['info_buyRequest']['item'],
                                        'subscribe' => $option['info_buyRequest']['subscribe'],
                                        'bundlename' => $option['info_buyRequest']['bundlename'],
                                        'am_rec_subscription_plan_id' => $option['info_buyRequest']['am_rec_subscription_plan_id'],
                                        'am_rec_timezone' => $option['info_buyRequest']['am_rec_timezone'],
                                    ];

                                }
                                $option['info_buyRequest'] = $infoBuyRequestData ;
                                $item->setProductOptions($option);
                                $this->orderItemRepository->save($item);
                                break;

                            }

                        }

                        $collection->setData($arrayData);

                        foreach ($activeSubscriptionsList as $activeList) {
                            if ($activeList['id'] == $collection['subscription']['id']) {
                                if ($activeList->getEndDate()) {
                                    if ($activeList->getEndDate() == $activeList->getStartDate()) {
                                        $activeList->setEndDate($newDate);
                                        $activeList->setStartDate($newDate);
                                        $activeList->setShipDate($newShipDate);
                                        $this->payRepositoryInterface->save($activeList);
                                        break;
                                    }

                                }
                                $activeList->setStartDate($newDate);
                                if($previousEndDate != null) {
                                    $activeList->setEndDate($newUpdatedDate);
                                }
                                $activeList->setShipDate($newShipDate);
                                $this->payRepositoryInterface->save($activeList);
                                break;
                            }

                        }
                    }

                }
                $this->orderRepository->save($order);
            }

            $this->messageManager->addSuccessMessage(__('Delivery Date is updated successfully'));
            $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }

}
