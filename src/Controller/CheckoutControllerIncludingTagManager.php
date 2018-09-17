<?php

namespace Drupal\commerce_google_tag_manager\Controller;

use Drupal\commerce_cart\CartSessionInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_checkout\Controller\CheckoutController;
use Drupal\commerce_google_tag_manager\CommerceDataTrait;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the checkout form page.
 */
class CheckoutControllerIncludingTagManager extends CheckoutController {

  use CommerceDataTrait;

  /**
   * Constructs a new CheckoutController object.
   *
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $checkout_order_manager
   *   The checkout order manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\commerce_cart\CartSessionInterface $cart_session
   *   The cart session.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   */
  public function __construct(
    CheckoutOrderManagerInterface $checkout_order_manager,
    FormBuilderInterface $form_builder,
    CartSessionInterface $cart_session,
    ModuleHandler $module_handler) {
    parent::__construct($checkout_order_manager, $form_builder, $cart_session);
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_checkout.checkout_order_manager'),
      $container->get('form_builder'),
      $container->get('commerce_cart.cart_session'),
      $container->get('module_handler')
    );
  }

  /**
   * Builds and processes the form provided by the order's checkout flow.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   The render form.
   */
  public function formPage(RouteMatchInterface $route_match) {
    $this->trackCheckout($route_match->getParameter('commerce_order'), $route_match->getParameter('step'));
    return parent::formPage($route_match);
  }

  /**
   * Track checkout step.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param string $step
   *   Step requested by user.
   */
  protected function trackCheckout(OrderInterface $order, $step) {
    $this->eventContext = 'checkout';

    if (!$order->getItems()) {
      // This can occur on reloading an already submitted order form.
      return;
    }

    if (is_null($step)) {
      // The parent function will redirect this case.
      return;
    }

    $productsData = $this->getLineItemsData($order->getItems());

    $data = [
      'currencyCode' => $order->getTotalPrice()->getCurrencyCode(),
      'checkout' => [
        'products' => $productsData,
      ],
    ];

    if (!is_null($step)) {
      $data['checkout']['actionField']['step'] = $step;
    }

    $this->pushCommerceData($data);
  }

}
