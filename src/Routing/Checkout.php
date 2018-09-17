<?php

namespace Drupal\commerce_google_tag_manager\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class Checkout.
 *
 * Listens to the dynamic route events.
 */
class Checkout extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('commerce_checkout.form')) {
      $route->addDefaults([
        '_controller' => '\Drupal\commerce_google_tag_manager\Controller\CheckoutControllerIncludingTagManager::formPage',
      ]);
    }
  }

}
