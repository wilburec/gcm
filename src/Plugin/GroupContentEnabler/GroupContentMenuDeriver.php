<?php

namespace Drupal\group_content_menu\Plugin\GroupContentEnabler;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\group_content_menu\Entity\GroupContentMenuType;

/**
 * Group menu deriver.
 */
class GroupContentMenuDeriver extends DeriverBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    foreach (GroupContentMenuType::loadMultiple() as $name => $group_menu_type) {
      $label = $group_menu_type->label();

      $this->derivatives[$name] = [
        'entity_bundle' => $name,
        'label' => $this->t('Group menu (@type)', ['@type' => $label]),
        'description' => $this->t('Adds %type menu items to groups.', ['%type' => $label]),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
