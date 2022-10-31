<?php

namespace Drupal\group_content_menu;

/**
 * Get the id of hte group content menu entity.
 */
interface MenuIdHelperInterface {

  /**
   * Get the id of the group content menu entity this menu link belongs to.
   *
   * @param $menu_name
   *   The name of the menu this link belongs to.
   * @param $menu_link_plugin_id
   *   The plugin ID of this menu link, can be empty for root.
   */
  public function getGroupContentMenuId($menu_name, $menu_link_plugin_id): string;

}
