<?php

namespace Drupal\group_content_menu;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuParentFormSelectorInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\group\Entity\GroupContent;
use Drupal\group\Entity\GroupContentInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\node\NodeInterface;
use Drupal\system\MenuInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Helper class to handle altering node forms.
 *
 * @package Drupal\GroupContentMenu
 */
class NodeFormAlter implements ContainerInjectionInterface {

  /**
   * The `entity_type.manager` service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The `menu.parent_form_selector` service.
   *
   * @var \Drupal\Core\Menu\MenuParentFormSelectorInterface
   */
  protected $menuParentSelector;

  /**
   * The `current_user` service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The `context.repository` service.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * Construct our class and inject dependencies.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The `entity_type.manager` service.
   * @param \Drupal\Core\Menu\MenuParentFormSelectorInterface $menu_parent_form_selector
   *   The `menu.parent_form_selector` service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The `current_user` service.
   * @param \Drupal\Core\Plugin\Context\ContextRepositoryInterface $context_repository
   *   The `context.repository` service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MenuParentFormSelectorInterface $menu_parent_form_selector, AccountProxyInterface $current_user, ContextRepositoryInterface $context_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->menuParentSelector = $menu_parent_form_selector;
    $this->currentUser = $current_user;
    $this->contextRepository = $context_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('menu.parent_form_selector'),
      $container->get('current_user'),
      $container->get('context.repository')
    );
  }

  /**
   * Alter node forms to add GroupContentMenu options where appropriate.
   *
   * @param array $form
   *   A form array as from hook_form_alter().
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A form state object as from hook_form_alter().
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @see group_content_menu_form_node_form_alter()
   */
  public function alter(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $form_state->getFormObject()->getEntity();
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = $this->entityTypeManager
      ->getStorage('node_type')
      ->load($node->bundle());
    $available_menus = $node_type
      ->getThirdPartySetting('menu_ui', 'available_menus', ['main']);
    $menu_ui_menus = array_map(static function (MenuInterface $menu) {
      return $menu->label();
    }, $this->entityTypeManager
      ->getStorage('menu')
      ->loadMultiple($available_menus));

    $groups = $this->getGroups($form_state, $node);
    if (!empty($groups)) {
      $group_menus = $this->getGroupMenus($groups);
      $defaults = menu_ui_get_menu_link_defaults($node);
      if ($defaults['id']) {
        $default = $defaults['menu_name'] . ':' . $defaults['parent'];
      }
      else {
        $defaults = group_content_menu_get_menu_link_default($node, array_keys($group_menus));
        $default = $defaults['menu_name'] . ':' . $defaults['parent'];
      }

      // Are there any traditional menus that are not group menus?
      $traditional_menus = !empty($form['menu']['link']['menu_parent']['#options']);

      $form['menu'] = [
        '#type' => 'details',
        '#title' => t('Menu settings'),
        '#open' => (bool) $defaults['id'],
        '#group' => 'advanced',
        '#attached' => [
          'library' => ['menu_ui/drupal.menu_ui'],
        ],
        '#tree' => TRUE,
        '#weight' => -2,
        '#attributes' => ['class' => ['menu-link-form']],
      ];
      $form['menu']['enabled'] = [
        '#type' => 'checkbox',
        '#title' => t('Provide a menu link'),
        '#default_value' => (int) (bool) $defaults['id'],
      ];
      $form['menu']['link'] = [
        '#type' => 'container',
        '#parents' => ['menu'],
        '#states' => [
          'invisible' => [
            'input[name="menu[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];

      $form['menu']['link']['menu_parent'] = $this->menuParentSelector
        ->parentSelectElement($default, $defaults['id'], array_merge($group_menus, $menu_ui_menus));

      // Populate the element with the link data.
      foreach (['id', 'entity_id'] as $key) {
        $form['menu']['link'][$key] = [
          '#type' => 'value',
          '#value' => $defaults[$key],
        ];
      }

      $form['menu']['link']['title'] = [
        '#type' => 'textfield',
        '#title' => t('Menu link title'),
        '#default_value' => $defaults['title'],
        '#maxlength' => $defaults['title_max_length'],
      ];

      $form['menu']['link']['description'] = [
        '#type' => 'textfield',
        '#title' => t('Description'),
        '#default_value' => $defaults['description'],
        '#description' => t('Shown when hovering over the menu link.'),
        '#maxlength' => $defaults['description_max_length'],
      ];

      $form['menu']['link']['menu_parent']['#title'] = t('Parent item');
      $form['menu']['link']['menu_parent']['#attributes']['class'][] = 'menu-parent-select';

      $form['menu']['link']['weight'] = [
        '#type' => 'number',
        '#title' => t('Weight'),
        '#default_value' => $defaults['weight'],
        '#description' => t('Menu links with lower weights are displayed before links with higher weights.'),
      ];

      foreach (array_keys($form['actions']) as $action) {
        if ($action !== 'preview' && isset($form['actions'][$action]['#type']) && $form['actions'][$action]['#type'] === 'submit') {
          $form['actions'][$action]['#submit'][] = 'menu_ui_form_node_form_submit';
        }
      }

      $form['menu']['#access'] = FALSE;
      if (!empty($form['menu']['link']['menu_parent']['#options'])) {
        // If there are traditional menus and user has admin permission.
        if ($traditional_menus && $this->currentUser->hasPermission('administer menu')) {
          $form['menu']['#access'] = TRUE;
        }
        else {
          $context_id = '@group.group_route_context:group';
          $contexts = $this->contextRepository
            ->getRuntimeContexts([$context_id]);
          $group = $contexts[$context_id]->getContextValue();
          if ($group && $group->hasPermission('manage group_content_menu', $this->currentUser)) {
            $form['menu']['#access'] = TRUE;
          }
        }
      }

      $form['#entity_builders'][] = 'menu_ui_node_builder';
    }
  }

  /**
   * Get a node's groups for the edit form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object from hook_form_node_form_alter().
   * @param \Drupal\node\NodeInterface $node
   *   The node object being edited.
   *
   * @return array|\Drupal\group\Entity\GroupInterface[]
   *   An empty array or an array of Groups.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getGroups(FormStateInterface $form_state, NodeInterface $node): array {
    $groups = [];

    if ($group_id = $form_state->get('group')) {
      // If group is set in $form_state, we're currently creating a new node in
      // a group so the node will have only one group.
      $groups[] = $group_id;
    }
    elseif (!$node->isNew()) {
      // We're on a node's edit form. A node can be added to any number of
      // groups so we must load all groups for the node.
      $group_contents = $this->entityTypeManager
        ->getStorage('group_content')
        ->loadByEntity($node);
      $group_ids = array_map(static function (GroupContent $group_content) {
        return $group_content->getGroup()->id();
      }, $group_contents);
      $groups = $this->entityTypeManager
        ->getStorage('group')
        ->loadMultiple($group_ids);
    }

    return $groups;
  }

  /**
   * Get an array of GroupContentMenus.
   *
   * @param \Drupal\group\Entity\GroupInterface[] $groups
   *   An array of groups to get menus for.
   *
   * @return array
   *   An array of GroupContentMenu labels, keyed by menu link id..
   */
  protected function getGroupMenus(array $groups) {
    $group_menus = [];
    foreach ($groups as $group) {
      $group_menus[] = array_map(static function (GroupContentInterface $group_content) {
        $id = GroupContentMenuInterface::MENU_PREFIX . $group_content->getEntity()->id();
        return [$id => $group_content->getEntity()->label() . " ({$group_content->getGroup()->label()})"];
      }, group_content_menu_get_menus_per_group($group));
    }
    // Unpack the group menus.
    $group_menus = array_merge(...$group_menus);
    // We have multiple levels of nested arrays, depending on if any groups
    // have menus or not.
    if ($group_menus) {
      $group_menus = array_merge(...$group_menus);
    }

    return array_unique($group_menus);
  }

}
