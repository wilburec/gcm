<?php

namespace Drupal\menu_link_reference\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;

/**
 * Plugin implementation of the 'menu_link_reference' widget.
 *
 * @FieldWidget(
 *   id = "menu_link_reference",
 *   label = @Translation("Menu link reference"),
 *   field_types = {
 *     "menu_link_reference"
 *   }
 * )
 */
class MenuLinkReference extends WidgetBase {

  protected const NONE = '_none';

  /**
   * {@hinheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $default_menu_name = $items[$delta]->menu_name ?? NULL;
    $element['menu_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu name'),
      '#default_value' => $default_menu_name ?: self::NONE,
      '#required' => $element['#required'],
      '#empty_value' => self::NONE,
      '#empty_option' => '- Please select -',
      '#options' => $this->getMenuNames(),
      '#ajax' => [
        'callback' => [static::class, 'ajax'],
      ],
    ];
    $element['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu link'),
      '#required' => $element['#required'],
      '#options' => [],
      '#process' => [[static::class, 'idProcess']],
    ];
    if ($items[$delta]->id) {
      $element['id']['#default_value'] = $element['menu_name']['#default_value'] . ':' . $items[$delta]->id;
    }

    return $element;
  }

  /**
   * {@hinheritdoc}
   */
  public static function idProcess($element, FormStateInterface $form_state, &$complete_form) {
    $array_parents = $element['#array_parents'];
    array_splice($array_parents, -1, 1, ['menu_name']);
    $menu_name_element = NestedArray::getValue($complete_form, $array_parents);
    $menu_name = $menu_name_element['#value'];
    if ($menu_name !== $menu_name_element['#empty_value']) {
      $menu_parent_form_selector = \Drupal::service('menu.parent_form_selector');
      assert($menu_parent_form_selector instanceof MenuParentFormSelectorInterface);
      $menus = [
        $menu_name => $menu_name_element['#options'][$menu_name],
      ];
      $element['#options'] = $menu_parent_form_selector->getParentSelectOptions('', $menus);
    }
    else {
      $element['#attributes']['class'][] = 'visually-hidden';
    }
    unset($element['#needs_validation']);
    return $element;
  }

  /**
   * {@hinheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach (array_keys($values) as $delta) {
      if ($values[$delta]['menu_name'] === self::NONE) {
        $values[$delta]['name'] = NULL;
        $values[$delta]['id'] = NULL;
      }
      else {
        // Remove the menu name from the id.
        $values[$delta]['id'] = preg_replace('/^[^:]+:/', '', $values[$delta]['id']);
      }
    }

    return $values;
  }

  /**
   * {@hinheritdoc}
   */
  public static function ajax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_splice($array_parents, -1, 1, ['id']);
    $link_element = NestedArray::getValue($form, $array_parents);
    // The CSS selector is for the <select> element and without removing
    // the form_element wrapper, an additional <div> and <label> would get
    // added which is already in the HTML.
    $link_element['#theme_wrappers'] = [];
    return (new AjaxResponse())
      ->addCommand(new ReplaceCommand(static::getCssSelector($link_element), $link_element));
  }

  /**
   * Get menu names.
   *
   * @return
   *   The menu names.
   */
  protected function getMenuNames(): array {
    $menus = \Drupal::entityTypeManager()
      ->getStorage('menu')
      ->loadMultiple(NULL);
    return array_map(static fn (EntityInterface $e) => $e->label(), $menus);
  }

  /**
   * @param array $element
   *   A form element.
   *
   * @return string
   *   A CSS selector to find $element in the DOM.
   */
  protected static function getCssSelector(array $element): string {
    // Search FormBuilder for "Provide a selector usable by JavaScript" for the
    // origin of this code.
    return sprintf('[data-drupal-selector="%s"]', Html::getId('edit-' . implode('-', $element['#parents'])));
  }

}
