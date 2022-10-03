<?php

namespace Drupal\group_content_submenu;

use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProxyMenulink extends PluginBase implements MenuLinkInterface, ContainerFactoryPluginInterface {

  protected MenuLinkInterface $plugin;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkManagerInterface $menu_link_manager) {
    $this->plugin = $menu_link_manager->createInstance($plugin_definition['metadata'], $configuration);
    parent::__construct($configuration, $plugin_id, $this->plugin->getPluginDefinition());
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.menu.link')
    );
  }

  public function getCacheContexts() {
    return $this->plugin->getCacheContexts();
  }

  public function getCacheTags() {
    return $this->plugin->getCacheTags();
  }

  public function getCacheMaxAge() {
    return $this->plugin->getCacheMaxAge();
  }

  public function getWeight() {
    return $this->plugin->getWeight();
  }

  public function getTitle() {
    return $this->plugin->getTitle();
  }

  public function getDescription() {
    return $this->plugin->getDescription();
  }

  public function getMenuName() {
    return $this->plugin->getMenuName();
  }

  public function getProvider() {
    return $this->plugin->getProvider();
  }

  public function getParent() {
    return $this->plugin->getParent();
  }

  public function isEnabled() {
    return $this->plugin->isEnabled();
  }

  public function isExpanded() {
    return $this->plugin->isExpanded();
  }

  public function isResettable() {
    return $this->plugin->isResettable();
  }

  public function isTranslatable() {
    return $this->plugin->isTranslatable();
  }

  public function isDeletable() {
    return $this->plugin->isDeletable();
  }

  public function getRouteName() {
    return $this->plugin->getRouteName();
  }

  public function getRouteParameters() {
    return $this->plugin->getRouteParameters();
  }

  public function getUrlObject($title_attribute = TRUE) {
    return $this->plugin->getUrlObject($title_attribute);
  }

  public function getOptions() {
    return $this->plugin->getOptions();
  }

  public function getMetaData() {
    return $this->plugin->getMetaData();
  }

  public function updateLink(array $new_definition_values, $persist) {
    return $this->plugin->updateLink($new_definition_values, $persist);
  }

  public function deleteLink() {
    return $this->plugin->deleteLink();
  }

  public function getFormClass() {
    return $this->plugin->getFormClass();
  }

  public function getDeleteRoute() {
    return $this->plugin->getDeleteRoute();
  }

  public function getEditRoute() {
    return $this->plugin->getEditRoute();
  }

  public function getTranslateRoute() {
    return $this->plugin->getTranslateRoute();
  }

}
