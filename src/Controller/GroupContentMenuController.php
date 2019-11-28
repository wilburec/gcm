<?php

namespace Drupal\group_content_menu\Controller;

use Drupal\Core\Entity\EntityFormBuilderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\group\Entity\Controller\GroupContentController;
use Drupal\group\Entity\GroupInterface;
use Drupal\group\Plugin\GroupContentEnablerManagerInterface;
use Drupal\group_content_menu\GroupContentMenuInterface;
use Drupal\user\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for 'group_content_menu' GroupContent routes.
 */
class GroupContentMenuController extends GroupContentController {

  /**
   * The group content plugin manager.
   *
   * @var \Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs a new GroupContentMenuController.
   *
   * @param \Drupal\group\Plugin\GroupContentEnablerManagerInterface $plugin_manager
   *   The group content plugin manager.
   * @param \Drupal\user\PrivateTempStoreFactory $temp_store_factory
   *   The private store factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFormBuilderInterface $entity_form_builder
   *   The entity form builder.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(GroupContentEnablerManagerInterface $plugin_manager, PrivateTempStoreFactory $temp_store_factory, EntityTypeManagerInterface $entity_type_manager, EntityFormBuilderInterface $entity_form_builder, RendererInterface $renderer) {
    parent::__construct($temp_store_factory, $entity_type_manager, $entity_form_builder, $renderer);
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.group_content_enabler'),
      $container->get('user.private_tempstore'),
      $container->get('entity_type.manager'),
      $container->get('entity.form_builder'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function addPage(GroupInterface $group, $create_mode = TRUE) {
    $bundle_names = $this->addPageBundles($group, $create_mode);
    // Filter out the bundles the user doesn't have access to. Duplicated from
    // parent class so as to avoid information disclosure.
    foreach ($bundle_names as $plugin_id => $bundle_name) {
      if ($create_mode) {
        $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
        $access = $plugin->createEntityAccess($group, $this->currentUser());
      }
      else {
        $access_control_handler = $this->entityTypeManager->getAccessControlHandler('group_content');
        $access = $access_control_handler->createAccess($bundle_name, NULL, ['group' => $group], TRUE);
      }

      if (!$access->isAllowed()) {
        unset($bundle_names[$plugin_id]);
      }
    }

    // Disallow creating multiple menus of the same type.
    if (count($bundle_names) === 1) {
      reset($bundle_names);
      $plugin_id = key($bundle_names);
      if ($limitation = $this->handleOneMenuLimitation($group, $plugin_id)) {
        return $limitation;
      }
    }

    $build = parent::addPage($group, $create_mode);

    // Do not interfere with redirects.
    if (!is_array($build)) {
      return $build;
    }

    // Overwrite the label and description for all of the displayed bundles.
    $storage_handler = $this->entityTypeManager->getStorage('group_content_menu_type');
    foreach ($this->addPageBundles($group, $create_mode) as $plugin_id => $bundle_name) {
      if (!empty($build['#bundles'][$bundle_name])) {
        $plugin = $group->getGroupType()->getContentPlugin($plugin_id);
        $label = $plugin->getLabel();
        $bundle_label = $storage_handler->load($plugin->getEntityBundle())->label();
        $description = $this->t('Add new menu of type %bundle_label to the group.', ['%bundle_label' => $bundle_label]);
        $build['#bundles'][$bundle_name]['label'] = $bundle_label;
        $build['#bundles'][$bundle_name]['description'] = $description;
        $build['#bundles'][$bundle_name]['add_link'] = Link::createFromRoute($label, 'entity.group_content_menu.add_form', ['group' => $group->id(), 'plugin_id' => $plugin_id]);
      }
    }

    return $build;
  }

  /**
   * Handle one menu per group limitation.
   *
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   * @param string $plugin_id
   *   The group content plugin ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|bool
   *   The redirect response or FALSE if no need to handle..
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function handleOneMenuLimitation(GroupInterface $group, $plugin_id) {
    $group_contents = \Drupal::entityTypeManager()->getStorage('group_content')->loadByGroup($group, $plugin_id);
    $group_content = reset($group_contents);
    if ($menu_type = $this->entityTypeManager->getStorage('group_content_menu_type')->load($group_content->getEntity()->bundle())) {
      $this->messenger()->addError($this->t('This group already has a menu "%menu" of type "%type". Only one menu per type per group is allowed.', [
        '%menu' => $group_content->getEntity()->label(),
        '%type' => $menu_type->label(),
      ]));
      $route_params = ['group' => $group_content->getGroup()->id()];
      $url = Url::fromRoute('entity.group_content_menu.collection', $route_params, ['absolute' => TRUE]);
      return new RedirectResponse($url->toString());
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function createForm(GroupInterface $group, $plugin_id) {
    if ($limitation = $this->handleOneMenuLimitation($group, $plugin_id)) {
      return $limitation;
    }

    return parent::createForm($group, $plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  protected function addPageBundles(GroupInterface $group, $create_mode) {
    $bundles = [];

    // Retrieve all group_content_menu plugins for the group's type.
    $plugin_ids = $this->pluginManager->getInstalledIds($group->getGroupType());
    foreach ($plugin_ids as $key => $plugin_id) {
      if (strpos($plugin_id, 'group_content_menu:') !== 0) {
        unset($plugin_ids[$key]);
      }
    }

    // Retrieve all of the responsible group content types, keyed by plugin ID.
    $storage = $this->entityTypeManager->getStorage('group_content_type');
    $properties = ['group_type' => $group->bundle(), 'content_plugin' => $plugin_ids];
    foreach ($storage->loadByProperties($properties) as $bundle => $group_content_type) {
      /** @var \Drupal\group\Entity\GroupContentTypeInterface $group_content_type */
      $bundles[$group_content_type->getContentPluginId()] = $bundle;
    }

    return $bundles;
  }

  /**
   * Provides the menu link creation form.
   *
   * @param \Drupal\group_content_menu\GroupContentMenuInterface $group_content_menu
   *   An entity representing a custom menu.
   *
   * @return array
   *   Returns the menu link creation form.
   */
  public function addLink(GroupContentMenuInterface $group_content_menu) {
    $menu_name = GroupContentMenuInterface::MENU_PREFIX . $group_content_menu->id();
    $menu_link = $this->entityTypeManager()->getStorage('menu_link_content')->create([
      'menu_name' => $menu_name,
      'bundle' => '',
    ]);
    return $this->entityFormBuilder()->getForm($menu_link);
  }

}
