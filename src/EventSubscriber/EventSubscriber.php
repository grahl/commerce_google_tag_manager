<?php

namespace Drupal\commerce_google_tag_manager\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_google_tag_manager\CommerceDataTrait;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class EventSubscriber.
 */
class EventSubscriber implements EventSubscriberInterface {

  use CommerceDataTrait;

  protected $config;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleHandler $module_handler, ConfigFactory $config_factory) {
    $this->moduleHandler = $module_handler;
    $this->config = $config_factory->get('system.site');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      CartEvents::CART_ENTITY_ADD => 'addToCart',
      CartEvents::CART_ORDER_ITEM_REMOVE => 'removeFromCart',
      'commerce_order.place.post_transition' => 'purchase',
    ];
    return $events;
  }

  /**
   * Add to cart.
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   Event triggered, contains order item.
   */
  public function addToCart(CartEntityAddEvent $event) {
    $this->eventContext = 'add_to_cart';

    $data = [
      'currencyCode' => $event->getOrderItem()->getTotalPrice()->getCurrencyCode(),
      'add' => [
        'products' => [$this->getLineItemData($event->getOrderItem())],
      ],
    ];

    $this->pushCommerceData($data);
  }

  /**
   * Remove from cart.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemRemoveEvent $event
   *   Event triggered, contains order item.
   */
  public function removeFromCart(CartOrderItemRemoveEvent $event) {
    $this->eventContext = 'remove_from_cart';

    $data = [
      'currencyCode' => $event->getOrderItem()->getTotalPrice()->getCurrencyCode(),
      'remove' => [
        'products' => [$this->getLineItemData($event->getOrderItem())],
      ],
    ];

    $this->pushCommerceData($data);
  }

  /**
   * Workflow transitioned to completion.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   *
   * @TODO: Find out if this can be variable, be triggered elsewhere.
   */
  public function purchase(WorkflowTransitionEvent $event) {
    $this->eventContext = 'purchase';

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    if (!$order->getItems()) {
      // This can occur on reloading an already submitted order form.
      return;
    }

    $data = [
      'currencyCode' => $order->getTotalPrice()->getCurrencyCode(),
      'purchase' => [
        'actionField' => $this->getOrderData($order),
        'products' => $this->getLineItemsData($order->getItems()),
      ],
    ];

    $this->pushCommerceData($data);
  }

  /**
   * Prepares order attributes.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return array
   *   Processed data for GA.
   */
  protected function getOrderData(OrderInterface $order) {
    $order_data = [
      'id' => $order->getOrderNumber(),
      'affiliation' => $this->config->get('site_name'),
      'revenue' => $order->getTotalPrice()->getNumber(),
      'tax' => $this->computeTax($order),
      'shipping' => $this->computeShipping($order),
    ];

    // Allow other modules to alter this order data.
    $context = ['order' => $order, 'event' => $this->eventContext];
    $this->moduleHandler->alter('commerce_google_tag_manager_order_data', $order_data, $context);

    return $order_data;
  }

  /**
   * Compute tax, if enabled.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return float
   *   Tax amount in decimal.
   */
  protected function computeTax(OrderInterface $order) {
    $tax_sum = 0;
    if ($this->moduleHandler->moduleExists('commerce_tax')) {
      $items = $order->getItems();
      foreach ($items as $item) {
        foreach ($item->getAdjustments() as $adjustment) {
          // TODO: It might make sense to make it configurable to have
          // excluded tax not be counted here.
          $tax_sum += $adjustment->getAmount()->getNumber();
        }
      }
    }
    return $tax_sum;
  }

  /**
   * Compute shipping, if enabled.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return float
   *   Shipping amount in decimal.
   */
  protected function computeShipping(OrderInterface $order) {
    $shipping = 0;
    if ($this->moduleHandler->moduleExists('commerce_shipping')) {
      if ($order->get('shipments')) {
        /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment */
        $shipment = $order->get('shipments')->entity;
        $shipping = $shipment->getAmount()->getNumber();
      }
    }
    return $shipping;
  }

}
