<?php

namespace Bluethink\DeliveryDate\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Checkout\Model\Session as CheckoutSession;

class SaveDeliveryDateToOrder implements ObserverInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * @param CheckoutSession $checkoutSession
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
    }


    /**
     * @param Observer $observer
     * @return $this|void
     */
    public function execute(Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $quote = $this->checkoutSession->getQuote();
            $maxDelayedDelivery = '';
            $maxDeliveryTime = '';
            $today = date("Y-m-d");
            $orderDeliveryDate = date('Y-m-d', strtotime($today . ' + 10 days'));
            foreach ($quote->getAllItems() as $item) {
                $product = $this->getProductBySku($item->getProduct()->getSku());
                if ($product) {
                    $maxDelayedDelivery = ($maxDelayedDelivery < $product->getProductDelayedDelivery())
                        ? $product->getProductDelayedDelivery() : $maxDelayedDelivery;

                    $maxDeliveryTime = ($maxDeliveryTime < $product->getProductDeliveryTime())
                        ? $product->getProductDeliveryTime() : $maxDeliveryTime;
                }
            }

            if (empty($maxDelayedDelivery) && $maxDeliveryTime) {
                $orderDeliveryDate = date('Y-m-d', strtotime($today . ' + ' . $maxDeliveryTime . ' days'));

            } elseif (!empty($maxDelayedDelivery)) {
                $orderDeliveryDate = $maxDelayedDelivery;
            }

            if ($orderDeliveryDate) {
                $order->setDeliveryDate($orderDeliveryDate);
                $order->save();
            }
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
        return $this;
    }

    /**
     * @param $sku
     * @return \Magento\Catalog\Api\Data\ProductInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProductBySku($sku)
    {
        try {
            $product = $this->productRepository->get($sku);
            return $product;
        } catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
        return false;
    }

}
