<?php

namespace Drupal\group_content_submenu;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\field\Entity\FieldConfig;

class GroupContentSubmenuInsert {

  public static function fieldConfig(FieldConfigInterface $field_config) {
    // We are only concerned about group_content_menu.
    if ($field_config->getTargetEntityTypeId() !== 'group_content_menu') {
      return;
    }
    // Only run when the field being inserted is ours and the other field
    // already exists. This ensures the code is executed exactly once.
    $bundle = $field_config->getTargetBundle();
    $other_field_name = match ($field_config->getName()) {
      'parent_menu_name' => 'parent_menu_link',
      'parent_menu_link' => 'parent_menu_name',
      default => '',
    };
    if (!$other_field_name || !FieldConfig::loadByName('group_content_menu', $bundle, $other_field_name)) {
      return;
    }
    // Collecting and creating displays is based on
    // EntityViewDisplay::collectRenderDisplays().
    $results = \Drupal::entityQuery('entity_view_display')
      ->condition('id', "group_content.menu.$bundle.", 'STARTS_WITH')
      ->condition('status', TRUE)
      ->execute();
    if (!$displays = EntityFormDisplay::loadMultiple($results)) {
      $displays[] = EntityFormDisplay::create([
        'targetEntityType' => 'group_content_menu',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    foreach ($displays as $display) {
      // This sets up correct order and correct type.
      $display
        ->setComponent('parent_menu_name', [
          'type' => 'options_select',
        ])
        ->setComponent('parent_menu_link')
        ->save();
    }
  }

}
