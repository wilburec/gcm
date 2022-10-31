<?php

namespace Drupal\group_content_menu;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Menu\MenuTreeParameters;

/**
 *
 */
interface GroupContentMenuStorageInterface extends ContentEntityStorageInterface {

  /**
   *
   */
  public function loadMenuTree(GroupContentMenuInterface $group_content_menu, MenuTreeParameters $parameters);

}
