<?php

namespace Drupal\group_content_submenu;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ShadowlinkHelper implements ContainerInjectionInterface {

  public function __construct(protected MenuLinkManagerInterface $menuLinkManager, protected MenuTreeStorageInterface $menuTreeStorage) {
  }

  public static function create(ContainerInterface $container) {
    return new static (
      $container->get('plugin.manager.menu.link'),
      $container->get('menu.tree_storage')
    );
  }


  public function groupContentMenuPresave(GroupContentMenuInterface $gcm) {
    if (!$gcm->hasField('parent_menu_name') || !$gcm->hasField('parent_menu_link')) {
      return;
    }
    $parent_menu_name_items = $gcm->get('parent_menu_name');
    $parent_menu_link_items = $gcm->get('parent_menu_link');
    $original = $gcm->original;
    if ($gcm->isNew()) {
      // New group content menu without a parent menu name, nothing to do.
      if ($parent_menu_name_items->isEmpty()) {
        return;
      }
    }
    // Not nwe but no changes: also nothing to do.
    elseif ($original instanceof GroupContentMenuInterface && $parent_menu_name_items->equals($original->get('parent_menu_name')) && $parent_menu_link_items->equals($original->get('parent_menu_link'))) {
      return;
    }
    $tree_data = $this->menuTreeStorage->loadTreeData(GroupContentMenuInterface::MENU_PREFIX . $gcm->id(), new MenuTreeParameters());
    foreach ($tree_data['tree'] as $element) {
      $this->addUpdateRemove($element['definition'], $gcm);
    }
  }

  /**
   * Add / update / remove a shadow link as appropriate.
   *
   * @param array $definition
   *   A menu link plugin definition.
   * @param \Drupal\group_content_menu\GroupContentMenuInterface $gcm
   *   The group content menu.
   *
   * @return void
   */
  protected function addUpdateRemove(array $definition, GroupContentMenuInterface $gcm): void {
    $shadow_link_id = static::getShadowlinkId($definition['id']);
    $already_exists = $this->menuLinkManager->hasDefinition($shadow_link_id);
    if ($gcm->parent_menu_name->isEmpty()) {
      if ($already_exists) {
        $this->menuLinkManager->removeDefinition($shadow_link_id);
      }
    }
    else {
      $shadow_link_definition = static::getShadowLinkDefinition($definition, $gcm);
      if ($already_exists) {
        $this->menuLinkManager->updateDefinition($shadow_link_id, $shadow_link_definition);
      }
      else {
        $this->menuLinkManager->addDefinition($shadow_link_id, $shadow_link_definition);
      }
    }
  }

  public static function getShadowlinkId(string $id): string {
    return 'gcm:' . $id;
  }

  public static function isShadowLinkId(string $id): bool {
    return str_starts_with($id, 'gcm:');
  }

  public static function getOriginalId(string $id): string {
    return preg_replace('/^gcm:/', '', $id);
  }

  /**
   * @param array $definition
   * @param array $new_definition
   * @param \Drupal\group_content_menu\GroupContentMenuInterface $gcm
   *
   * @return array
   */
  public static function getShadowLinkDefinition(array $definition, GroupContentMenuInterface $gcm): array {
    return [
      'class' => ProxyMenulink::class,
      // ProxyMenulink::__construct will use to get the real plugin.
      'metadata' => $definition['id'],
      // This is extremely important, otherwise the next menu rebuild would
      // drop our links, see MenuTreeStorage::findNoLongerExistingLinks()
      'discovered' => 0,
      'menu_name' => $gcm->parent_menu_name->target_id,
      // This is the most important part of the module: the parent of this
      // shadow link is either the shadow link of the original parent or if it
      // the original parent is root then the parent menu link set up in the
      // group content menu entity.
      'parent' => $definition['parent'] ?
        static::getShadowlinkId($definition['parent']) :
        $gcm->parent_menu_link->value
    // Everything else is copied over, just in case. ProxyMenuLink doesn't use
    // it but something else might.
    ] + $definition;
  }

}
