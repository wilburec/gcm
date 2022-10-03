<?php

namespace Drupal\group_content_submenu;

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\group_content_menu\Entity\GroupContentMenu;
use Drupal\group_content_menu\GroupContentMenuInterface;

class MenuLinkManager implements MenuLinkManagerInterface {

  public function __construct(protected MenuLinkManagerInterface $original) {
  }

  public function addDefinition($id, array $definition) {
    $instance = $this->original->addDefinition($id, $definition);
    if ($gcm = $this->getGcm($definition)) {
      $this->original->addDefinition(ShadowlinkHelper::getShadowlinkId($id), ShadowlinkHelper::getShadowLinkDefinition($definition, $gcm));
    }
    return $instance;
  }

  public function updateDefinition($id, array $new_definition_values, $persist = TRUE) {
    $instance = $this->original->updateDefinition($id, $new_definition_values, $persist);
    $definition = $instance->getPluginDefinition();
    if ($gcm = $this->getGcm($definition)) {
      $this->original->updateDefinition(ShadowlinkHelper::getShadowlinkId($id), ShadowlinkHelper::getShadowLinkDefinition($definition, $gcm));
    }
    return $instance;
  }

  public function removeDefinition($id, $persist = TRUE) {
    $this->original->removeDefinition($id, $persist);
    $shadow_link_id = ShadowlinkHelper::getShadowlinkId($id);
    if ($this->original->hasDefinition($shadow_link_id)) {
      $this->original->removeDefinition($shadow_link_id);
    }
  }

  public function resetLink($id) {
    $instance = $this->original->resetLink($id);
    $shadow_link_id = ShadowlinkHelper::getShadowlinkId($id);
    if ($this->original->hasDefinition($shadow_link_id)) {
      $this->original->resetLink($shadow_link_id);
    }
    return $instance;
  }

  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    return $this->original->getDefinition($plugin_id, $exception_on_invalid);
  }

  public function getDefinitions() {
    return $this->original->getDefinitions();
  }

  public function hasDefinition($plugin_id) {
    return $this->original->hasDefinition($plugin_id);
  }

  public function createInstance($plugin_id, array $configuration = []) {
    return $this->original->createInstance($plugin_id, $configuration);
  }

  public function getInstance(array $options) {
    return $this->original->getInstance($options);
  }

  public function rebuild() {
    $this->original->rebuild();
  }

  public function deleteLinksInMenu($menu_name) {
    $this->original->deleteLinksInMenu($menu_name);
  }

  public function loadLinksByRoute($route_name, array $route_parameters = [], $menu_name = NULL) {
    return $this->original->loadLinksByRoute($route_name, $route_parameters, $menu_name);
  }

  public function countMenuLinks($menu_name = NULL) {
    return $this->original->countMenuLinks($menu_name);
  }

  public function getParentIds($id) {
    return $this->original->getParentIds($id);
  }

  public function getChildIds($id) {
    return $this->original->getChildIds($id);
  }

  public function menuNameInUse($menu_name) {
    return $this->original->menuNameInUse($menu_name);
  }

  public function resetDefinitions() {
    return $this->original->resetDefinitions();
  }

  /**
   * @param array $definition
   *
   * @return \Drupal\group_content_menu\GroupContentMenuInterface|null
   */
  public function getGcm(array $definition): ?GroupContentMenuInterface {
    $prefix = GroupContentMenuInterface::MENU_PREFIX;
    if (preg_match("/^$prefix(\d+)$/", $definition['menu_name'], $matches)) {
      $gcm = GroupContentMenu::load($matches[1]);
      if ($gcm->hasField('parent_menu_name') && $gcm->hasField('parent_menu_link') && !$gcm->parent_menu_name->isEmpty()) {
        return $gcm;
      }
    }
    return NULL;
  }

}
