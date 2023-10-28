<?php


declare(strict_types=1);

/**
 * @author Amasty Team
 * @copyright Copyright (c) 2022 Amasty (https://www.amasty.com)
 * @package Subscriptions & Recurring Payments for Magento 2 (System)
 */

namespace Meiko\EditOrder\Controller\Index;


use Amasty\RecurringPayments\Api\Subscription\AddressInterface;
use Amasty\RecurringPayments\Api\Subscription\AddressRepositoryInterface;
use Amasty\RecurringPayments\Api\Subscription\RepositoryInterface;
use Amasty\RecurringPayments\Api\Subscription\SubscriptionInterface;
use Amasty\RecurringPayments\Block\Product\View\RecurringPayments;
use Amasty\RecurringPayments\Model\AddressFactory;
use Amasty\RecurringPayments\Model\Repository\SubscriptionRepository;
use Amasty\RecurringPayments\Model\ResourceModel\Address\CollectionFactory as AdressCollectionFactory;
use Amasty\RecurringPayments\Model\ResourceModel\SubscriptionPlan\CollectionFactory;
use Amasty\RecurringPayments\Model\Subscription;
use Amasty\RecurringPayments\Model\SubscriptionFactory;
use Amasty\RecurringPayments\Model\SubscriptionFactory as SubFactory;
use Amasty\RecurringPayments\Model\SubscriptionManagement;
use Amasty\RecurringPayments\Service\GetCurrentProductService;
use Magento\Catalog\Helper\Data;
use Magento\Catalog\Model\ProductRepository;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ActionInterface;
use magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartItemInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\ItemFactory;


/**
 * class SubscriptionExtended
 */
class SubscriptionExtended implements ActionInterface
{

    /**
     * @var PageFactory
     */
    protected PageFactory $resultPageFactory;
    /**
     * @var ProductRepository
     */
    protected ProductRepository $productRepository;
    /**
     * @var CartItemInterfaceFactory
     */
    protected CartItemInterfaceFactory $cartItemFactory;
    /**
     * @var ItemFactory
     */
    protected ItemFactory $orderItemFactory;

    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;
    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;
    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;
    /**
     * @var RedirectFactory
     */
    protected RedirectFactory $resultRedirectFactory;
    /**
     * @var SubscriptionInterface
     */
    protected SubscriptionInterface $subscription;
    /**
     * @var CartRepositoryInterface
     */
    protected CartRepositoryInterface $quoteRepository;
    /**
     * @var AddressFactory
     */
    protected AddressFactory $addressItemFactory;
    /**
     * @var Session
     */
    protected Session $session;
    /**
     * @var AddressRepositoryInterface
     */
    protected AddressRepositoryInterface $addressRepositoryInterface;
    /**
     * @var SubscriptionInterface
     */
    protected SubscriptionInterface $payment;
    /**
     * @var SubscriptionRepository
     */
    protected SubscriptionRepository $payRepositoryInterface;
    /**
     * @var SubFactory
     */
    protected SubscriptionFactory $paymentCollectionFactory;
    /**
     * @var Subscription
     */
    protected Subscription $subId;
    /**
     * @var SubFactory
     */
    protected SubFactory $factory;
    /**
     * @var RepositoryInterface
     */
    protected RepositoryInterface $repo;
    /**
     * @var SubscriptionManagement
     */
    protected SubscriptionManagement $subscriptionManagement;
    /**
     * @var RecurringPayments
     */
    protected RecurringPayments $productData;
    /**
     * @var GetCurrentProductService
     */
    protected GetCurrentProductService $service;
    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $collectionFactory;
    /**
     * @var ResultFactory
     */
    protected ResultFactory $resultFactory;
    /**
     * @var Data
     */
    private Data $helper;

