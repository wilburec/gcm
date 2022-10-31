<?php

namespace Drupal\group_content_menu;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;

/**
 * Get the id of hte group content menu entity.
 */
class MenuIdHelper implements MenuIdHelperInterface {

  protected EntityStorageInterface $gcmStorage;

  /**
   *
   */
  public function __construct(protected MenuTreeStorageInterface $menuTreeStorage, EntityTypeManagerInterface $entityTypeManager) {
    $this->gcmStorage = $entityTypeManager->getStorage('group_content_menu');
  }

  /**
   *
   */
  public function getGroupContentMenuId($menu_name, $menu_link_plugin_id): string {
    if (preg_match('/^' . GroupContentMenuInterface::MENU_PREFIX . '(\d+)/', $menu_name, $matches)) {
      return $matches[1];
    }
    elseif ($menu_link_plugin_id) {
      $root_path_ids = $this->menuTreeStorage->getRootPathIds($menu_link_plugin_id);
      $ids = $this->gcmStorage->getQuery()
        ->condition('parent.id', $root_path_ids, 'IN')
        // @todo is this correct?
        ->accessCheck(TRUE)
        ->execute();
      return reset($ids);
    }
    else {
      $ids = $this->gcmStorage->getQuery()
        ->condition('parent.menu_name', $menu_name)
        ->accessCheck(TRUE)
        ->execute();
      return reset($ids);
    }
  }

}
