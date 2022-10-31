<?php

namespace Drupal\group_content_menu;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuParentFormSelector;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Group content menu implementation of the menu parent form selector service.
 *
 * The form selector is a list of all appropriate menu links.
 */
class GroupContentMenuParentFormSelector extends MenuParentFormSelector {

  /**
   *
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $string_translation, protected MenuIdHelperInterface $menuIdHelper) {
    parent::__construct($menu_link_tree, $entity_type_manager, $string_translation);
  }

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
    [$menu_name, $menu_link_plugin_id] = explode(':', $menu_parent, 2);
    if ($this->menuIdHelper->getGroupContentMenuId($menu_name, $menu_link_plugin_id)) {
      $this->isGroupMenu = TRUE;
    }
    $element = parent::parentSelectElement($menu_parent, $id, $menus);
    // Add the group content list tag in case a menu is created, deleted, etc.
    $element['#cache']['tags'] = $element['#cache']['tags'] ?? [];
    $element['#cache']['tags'] = Cache::mergeTags($element['#cache']['tags'], ['group_content_list']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getMenuOptions(array $menu_names = NULL) {
    if (!$this->isGroupMenu) {
      return parent::getMenuOptions($menu_names);
    }
    if (!$route_group = \Drupal::routeMatch()->getParameter('group')) {
      return [];
    }
    $group_content_menus = $this->entityTypeManager->getStorage('group_content_menu')->loadMultiple($menu_names);
    $options = [];
    /** @var \Drupal\group_content_menu\GroupContentMenuInterface[] $menus */
    foreach ($group_content_menus as $group_content_menu) {
      $group_contents = $this->entityTypeManager->getStorage('group_content')->loadByEntity($group_content_menu);
      if ($group_contents) {
        $menu_group = array_pop($group_contents)->getGroup();
        if ($menu_group->id() === $route_group->id()) {
          $options[$group_content_menu->getMenuName()] = $group_content_menu->label() . " ({$menu_group->label()})";
        }
      }
    }
    return $options;
  }

}
