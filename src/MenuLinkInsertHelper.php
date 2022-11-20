<?php

namespace Drupal\group_content_menu;

use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

class MenuLinkInsertHelper {

  /**
   *
   */
  public function __construct(protected MenuTreeStorageInterface $menuTreeStorage) {

  }

  public function __invoke(MenuLinkContentInterface $menu_link_content) {
    // GroupContentMenuController::addLink sets this up.
    if ($menu_link_content->isGroupContentMenu) {
      $plugin_id = $menu_link_content->getPluginId();
      $definition = $this->menuTreeStorage->load($plugin_id);
      $definition['provider'] = 'group_content_menu';
      $this->menuTreeStorage->save($definition);
    }
  }

}
