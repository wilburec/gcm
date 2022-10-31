<?php

namespace Drupal\group_content_menu;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the parent form selector service.
 */
class GroupContentMenuServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($definition = $container->getDefinition('menu.parent_form_selector')) {
      $definition->setClass(GroupContentMenuParentFormSelector::class);
      $definition->addArgument(new Reference('group_content_menu.menu_id_helper'));
    }
  }

}
