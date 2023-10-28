<?php

declare(strict_types=1);

namespace Meiko\EditOrder\Controller\Submit;

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
 * @class EditBillingAddress
 */
class EditBillingAddress implements ActionInterface
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
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $requestInterface
     * @param Redirect $resultRedirectFactory
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        RequestInterface         $requestInterface,
        Redirect                 $resultRedirectFactory,
        ResultFactory            $resultFactory,
        UrlInterface             $url,
        ManagerInterface         $messageManager

    )
    {
        $this->orderRepository = $orderRepository;
        $this->requestInterface = $requestInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
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


        try {
            /** @var  $order */
            $order = $this->orderRepository->get($orderId);
            /** @var  $billingAddress */
            $billingAddress = $order->getBillingAddress();
            $billingAddressData = $this->requestInterface->getPostValue();

            $billingAddress->setFirstname($billingAddressData['firstname']);
            $billingAddress->setLastname($billingAddressData['lastname']);
            $billingAddress->setStreet($billingAddressData['street']);
            $billingAddress->setCity($billingAddressData['city']);
            $billingAddress->setCountryId($billingAddressData['country_id']);
            $billingAddress->setPostcode($billingAddressData['postcode']);
            $billingAddress->setTelephone($billingAddressData['telephone']);
            $this->orderRepository->save($order);
            $this->messageManager->addSuccessMessage(__('Billing address updated successfully'));
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
