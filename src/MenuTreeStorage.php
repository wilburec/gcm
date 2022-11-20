<?php

namespace Drupal\group_content_menu;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorageInterface;

/**
 * MenuTreeStorage decorator.
 */
class MenuTreeStorage implements MenuTreeStorageInterface {

  use DependencySerializationTrait;

  /**
   * @param \Drupal\Core\Menu\MenuTreeStorageInterface $menuTreeStorage
   */
  public function __construct(protected MenuTreeStorageInterface $menuTreeStorage) {
  }

  /**
   * {@inheritdoc}
   */
  public function maxDepth() {
    return $this->menuTreeStorage->maxDepth();
  }

  /**
   * {@inheritdoc}
   */
  public function resetDefinitions() {
    return $this->menuTreeStorage->resetDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild(array $definitions) {
    return $this->menuTreeStorage->rebuild($definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $link) {
    return $this->menuTreeStorage->save($link);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($id) {
    return $this->menuTreeStorage->delete($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtreeHeight($id) {
    return $this->menuTreeStorage->getSubtreeHeight($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $properties) {
    return $this->menuTreeStorage->loadByProperties($properties);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByRoute($route_name, array $route_parameters = [], $menu_name = NULL) {
    return $this->menuTreeStorage
      ->loadByRoute($route_name, $route_parameters, $menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    return $this->menuTreeStorage->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    return $this->menuTreeStorage->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getRootPathIds($id) {
    return $this->menuTreeStorage->getRootPathIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function getExpanded($menu_name, array $parents) {
    return $this->menuTreeStorage->getExpanded($menu_name, $parents);
  }

  /**
   * {@inheritdoc}
   */
  public function loadTreeData($menu_name, MenuTreeParameters $parameters) {
    if (!$parameters instanceof GroupContentMenuTreeParameters) {
      $parameters->addCondition('provider', 'group_content_menu:%', 'NOT LIKE');
    }
    return $this->menuTreeStorage->loadTreeData($menu_name, $parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function loadSubtreeData($id, $max_relative_depth = NULL) {
    return $this->menuTreeStorage->loadSubtreeData($id, $max_relative_depth);
  }

  /**
   * {@inheritdoc}
   */
  public function menuNameInUse($menu_name) {
    return $this->menuTreeStorage->menuNameInUse($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuNames() {
    return $this->menuTreeStorage->getMenuNames();
  }

  /**
   * {@inheritdoc}
   */
  public function countMenuLinks($menu_name = NULL) {
    return $this->menuTreeStorage->countMenuLinks($menu_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllChildIds($id) {
    return $this->menuTreeStorage->getAllChildIds($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadAllChildren($id, $max_relative_depth = NULL) {
    return $this->menuTreeStorage->loadAllChildren($id, $max_relative_depth);
  }

}
