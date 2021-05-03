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
    $permissions = [];

    $permissions['access group content menu overview'] = $this->buildPermission(
      'Access group content menu overview',
      'Access the overview of all menus'
    );
    $permissions['manage group_content_menu'] = $this->buildPermission(
      'Manage menus',
      'Create, update and delete menus'
    );

    return $permissions;
  }

}
