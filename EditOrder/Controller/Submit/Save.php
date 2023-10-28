<?php
declare(strict_types=1);

namespace Meiko\EditOrder\Controller\Submit;

use Amasty\RecurringPayments\Model\Repository\SubscriptionRepository;
use Amasty\RecurringPayments\Model\ResourceModel\Subscription\CollectionFactory;
use Amasty\RecurringPayments\Model\Subscription;
use Exception;
use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Model\ProductRepository;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * @class Save
 */
class Save implements ActionInterface
{
    /**
     * @var RequestInterface
     */
    protected RequestInterface $requestInterface;
    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;
    /**
     * @var Redirect
     */
    protected Redirect $resultRedirectFactory;
    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;
    /**
     * @var ResultFactory
     */
    private ResultFactory $resultFactory;
    /**
     * @var UrlInterface
     */
    private UrlInterface $url;
    /**
     * @var StockRegistryInterface
     */
    private StockRegistryInterface $stockRegistry;
    /**
     * @var ProductFactory
     */
    private ProductFactory $productFactory;
    /**
     * @var JsonFactory
     */
    private JsonFactory $jsonFactory;

    /**
     * @var Subscription
     */
    private Subscription $subscription;
    /**
     * @var SubscriptionRepository
     */
    private SubscriptionRepository $subscriptionRepository;
    /**
     * @var OrderItemRepositoryInterface
     */
    private OrderItemRepositoryInterface $orderItemRepository;
    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private ProductRepository $productRepository;


    /**
     * @param RequestInterface $requestInterface
     * @param ManagerInterface $messageManager
     * @param Redirect $resultRedirectFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param ResultFactory $resultFactory
     * @param UrlInterface $url
     * @param StockRegistryInterface $stockRegistry
     * @param ProductFactory $productFactory
     * @param JsonFactory $jsonFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Subscription $subscription
     * @param SubscriptionRepository $subscriptionRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param CollectionFactory $collectionFactory
     * @param ProductRepository $productRepository
     */
    public function __construct(RequestInterface $requestInterface, ManagerInterface $messageManager, Redirect $resultRedirectFactory, OrderRepositoryInterface $orderRepository, ResultFactory $resultFactory, UrlInterface $url, StockRegistryInterface $stockRegistry, ProductFactory $productFactory, JsonFactory $jsonFactory, SearchCriteriaBuilder $searchCriteriaBuilder, Subscription $subscription, SubscriptionRepository $subscriptionRepository, OrderItemRepositoryInterface $orderItemRepository, CollectionFactory $collectionFactory, ProductRepository $productRepository)
    {
        $this->requestInterface = $requestInterface;
        $this->messageManager = $messageManager;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->orderRepository = $orderRepository;
        $this->resultFactory = $resultFactory;
        $this->url = $url;
        $this->stockRegistry = $stockRegistry;
        $this->productFactory = $productFactory;
        $this->jsonFactory = $jsonFactory;
        $this->subscription = $subscription;
        $this->subscriptionRepository = $subscriptionRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->collectionFactory = $collectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productRepository = $productRepository;
    }

    /**
     * @throws Exception
     */
    public function execute()
    {
        /** @var  $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        /** @var $orderId */
        $orderId = $this->requestInterface->getParam('id');
        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Order ID is missing.'));
            return $resultRedirect->setUrl($this->url->getUrl('sales/order/history'));
        }
        /** @var  $quantity */
        $quantity = $this->requestInterface->getParam('qty');


        try {
            $order = $this->orderRepository->get($orderId);
            /** @var  $subscriptionCollection */
            $subscriptionCollection = $this->collectionFactory->create();
            $subscriptionCollection->addFieldToFilter('order_id', $orderId)->addFieldToFilter('status', ['neq' => 'canceled']);


            /** @var Item $item */
            foreach ($order->getItems() as $item) {
                foreach ($subscriptionCollection as $subscription) {
                    $subscriptionProductIds = $subscription->getData('product_id');
                    $productId = $item->getProductId();
                    $stockItem = $this->stockRegistry->getStockItem($productId);
                    if ($stockItem->getIsInStock()) {
                        if ($stockItem->getData('qty') > $quantity[$item->getId()]) {
                            /** Match Product id */
                            if ($subscriptionProductIds == $productId) {
                                $subscription->setQty($quantity[$item->getId()]);
                                /** The updated quantity for the subscription */
                                $subscription->setBaseGrandTotal($item->getPrice() * floatval($quantity[$item->getId()]));
                                /**  set GrandTotal for the subscription */
                                $subscription->setBaseGrandTotalWithDiscount($item->getPrice() * floatval($quantity[$item->getId()]));

                                /** Save Data in Subscription Table */
                                $this->subscriptionRepository->save($subscription);
                            }

                        }
                    } else {
                        $response = ['error' => true, 'message' => __('The requested quantity is not available')];
                        return $this->jsonFactory->create()->setData($response);
                    }

                }

                if ($item->getItemId()) {
                    $productId = $item->getProductId();
                    $stockItem = $this->stockRegistry->getStockItem($productId);
                    if ($stockItem->getIsInStock()) {
                        if ($stockItem->getData('qty') > $quantity[$item->getId()]) {

                            $productId = $item->getProductId();
                            $product = $this->productRepository->getById($productId);
                            $sku = $product->getSku();
                            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
                            if ($stockItem) {
                                $qty = $stockItem->getQty() - floatval($quantity[$item->getId()]);
                                $stockItem->setQty($qty);
                                $stockItem->setIsInStock((bool)$qty);
                            }
                            $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
                            $item->setQtyOrdered($quantity[$item->getId()]);
                        }else {
                            $response = ['error' => true, 'message' => __('The requested quantity is not available')];
                            return $this->jsonFactory->create()->setData($response);

                        }


                        /** The updated quantity for the Order */
                        $item->setRowTotal($item->getPrice() * floatval($quantity[$item->getId()]));
                        /**  updated Row Total for the Order Items */
                        $item->setBaseRowTotal($item->getPrice() * floatval($quantity[$item->getId()]));
                        $item->setRowTotalInclTax($item->getPrice() * floatval($quantity[$item->getId()]));
                        $item->setBaseRowTotalInclTax($item->getPrice() * floatval($quantity[$item->getId()]));
                        /** Save Data in order Items Table */
                        $this->orderItemRepository->save($item);


                    }
                    $total = 0;
                    $sub_total = 0;
                    $rowTotal = $item->getRowTotal();
                    $total += $sub_total;
                    $updatedRowTotal[] = $rowTotal;
                    $updatedRowTotalSum = array_sum($updatedRowTotal);
                    $order->setSubtotal($updatedRowTotalSum);
                    $order->setBaseSubtotal($updatedRowTotalSum - $item->getRowTotal() + $item->getBaseRowTotal());
                    $order->setGrandTotal($updatedRowTotalSum + $order->getShippingAmount() + $order->getTaxAmount() + $order->getDiscountAmount());
                    $order->setBaseGrandTotal($updatedRowTotalSum + $order->getShippingAmount() + $order->getTaxAmount() + $order->getBaseDiscountAmount());
                    /** Save Data in Order Table */
                    $this->orderRepository->save($order);

                }
            }

            $this->messageManager->addSuccessMessage(__('Order items has been updated successfully'));
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Order not found: %1', $e->getMessage()));
        }


        /** @var Redirect $resultRedirect */
        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
