<?php

namespace Drupal\group_content_menu;

use Drupal\Core\Menu\MenuParentFormSelector;

/**
 * Group content menu implementation of the menu parent form selector service.
 *
 * The form selector is a list of all appropriate menu links.
 */
class GroupContentMenuParentFormSelector extends MenuParentFormSelector {

  /**
   * Determine if menu is a group menu.
   *
   * @var bool
   */
  protected $isGroupMenu = FALSE;

  /**
   * {@inheritdoc}
   */
  public function parentSelectElement($menu_parent, $id = '', array $menus = NULL) {
    if (strpos($menu_parent, GroupContentMenuInterface::MENU_PREFIX) !== FALSE) {
      $this->isGroupMenu = TRUE;
    }
    return parent::parentSelectElement($menu_parent, $id, $menus);

  }

  /**
   * {@inheritdoc}
   */
  protected function getMenuOptions(array $menu_names = NULL) {
    $entity_type = 'menu';
    if ($this->isGroupMenu) {
      $entity_type = 'group_content_menu';
    }

    $menus = $this->entityTypeManager->getStorage($entity_type)->loadMultiple($menu_names);
    $options = [];
    /** @var \Drupal\system\MenuInterface[] $menus */
    foreach ($menus as $menu) {
      if ($this->isGroupMenu) {
        if ($route_group = \Drupal::routeMatch()->getParameter('group')) {
          $group_contents = $this->entityTypeManager->getStorage('group_content')->loadByEntity($menu);
          if ($group_contents) {
            $menu_group = array_pop($group_contents)->getGroup();
            if ($menu_group->id() === $route_group->id()) {
              $options[GroupContentMenuInterface::MENU_PREFIX . $menu->id()] = $menu->label() . " ({$menu_group->label()})";
            }
          }
        }
      }
      else {
        $options[$menu->id()] = $menu->label();
      }

    }
    return $options;
  }

}
