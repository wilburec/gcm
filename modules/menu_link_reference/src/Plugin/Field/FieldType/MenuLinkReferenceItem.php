<?php

namespace Drupal\menu_link_reference\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'menu_link_reference' field type.
 *
 * @FieldType(
 *   id = "menu_link_reference",
 *   label = @Translation("Menu link reference"),
 *   category = @Translation("General"),
 *   default_widget = "menu_link_reference",
 *   default_formatter = "string",
 *   no_ui = TRUE
 * )
 */
class MenuLinkReferenceItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('menu_name')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['menu_name'] = DataDefinition::create('string')
      ->setLabel(t('Menu name'))
      ->setRequired(TRUE);
    $properties['id'] = DataDefinition::create('string')
      ->setLabel(t('Link ID'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'menu_name' => [
        'description' => "The menu name. All links with the same menu name (such as 'tools') are part of the same menu.",
        'type' => 'varchar_ascii',
        'length' => 32,
        'not null' => TRUE,
        'default' => '',
      ],
      'id' => [
        'description' => 'Machine name: the plugin ID.',
        'type' => 'varchar_ascii',
        'length' => 255,
        'not null' => TRUE,
      ],
    ];

    return [
      'columns' => $columns,
      'indexes' => [
        'menu_name' => ['menu_name'],
        'id' => ['id'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['menu_name'] = $random->name(mt_rand(2, 32));
    return $values;
  }

}
