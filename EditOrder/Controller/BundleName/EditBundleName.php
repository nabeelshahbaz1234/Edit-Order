<?php

declare(strict_types=1);

namespace Meiko\EditOrder\Controller\BundleName;

use DateTime;
use Exception;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * @class EditBillingAddress
 */
class EditBundleName implements ActionInterface
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
     * @var OrderItemRepositoryInterface
     */
    private OrderItemRepositoryInterface $orderItemRepository;


    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param RequestInterface $requestInterface
     * @param Redirect $resultRedirectFactory
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param ManagerInterface $messageManager
     * @param OrderItemRepositoryInterface $orderItemRepository
     */
    public function __construct(
        OrderRepositoryInterface     $orderRepository,
        RequestInterface             $requestInterface,
        Redirect                     $resultRedirectFactory,
        ResultFactory                $resultFactory,
        UrlInterface                 $url,
        ManagerInterface             $messageManager,
        OrderItemRepositoryInterface $orderItemRepository

    )
    {
        $this->orderRepository = $orderRepository;
        $this->requestInterface = $requestInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->orderItemRepository = $orderItemRepository;
    }

    /**
     * Edit shipping address
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {

        $bundleName = $this->requestInterface->getParam('bundle_name');
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
            /** @var Item $item */
            foreach ($order->getItems() as $item) {

                if ($item->getItemId()) {


                        $option = $item->getProductOptions();
                        $option['info_buyRequest']['bundlename'] = $bundleName;
                        $item->setProductOptions($option)->save();



                }
            }
            $this->orderItemRepository->save($item);
            $this->orderRepository->save($order);
            $this->messageManager->addSuccessMessage(__('Bundle Name is updated successfully'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Order not found:
            Something went wrong
            %1', $e->getMessage()));
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(__('Error updating Bundle Name: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
