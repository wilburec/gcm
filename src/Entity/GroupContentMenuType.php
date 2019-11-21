<?php

namespace Drupal\group_content_menu\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Group content menu type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "group_content_menu_type",
 *   label = @Translation("Group content menu type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\group_content_menu\Form\GroupContentMenuTypeForm",
 *       "edit" = "Drupal\group_content_menu\Form\GroupContentMenuTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\group_content_menu\GroupContentMenuTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer group content menu types",
 *   bundle_of = "group_content_menu",
 *   config_prefix = "group_content_menu_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/group_content_menu_types/add",
 *     "edit-form" = "/admin/structure/group_content_menu_types/manage/{group_content_menu_type}",
 *     "delete-form" = "/admin/structure/group_content_menu_types/manage/{group_content_menu_type}/delete",
 *     "collection" = "/admin/structure/group_content_menu_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class GroupContentMenuType extends ConfigEntityBundleBase {

  /**
   * The machine name of this group content menu type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the group content menu type.
   *
   * @var string
   */
  protected $label;

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage);
    \Drupal::service('plugin.manager.group_content_enabler')->clearCachedDefinitions();
    \Drupal::service('router.builder')->rebuild();
  }

}
