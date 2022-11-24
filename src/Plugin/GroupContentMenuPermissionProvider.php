<?php

namespace Drupal\group_content_menu\Plugin;

use Drupal\group\Plugin\GroupContentPermissionProvider;

/**
 * Provide group permissions for group_content_menu entities.
 */
class GroupContentMenuPermissionProvider extends GroupContentPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public function getPermission($operation, $target, $scope = 'any') {
    // Group Content Menus don't operate like normal group content so these
    // permissions don't apply. Instead we use a high-level "Manage menus" perm.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions() {
    return [];
  }

}
