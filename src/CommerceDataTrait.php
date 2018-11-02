<?php

namespace Drupal\commerce_google_tag_manager;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Component\Serialization\Json;

/**
 * Trait containing generic handling of tag manager data.
 */
trait CommerceDataTrait {

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * What action is currently being performed (e.g. add_to_cart).
   *
   * @var string
   */
  protected $eventContext;

  /**
   * Builds and pushes the current commerce data.
   *
   * @param array $commerce_data
   *   Data to push.
   */
  protected function pushCommerceData(array $commerce_data) {
    $script = 'var dataLayer = dataLayer || []; ';

    $data = [
      'event' => $this->eventContext,
      'ecommerce' => $commerce_data,
    ];
    $context = ['event' => $this->eventContext];

    $this->moduleHandler->alter('commerce_google_tag_manager_commerce_data', $data, $context);

    // Add the data line to the JS array.
    $_SESSION['commerce_google_tag_manager'][] = $script . 'dataLayer.push(' . Json::encode($data) . ');';
  }

  /**
   * Gets an array-based representation of the given Line Item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return array
   *   Processed data for GA.
   */
  protected function getLineItemData(OrderItemInterface $order_item) {
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $purchased_entity */
    $purchased_entity = $order_item->getPurchasedEntity();
    $product_data = [
      'id' => $purchased_entity->getSku(),
      'name' => $purchased_entity->getTitle(),
      'category' => '',
      // TODO add variant back, was $item->commerce_product->getBundle().
      'variant' => '',
      'price' => $purchased_entity->getPrice()->getNumber(),
      'quantity' => $order_item->getQuantity(),
    ];

    // Allow other modules to alter this product data.
    $context = ['order-item' => $order_item, 'event' => $this->eventContext];
    $this->moduleHandler->alter('commerce_google_tag_manager_line_item_data', $product_data, $context);
    return $product_data;
  }

  /**
   * Fetch data from getLineItemData() for multiple items.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items
   *   The order items.
   *
   * @return array
   *   Processed data.
   */
  protected function getLineItemsData(array $order_items) {
    $products = [];
    foreach ($order_items as $order_item) {
      $products[] = $this->getLineItemData($order_item);
    }
    return array_filter($products);
  }

}
