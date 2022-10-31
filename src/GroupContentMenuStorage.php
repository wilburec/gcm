<?php

namespace Drupal\group_content_menu;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class GroupContentMenuStorage extends SqlContentEntityStorage implements GroupContentMenuStorageInterface {

  /**
   *
   */
  public function __construct(EntityTypeInterface $entity_type, Connection $database, EntityFieldManagerInterface $entity_field_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityTypeManagerInterface $entity_type_manager, protected MenuLinkTreeInterface $menuLinkTree, protected MenuLinkManagerInterface $menuLinkManager) {
    parent::__construct($entity_type, $database, $entity_field_manager, $cache, $language_manager, $memory_cache, $entity_type_bundle_info, $entity_type_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('database'),
      $container->get('entity_field.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager'),
      $container->get('entity.memory_cache'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('menu.link_tree'),
      $container->get('plugin.manager.menu.link')
    );
  }

  /**
   *
   */
  protected function doPreSave(EntityInterface $group_content_menu) {
    $return = parent::doPreSave($group_content_menu);
    assert($group_content_menu instanceof GroupContentMenuInterface);
    $original = $group_content_menu->original;
    if ($original instanceof GroupContentMenuInterface && !$original->get('parent')->filterEmptyItems()->equals($group_content_menu->get('parent')->filterEmptyItems())) {
      $update = [
        'menu_name' => $group_content_menu->getMenuName(),
        'parent' => $group_content_menu->parent->id,
      ];
      $tree = $this->loadMenuTree($original, new MenuTreeParameters());
      $this->updateTree($tree, $update, $original->parent->isEmpty());
    }
    return $return;
  }

  /**
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $tree
   *   The menu tree.
   * @param array $update
   *   The update information to be passed to
   *   MenuLinkManager::UpdateDefinition().
   * @param $do
   *   Whether this level of tree should be updated or not. When the parent of
   *   the group content menu entity is not empty then the loaded menu tree
   *   includes its siblings which should not be updated. Submenus and
   *   standalone menus should always be updated.
   *
   * @return void
   */
  public function updateTree(array $tree, array $update, $do): void {
    $menu_link_content_storage = $this->entityTypeManager->getStorage('menu_link_content');
    foreach ($tree as $menu_link_tree_element) {
      $this->updateTree($menu_link_tree_element->subtree, $update, TRUE);
      $link = $menu_link_tree_element->link;
      if ($do && $link instanceof MenuLinkContent) {
        $menu_link_content_entity = $menu_link_content_storage
          ->load($link->getPluginDefinition()['metadata']['entity_id']);
        if ($menu_link_content_entity instanceof ContentEntityInterface) {
          $this->menuLinkManager->updateDefinition($link->getPluginId(), $update);
        }
      }
    }
  }

  /**
   * @param \Drupal\group_content_menu\GroupContentMenuInterface $group_content_menu
   * @param \Drupal\Core\Menu\MenuTreeParameters $parameters
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   */
  public function loadMenuTree(GroupContentMenuInterface $group_content_menu, MenuTreeParameters $parameters) {
    if (!$parameters->root) {
      $parameters->setRoot($group_content_menu->parent->id);
    }
    return $this->menuLinkTree->load($group_content_menu->getMenuName(), $parameters);
  }

}
