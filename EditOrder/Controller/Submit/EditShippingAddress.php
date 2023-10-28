<?php
declare(strict_types=1);

namespace Meiko\EditOrder\Controller\Submit;

use Amasty\RecurringPayments\Api\Subscription\AddressRepositoryInterface;
use Amasty\RecurringPayments\Model\Repository\SubscriptionRepository;
use Amasty\RecurringPayments\Model\Subscription;
use Amasty\RecurringPayments\Model\SubscriptionFactory;
use Exception;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * @class EditShippingAddress
 */
class EditShippingAddress implements ActionInterface
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
     * @var Subscription
     */
    private Subscription $subscription;
    /**
     * @var SubscriptionRepository
     */
    private SubscriptionRepository $subscriptionRepository;
    /**
     * @var SubscriptionFactory
     */
    private SubscriptionFactory $subscriptionFactory;
    /**
     * @var AddressRepositoryInterface
     */
    private AddressRepositoryInterface $addressRepositoryInterface;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $requestInterface
     * @param Redirect $resultRedirectFactory
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param ManagerInterface $messageManager
     * @param Subscription $subscription
     * @param SubscriptionRepository $subscriptionRepository
     * @param SubscriptionFactory $subscriptionFactory
     * @param AddressRepositoryInterface $addressRepositoryInterface
     */
    public function __construct(
        OrderRepositoryInterface   $orderRepository,
        RequestInterface           $requestInterface,
        Redirect                   $resultRedirectFactory,
        ResultFactory              $resultFactory,
        UrlInterface               $url,
        ManagerInterface           $messageManager,
        Subscription               $subscription,
        SubscriptionRepository     $subscriptionRepository,
        SubscriptionFactory        $subscriptionFactory,
        AddressRepositoryInterface $addressRepositoryInterface

    )
    {
        $this->orderRepository = $orderRepository;
        $this->requestInterface = $requestInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->subscription = $subscription;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->subscriptionFactory = $subscriptionFactory;
        $this->addressRepositoryInterface = $addressRepositoryInterface;
    }

    /**
     * Edit shipping address
     *
     * @return Redirect
     * @throws NoSuchEntityException
     */
    public function execute(): Redirect
    {
        /** @var  $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        /** @var  $orderId */
        $orderId = $this->requestInterface->getParam('id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order ID is missing.'));
            return $resultRedirect->setUrl($this->url->getUrl('sales/order/history'));
        }
        /** @var  $subscriptionCollection */
        $subscriptionCollection = $this->subscription->getCollection();
        foreach ($subscriptionCollection as $key) {
            /** Update Delivery Address for the subscription */
            if ($key->getData('order_id') == $orderId) {
                $paymentId = $key->getData('id');
                $pay = $this->subscriptionRepository->getById($paymentId)->getAddressId();
                $SubscriptionAddress = $this->addressRepositoryInterface->getById($pay);
                $SubscriptionAddressData = $this->requestInterface->getPostValue();
                $SubscriptionAddress
                    ->setCountryId($SubscriptionAddressData['country_id'])
                    ->setCity($SubscriptionAddressData['city'])
                    ->setFirstname($SubscriptionAddressData['firstname'])
                    ->setLastname($SubscriptionAddressData['lastname'])
                    ->setPostcode($SubscriptionAddressData['postcode'])
                    ->setStreet($SubscriptionAddressData['street'])
                    ->setTelephone($SubscriptionAddressData['telephone']);
                $this->addressRepositoryInterface->save($SubscriptionAddress);
            }

        }

        try {
            /** @var  $order */
            $order = $this->orderRepository->get($orderId);
            $address = $order->getShippingAddress();
            /** Update Delivery Address for the order @var  $addressData */
            $addressData = $this->requestInterface->getPostValue();

            $address->setFirstname($addressData['firstname']);
            $address->setLastname($addressData['lastname']);
            $address->setStreet($addressData['street']);
            $address->setCity($addressData['city']);
            $address->setCountryId($addressData['country_id']);
            $address->setPostcode($addressData['postcode']);
            $address->setTelephone($addressData['telephone']);
            $address->save();
               /** Save Data in Sales Order Table */
            $this->orderRepository->save($order);
            $this->messageManager->addSuccessMessage(__('Shipping address address updated successfully'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Order not found:
            Something went wrong
            %1', $e->getMessage()));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Error updating Shipping address: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
