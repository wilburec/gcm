<?php

namespace Drupal\group_content_submenu;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\system\Entity\Menu;
use Symfony\Component\DependencyInjection\ContainerInterface;

class GroupContentSubmenuAlter implements ContainerInjectionInterface {

  const PARENT_MENU_NAME_FORM_PARENTS = 'group_content_submenu_parent_menu_name_form_parents';

  const PARENT_MENU_LINK_ARRAY_PARENTS = 'group_content_submenu_parent_menu_link_array_parents';

  public function __construct(protected MenuParentFormSelectorInterface $menuParentFormSelector) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('menu.parent_form_selector')
    );
  }

  public function widget(array &$element, FormStateInterface $form_state, array $context) {
    /** @var \Drupal\Core\Field\FieldItemListInterface $items */
    $items = $context['items'];
    $gcm = $items->getEntity();
    if ($gcm instanceof GroupContentMenuInterface && $gcm->hasField('parent_menu_link') && $gcm->hasField('parent_menu_name')) {
      $prefix  = sprintf('gcs-%s-%d-',
        $gcm->getFieldDefinition('parent_menu_link')->getUniqueIdentifier(),
        $context['delta']
      );
      $parent_menu_link_wrapper_id = $prefix . 'link';
      $parent_menu_name_wrapper_id = $prefix . 'name';
      switch ($items->getFieldDefinition()->getName()) {
        case 'parent_menu_name':
          $element['#prefix'] = "<div id='$parent_menu_name_wrapper_id'>";
          $element['#suffix'] = '</div>';
          $element['#after_build'][] = [static::class, 'afterBuildName'];
          $element['#ajax'] = [
            'callback' => [static::class, 'ajax'],
            'parent_menu_link_wrapper_id' => $parent_menu_link_wrapper_id,
          ];
          break;
        case 'parent_menu_link':
          $options = $this->getParentSelectOptions($gcm, $form_state);
          $element['value']['#type'] = 'select';
          $element['value']['#options'] = $options;
          $element['value']['#size'] = min(count($options), 50);
          $element['value']['#prefix'] = "<div id='$parent_menu_link_wrapper_id'>";
          $element['value']['#suffix'] = '</div>';
          $element['value']['#states']['invisible'][] = ["#$parent_menu_name_wrapper_id select" => ['value' => '_none']];
          $element['value']['#after_build'][] = [static::class, 'afterBuildLink'];
          break;
      }
    }
  }

  /**
   * Store the path in the parent menu name in $form_state->getValues()
   *
   * @param array $element
   *   The parent name widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The parent name widget, unchanged.
   */
  public static function afterBuildName(array $element, FormStateInterface $form_state): array {
    $parents = $element['#parents'];
    $parents[] = 0;
    $parents[] = 'target_id';
    $form_state->set(self::PARENT_MENU_NAME_FORM_PARENTS, $parents);
    return $element;
  }

  /**
   * Store the path to the parent menu link widget in form state.
   *
   * @param array $element
   *   The parent menu link widget.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The parent menu link widget, unchanged/
   */
  public static function afterBuildLink(array $element, FormStateInterface $form_state): array {
    $form_state->set(self::PARENT_MENU_LINK_ARRAY_PARENTS, $element['#array_parents']);
    return $element;
  }

  /**
   * Get the parent select options based on the appropxiate menu name.
   *
   * @param \Drupal\group_content_menu\GroupContentMenuInterface $gcm
   *   The menu being edited.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form options if there are any relevant or the empty array.
   */
  public function getParentSelectOptions(GroupContentMenuInterface $gcm, FormStateInterface $form_state): array {
    $menu_name = $gcm->parent_menu_name->target_id;
    // This is set in ::afterBuildName so this will only be true in form
    // rebuildd which is exactly when we need it. The form parents do not
    // change from one form build to the next (hopefully).
    if ($menu_name_form_parents = $form_state->get(self::PARENT_MENU_NAME_FORM_PARENTS)) {
      $menu_name = $form_state->getValue($menu_name_form_parents);
    }
    if ($menu_name && $menu_name !== '_none' && ($menu = Menu::load($menu_name))) {
      return $this->menuParentFormSelector->getParentSelectOptions('', [$menu_name => $menu->label()]);
    }
    return [];
  }

  public static function ajax(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    // The right element to replace was set in ::afterBuildLink.
    $parent_menu_link_widget = NestedArray::getValue($form, $form_state->get(self::PARENT_MENU_LINK_ARRAY_PARENTS));
    $parent_menu_link_wrapper_id = $triggering_element['#ajax']['parent_menu_link_wrapper_id'];
    return (new AjaxResponse())
      ->addCommand(new ReplaceCommand('#' . $parent_menu_link_wrapper_id, $parent_menu_link_widget));
  }

}