    /**
     * @var SessionFactory
     */
    private SessionFactory $sessionFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param JsonFactory $resultJsonFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $request
     * @param ProductRepository $productRepository
     * @param CartItemInterfaceFactory $cartItemFactory
     * @param ItemFactory $orderItemFactory
     * @param ManagerInterface $messageManager
     * @param RedirectFactory $resultRedirectFactory
     * @param Data $helper
     * @param Registry $registry
     * @param CartRepositoryInterface $quoteRepository
     * @param SubscriptionInterface $subscription
     * @param AddressInterface $addressInterface
     * @param AddressFactory $addressItemFactory
     * @param Session $session
     * @param CollectionFactory $collectionFactory
     * @param AdressCollectionFactory $adresscollectionfactory
     * @param AddressRepositoryInterface $addressRepositoryInterface
     * @param SubscriptionInterface $payment
     * @param SubscriptionRepository $payRepoitoryIterface
     * @param SubscriptionFactory $paymentCollectionFactory
     * @param Subscription $subId
     * @param SubFactory $factory
     * @param RepositoryInterface $repo
     * @param SubscriptionManagement $subscriptionManagement
     * @param RecurringPayments $productData
     * @param GetCurrentProductService $service
     */
    public function __construct(
        Context                    $context,
        PageFactory                $resultPageFactory,
        JsonFactory                $resultJsonFactory,
        OrderRepositoryInterface   $orderRepository,
        RequestInterface           $request,
        ProductRepository          $productRepository,
        CartItemInterfaceFactory   $cartItemFactory,
        ItemFactory                $orderItemFactory,
        ManagerInterface           $messageManager,
        RedirectFactory            $resultRedirectFactory,
        Data                       $helper,
        CartRepositoryInterface    $quoteRepository,
        SubscriptionInterface      $subscription,
        AddressFactory             $addressItemFactory,
        CollectionFactory          $collectionFactory,
        AddressRepositoryInterface $addressRepositoryInterface,
        SubscriptionInterface      $payment,
        SubscriptionRepository     $payRepoitoryIterface,
        SubscriptionFactory        $paymentCollectionFactory,
        Subscription               $subId,
        SubFactory                 $factory,
        RepositoryInterface        $repo,
        SubscriptionManagement     $subscriptionManagement,
        RecurringPayments          $productData,
        GetCurrentProductService   $service,
        SessionFactory             $sessionFactory,
        ResultFactory              $redirect,
        ResultFactory              $resultFactory

    )
    {

        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->productRepository = $productRepository;
        $this->orderItemFactory = $orderItemFactory;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->helper = $helper;
        $this->subscription = $subscription;
        $this->quoteRepository = $quoteRepository;
        $this->cartItemFactory = $cartItemFactory;
        $this->addressItemFactory = $addressItemFactory;
        $this->collectionFactory = $collectionFactory;
        $this->addressRepositoryInterface = $addressRepositoryInterface;
        $this->payment = $payment;
        $this->payRepositoryInterface = $payRepoitoryIterface;
        $this->paymentCollectionFactory = $paymentCollectionFactory;
        $this->subId = $subId;
        $this->factory = $factory;
        $this->repo = $repo;
        $this->subscriptionManagement = $subscriptionManagement;
        $this->productData = $productData;
        $this->service = $service;
        $this->sessionFactory = $sessionFactory;
        $this->redirect = $redirect;
        $this->resultFactory = $resultFactory;
    }


    /**
     * @throws CouldNotSaveException
     */
    public function execute()
    {
        $paramData = $this->request->getParam('ExtendButton');
        $orderId = $this->request->getParam('order_id');
        $order = $this->orderRepository->get($orderId);
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $idd = $this->subId->getCollection();

        foreach ($idd as $key)

            if ($key->getData('order_id') == $orderId) {
                if ($key->getData('status') == 'active') {
                    $enddate = $key->getData('end_date');
                    if (isset($enddate)) {
                        $key->setEndDate($paramData);
                        $this->payRepositoryInterface->save($key);

                    } else {
                        $this->messageManager->addErrorMessage(__('End date not Available so subscription cannot be extended.'));
                        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
                    }
                }
            }


        $this->orderRepository->save($order);
        $this->messageManager->addSuccessMessage(__('Extended successfully.'));
        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);

    }

}
