<?php

namespace Drupal\group_content_menu\Plugin\GroupContentEnabler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerBase;
use Drupal\group_content_menu\Entity\GroupContentMenuType;

/**
 * Provides a content enabler for group menus.
 *
 * @GroupContentEnabler(
 *   id = "group_content_menu",
 *   label = @Translation("Group content menu"),
 *   description = @Translation("Adds group menus and menu items to groups."),
 *   entity_type_id = "group_content_menu",
 *   entity_access = TRUE,
 *   reference_label = @Translation("Title"),
 *   reference_description = @Translation("The title of the menu to add to the group"),
 *   deriver = "Drupal\group_content_menu\Plugin\GroupContentEnabler\GroupContentMenuDeriver",
 *   handlers = {
 *     "access" = "Drupal\group\Plugin\GroupContentAccessControlHandler",
 *     "permission_provider" = "Drupal\group_content_menu\Plugin\GroupContentMenuPermissionProvider",
 *   }
 * )
 */
class GroupContentMenu extends GroupContentEnablerBase {

  /**
   * Retrieves the menu type this plugin supports.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The menu type this plugin supports.
   */
  protected function getMenuType() {
    return GroupContentMenuType::load($this->getEntityBundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupOperations(GroupInterface $group) {
    $account = \Drupal::currentUser();
    $operations = [];
    $url = Url::fromRoute('entity.group_content_menu.collection', ['group' => $group->id()]);
    if ($url->access($account)) {
      $operations['group-content-menu-collection'] = [
        'title' => $this->t('Edit group menus'),
        'url' => $url,
        'weight' => 100,
      ];
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $config = parent::defaultConfiguration();
    $config['group_cardinality'] = 1;
    $config['entity_cardinality'] = 1;
    $config['auto_create_group_menu'] = FALSE;
    $config['auto_create_home_link'] = FALSE;
    $config['auto_create_home_link_title'] = 'Home';

    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $configuration = $this->getConfiguration();

    // Disable the entity and group cardinality field as the functionality of
    // this module relies on a cardinality of 1. We don't just hide it, though,
    // to keep a UI that's consistent with other content enabler plugins.
    $info = $this->t("This field has been disabled by the plugin to guarantee the functionality that's expected of it.");
    $form['group_cardinality']['#disabled'] = TRUE;
    $form['group_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';
    $form['entity_cardinality']['#disabled'] = TRUE;
    $form['entity_cardinality']['#description'] .= '<br /><em>' . $info . '</em>';

    $form['auto_create_group_menu'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create a menu when a group is created.'),
      '#description' => $this->t('The menu will be added to the new group as a group menu. The menu will be deleted when group is deleted.'),
      '#default_value' => $configuration['auto_create_group_menu'],
    ];

    $form['auto_create_home_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically create a "Home" link for the menu.'),
      '#description' => $this->t('The "Home" link will link to the canonical URL of the group.'),
      '#default_value' => $configuration['auto_create_home_link'],
      '#states' => [
        'visible' => [
          ':input[name="auto_create_group_menu"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['auto_create_home_link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link title'),
      '#default_value' => $configuration['auto_create_home_link_title'],
      '#required' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="auto_create_home_link"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function getGroupContentPermissions() {
    $plugin_id = $this->getPluginId();

    $permissions["create $plugin_id content"] = [
      'title' => 'Relate menu',
      'description' => 'Allows you to relate a menu to the group.',
    ];
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTargetEntityPermissions() {
    // No special permissions.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $dependencies['config'][] = 'group_content_menu.group_content_menu_type.' . $this->getEntityBundle();
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function postInstall() {
    parent::postInstall();
    // Rebuild route access to pick up new create menu route permissions.
    \Drupal::service("router.builder")->rebuild();
  }

}
