<?php

namespace Drupal\Tests\group_content_menu\Functional;

use Drupal\Core\Url;
use Drupal\group\Entity\GroupType;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Test description.
 *
 * @group group_content_menu
 */
class GroupContentMenuTest extends GroupBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'group_content_menu',
    'gnode',
    'menu_ui',
    'node',
  ];

  /**
   * @var string
   */
  protected $menuId;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->menuId = strtolower($this->randomMachineName());

    // Add group permissions.
    $role = GroupType::load('default')->getMemberRole();
    $role->grantPermissions([
      'access group content menu overview',
      'manage group_content_menu',
      'manage group_content_menu menu items'
    ]);
    $role->save();

    // Create a basic page content type with a default menu.
    $type = $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);
    $type->setThirdPartySetting('menu_ui', 'available_menus', ['main']);
    $type->save();
    // Create an article content type, without any default menu.
    $type = $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
      'display_submitted' => FALSE,
    ]);
    $type->setThirdPartySetting('menu_ui', 'available_menus', []);
    $type->save();
  }

  /**
   * Test creation of a group content menu with group nodes.
   */
  public function testNodeGroupContentMenu(): void {
    // To see the menu option on the node edit form, add menu access.
    $group_creator = $this->drupalCreateUser(array_merge($this->getGlobalPermissions(), [
      'administer menu',
    ]));
    $this->drupalLogin($group_creator);

    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Create a group.
    $this->drupalGet('/group/add/default');
    $group_name = $this->randomString();
    $page->fillField('label[0][value]', $group_name);
    $page->pressButton('Create Default label and complete your membership');
    $page->pressButton('Save group and membership');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains(sprintf('Default label %s has been created.', $group_name));

    // Visit the group menu page.
    $this->drupalGet('/group/1/menus');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('There are no group content menu entities yet.');

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $menu_label = $this->randomString();
    $page->fillField('label', $menu_label);
    $page->fillField('id', $this->menuId);
    $page->pressButton('Save group menu type');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains(sprintf('The group menu type %s has been added.', $menu_label));

    // Enable the gnode content plugin for basic page.
    $this->drupalGet('/admin/group/content/install/default/group_node:page');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type.');

    // Enable the gnode content plugin for article.
    $this->drupalGet('/admin/group/content/install/default/group_node:article');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type.');

    // Enable the group content plugin.
    $this->drupalGet(sprintf('/admin/group/content/install/default/group_content_menu:%s', $this->menuId));
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type.');

    // Verify the menu settings render even when no group menu has been created.
    $this->drupalGet('/group/1/content/create/group_node:page');
    $assert->pageTextContains('Menu settings');
    $assert->pageTextContains('Parent link');
    $page->fillField('title[0][value]', 'Group node');
    $page->pressButton('Save');
    $this->drupalGet('/node/1/edit');
    $assert->statusCodeEquals(200);

    // Verify the menu settings do not display if no menus are available.
    $this->drupalGet('/group/1/content/create/group_node:article');
    $assert->pageTextNotContains('Menu settings');

    // Create new group content menu.
    $this->drupalGet('/group/1/menu/add');
    $new_menu_label = $this->randomString();
    $page->fillField('label[0][value]', $new_menu_label);
    $page->pressButton('Save');

    // Only one group content menu instance is created.
    $this->drupalGet('/group/1/content');
    $assert->pageTextContainsOnce($new_menu_label);

    // Verify menu settings render when a group menu has been created.
    $this->drupalGet('/group/1/content/create/group_node:page');
    $assert->pageTextContains('Menu settings');
    $assert->pageTextContains('Parent link');
    $page->checkField('menu[enabled]');
    $assert->optionExists('menu[menu_parent]', "<$new_menu_label ($group_name)>");
    $assert->optionExists('menu[menu_parent]', '<Main navigation>');
    $page->fillField('title[0][value]', 'Group node');
    $page->pressButton('Save');
    $this->drupalGet('/node/2/edit');
    $assert->statusCodeEquals(200);

    // Verify the menu settings display, even if no default menu selected.
    $this->drupalGet('/group/1/content/create/group_node:article');
    $assert->pageTextContains('Menu settings');
    $assert->pageTextContains('Parent link');
    $assert->optionNotExists('menu[menu_parent]', 'Main navigation');
  }

  /**
   * Test creation of a group content menu.
   */
  public function testCreateGroupContentMenu(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $menu_label = $this->randomString();
    $page->fillField('label', $menu_label);
    $page->fillField('id', $this->menuId);
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains(sprintf('The group menu type %s has been added.', $menu_label));

    // Place a group content menu block.
    $default_theme = $this->config('system.theme')->get('default');
    $options = [
      'query' => [
        'region' => 'sidebar_first',
        'weight' => 0,
      ],
    ];
    $this->drupalGet(Url::fromRoute('block.admin_library', ['theme' => $default_theme], $options));
    $block_name = sprintf('group_content_menu:%s', $this->menuId);
    $add_url = Url::fromRoute('block.admin_add', [
      'plugin_id' => $block_name,
      'theme' => $default_theme,
    ]);
    $links = $this->xpath('//a[contains(@href, :href)]', [':href' => $add_url->toString()]);
    $this->assertCount(1, $links, 'Found one matching link.');
    $links[0]->click();
    $assert->statusCodeEquals(200);
    $page->fillField('settings[context_mapping][group]', '@group.group_route_context:group');
    $page->pressButton('Save block');
    $assert->statusCodeEquals(200);

    // Enable the group content plugin.
    $this->drupalGet(sprintf('/admin/group/content/install/default/group_content_menu:%s', $this->menuId));
    $page->checkField('auto_create_group_menu');
    $page->checkField('auto_create_home_link');
    $page->fillField('auto_create_home_link_title', 'Group home page');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type. ');

    // Add a group and group content menu.
    $this->drupalGet('/group/add/default');
    $group_title = $this->randomString();
    $page->fillField('label[0][value]', $group_title);
    $page->pressButton('Create Default label and complete your membership');
    $page->pressButton('Save group and membership');
    $assert->linkExists('Group home page');

    // Home link is editable.
    $this->drupalGet('/group/1/menu/1/link/1');
    $assert->statusCodeEquals(200);
    $page->pressButton('Save');
    $assert->pageTextContains('The menu link has been saved.');
    $assert->addressEquals('/group/1/menu/1/edit');

    // Add menu links to the newly created menu and render the menu.
    $this->drupalGet('/group/1/menu/1/edit');
    $assert->statusCodeEquals(200);
    $this->drupalGet('/group/1/menu/1/add-link');
    $assert->statusCodeEquals(200);
    // Add a link.
    $link_title = $this->randomString();
    $page->fillField('title[0][value]', $link_title);
    $page->fillField('link[0][uri]', '<front>');
    $page->pressButton('Save');
    // Edit the link
    $this->drupalGet('/group/1/menu/1/link/2');
    $page->selectFieldOption('menu_parent', '-- Group home page');
    $page->pressButton('Save');
    $assert->pageTextContains('The menu link has been saved. ');
    $assert->linkExists($link_title);
    $assert->statusCodeEquals(200);

    // Delete the link
    $this->drupalGet('/group/1/menu/1/link/2/delete');
    $page->pressButton('Delete');
    $assert->pageTextContains("The menu link $link_title has been deleted.");
    $assert->addressEquals('/group/1/menu/1/edit');

    // Delete menu.
    $this->drupalGet('/group/1/menu/1/delete');
    $page->pressButton('Delete');
    $assert->pageTextContains(sprintf('The group content menu %s has been deleted.', $menu_label));

    // Re-add menu.
    $this->drupalGet(sprintf('/group/1/content/create/group_content_menu:%s', $this->menuId));
    $menu_title = $this->randomString();
    $page->fillField('label[0][value]', $menu_title);
    $page->pressButton('Save');
    $assert->pageTextContains("New group menu $menu_title has been created.");
  }

  /**
   * Test adding the group content menu item manually.
   */
  public function testAddMenuManually(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $menu_label = $this->randomString();
    $page->fillField('label', $menu_label);
    $page->fillField('id', $this->menuId);
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains(sprintf('The group menu type %s has been added.', $menu_label));

    // Enable the group content plugin.
    $this->drupalGet(sprintf('/admin/group/content/install/default/group_content_menu:%s', $this->menuId));
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type. ');

    // Add a group.
    $this->drupalGet('/group/add/default');
    $group_title = $this->randomString();
    $page->fillField('label[0][value]', $group_title);
    $page->pressButton('Create Default label and complete your membership');
    $page->pressButton('Save group and membership');

    // Create new group content menu.
    $this->drupalGet('/group/1/menu/add');
    $menu_label = $this->randomString();
    $page->fillField('label[0][value]', $menu_label);
    $page->pressButton('Save');

    // Only one group content menu instance is created.
    $this->drupalGet('/group/1/content');
    $assert->pageTextContainsOnce($menu_label);
  }

  /**
   * Test creation of a group content menu with multiple menu types available.
   */
  public function testMultipleMenus(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Add group permissions.
    $role = GroupType::load('default')->getMemberRole();
    $role->grantPermissions([
      'manage group_content_menu menu items',
    ]);
    $role->save();

    // Generate Group Menu One content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types/add');
    $page->fillField('label', 'Group Menu One');
    $page->fillField('id', 'group_menu_one');
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('The group menu type Group Menu One has been added.');

    // Generate Group Menu Two content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types/add');
    $page->fillField('label', 'Group Menu Two');
    $page->fillField('id', 'group_menu_two');
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('The group menu type Group Menu Two has been added.');

    // Enable the group content plugins for the default group type.
    $this->drupalGet('/admin/group/content/install/default/group_content_menu:group_menu_one');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type. ');
    $this->drupalGet('/admin/group/content/install/default/group_content_menu:group_menu_two');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type.');

    // Add a group.
    $this->drupalGet('/group/add/default');
    $group_title = $this->randomString();
    $page->fillField('label[0][value]', $group_title);
    $page->pressButton('Create Default label and complete your membership');
    $page->pressButton('Save group and membership');
    $assert->statusCodeEquals(200);

    // Create a group content menu.
    $this->drupalGet('group/1/menu/add');
    $page->clickLink('Group menu (Group Menu Two)');
    $assert->statusCodeEquals(200);
    $menu_title = $this->randomString();
    $page->fillField('label[0][value]', $menu_title);
    $page->pressButton('Save');
    $assert->pageTextContains("New group menu $menu_title has been created.");
  }

  /**
   * Test Expand All Menu Items option.
   */
  public function testExpandAllItems(): void {
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $menu_label = $this->randomString();
    $page->fillField('label', $menu_label);
    $page->fillField('id', $this->menuId);
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains(sprintf('The group menu type %s has been added.', $menu_label));

    // Place group content menu block.
    $default_theme = $this->config('system.theme')->get('default');
    $group_menu_block = $this->drupalPlaceBlock(sprintf('group_content_menu:%s', $this->menuId), [
      'id' => $default_theme . '_groupmenu',
      'context_mapping' => [
        'group' => '@group.group_route_context:group',
      ],
    ]);
    // Get the block ID so we can reference it later for edits.
    $group_menu_block_id = $group_menu_block->id();

    // Enable the group content plugin.
    $this->drupalGet(sprintf('/admin/group/content/install/default/group_content_menu:%s', $this->menuId));
    $page->checkField('auto_create_group_menu');
    $page->checkField('auto_create_home_link');
    $page->fillField('auto_create_home_link_title', 'Group home page');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type. ');

    // Add a group and group content menu.
    $this->drupalGet('/group/add/default');
    $group_title = $this->randomString();
    $page->fillField('label[0][value]', $group_title);
    $page->pressButton('Create Default label and complete your membership');
    $page->pressButton('Save group and membership');
    $assert->linkExists('Group home page');

    // Add a parent link.
    $this->drupalGet('/group/1/menu/1/add-link');
    $assert->statusCodeEquals(200);
    $link_top_level = $this->randomString(8);
    $page->fillField('title[0][value]', $link_top_level);
    $page->fillField('link[0][uri]', 'http://example.com');
    $page->pressButton('Save');

    // Add a Child link.
    $this->drupalGet('/group/1/menu/1/add-link');
    $assert->statusCodeEquals(200);
    $link_sub_level = $this->randomString(8);
    $page->fillField('title[0][value]', $link_sub_level);
    $page->fillField('link[0][uri]', 'http://example1.com');
    $page->selectFieldOption('menu_parent', '-- ' . $link_top_level);
    $page->pressButton('Save');

    $this->drupalGet('/group/1');
    $assert->linkNotExists($link_sub_level);

    // Set Block to expand all items
    $this->drupalGet('admin/structure/block/manage/' . $group_menu_block_id);
    $assert->checkboxNotChecked('settings[expand_all_items]');
    $this->submitForm([
      'settings[level]' => 1,
      'settings[depth]' => 0,
      'settings[expand_all_items]' => 1,
    ], 'Save block');

    // Check if we can now see all items.
    $this->drupalGet('/group/1');
    $assert->linkExists($link_top_level);
    $assert->linkExists($link_sub_level);
  }

  /**
   * Test the group permissions of this module.
   */
  public function testGroupPermissions(): void {
    // Enable group content menu plugin.
    $group_content_menu_type = $this->entityTypeManager->getStorage('group_content_menu_type')->create([
      'id' => $this->menuId,
      'label' => $this->randomString(),
    ]);
    $group_content_menu_type->save();
    $plugin_id = 'group_content_menu:' . $group_content_menu_type->id();
    $group_type = GroupType::load('default');
    $this->container->get('plugin.manager.group_content_enabler')->clearCachedDefinitions();
    $group_content_menu = $this->entityTypeManager->getStorage('group_content_type')->createFromPlugin(
      $group_type,
      $plugin_id,
      [
        'auto_create_group_menu' => TRUE,
        'auto_create_home_link' => TRUE,
      ]
    );
    $group_content_menu->save();
    $group = $this->createGroup();

    // Enable node group content type plugin.
    $plugin_id = 'group_node:article';
    $group_type = $group->getGroupType();
    $this->container->get('plugin.manager.group_content_enabler')->clearCachedDefinitions();
    $this->entityTypeManager->getStorage('group_content_type')->createFromPlugin(
      $group_type,
      $plugin_id,
      []
    )->save();
    $node_permission_provider = $this->container->get('plugin.manager.group_content_enabler')->getPermissionProvider($plugin_id);

    // Grant permissions and create group admin, menu admin, member and
    // outsider, and anonymous roles.
    // Admin role.
    $admin_role = $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'admin',
      'label' => 'Admin',
      'weight' => 0,
      'group_type' => $group_type->id(),
    ]);
    $admin_role->changePermissions([
      'access group content menu overview' => TRUE,
      'manage group_content_menu' => TRUE,
      'manage group_content_menu menu items' => FALSE,
      'view group' => TRUE,
      $node_permission_provider->getPermission('create', 'entity', 'any') => TRUE,
      $node_permission_provider->getPermission('create', 'relation', 'any') => TRUE,
    ])->save();
    // Menu admin.
    $menu_admin_role = $this->entityTypeManager->getStorage('group_role')->create([
      'id' => 'menu_admin',
      'label' => 'Menu admin',
      'weight' => 0,
      'group_type' => $group_type->id(),
    ]);
    $menu_admin_role->changePermissions([
      'access group content menu overview' => FALSE,
      'manage group_content_menu' => FALSE,
      'manage group_content_menu menu items' => TRUE,
      'view group' => TRUE,
      $node_permission_provider->getPermission('create', 'entity', 'any') => TRUE,
      $node_permission_provider->getPermission('create', 'relation', 'any') => TRUE,
    ])->save();
    // Member role.
    $role = $group_type->getMemberRole();
    $role->changePermissions([
      'access group content menu overview' => FALSE,
      'manage group_content_menu' => FALSE,
      'manage group_content_menu menu items' => FALSE,
      'view group' => TRUE,
    ])->save();
    // Outsider role.
    $role = $group_type->getOutsiderRole();
    $role->grantPermissions([
      'view group',
    ])->save();
    // Anonymous role.
    $role = $group_type->getAnonymousRole();
    $role->grantPermissions([
      'view group',
    ])->save();

    // Create various user types.
    $group_admin = $this->drupalCreateUser([
      'access content',
    ]);
    $menu_admin = $this->drupalCreateUser([
      'access content',
    ]);
    $member = $this->drupalCreateUser([
      'access content',
    ]);
    $outsider = $this->drupalCreateUser([
      'access content',
    ]);
    $anonymous = User::load(0);

    // Assign various users membership types.
    $group->addMember($group_admin, [
      'group_roles' => [$admin_role->id()],
    ]);
    $group->addMember($menu_admin, [
      'group_roles' => [$menu_admin_role->id()],
    ]);
    $group->addMember($member, [
      'group_roles' => [$group_type->getMemberRoleId()],
    ]);

    $this->drupalLogin($group_admin);
    $this->assertMenuManagePermissions(200);
    $this->assertMenuItemCrudPermissions(403);
    $this->drupalLogin($menu_admin);
    $this->assertMenuManagePermissions(403);
    $this->assertMenuItemCrudPermissions(200);
    $this->assertMenuPermissions($member, 403);
    $this->assertMenuPermissions($outsider, 403);
    $this->assertMenuPermissions($anonymous, 403);
  }

  /**
   * Assert menu permissions.
   */
  private function assertMenuPermissions(UserInterface $user, int $status_code): void {
    $assert = $this->assertSession();
    if ($user->isAuthenticated()) {
      $this->drupalLogin($user);
    }
    $this->drupalGet(Url::fromRoute('entity.group.canonical', [
      'group' => 1,
    ]));
    $assert->statusCodeEquals(200);
    $this->assertMenuManagePermissions($status_code);
    $this->assertMenuManagePermissions($status_code);
  }

  /**
   * Assert menu manage permissions.
   */
  private function assertMenuManagePermissions(int $status_code): void {
    $assert = $this->assertSession();

    $this->drupalGet(Url::fromRoute('entity.group_content_menu.collection', [
      'group' => 1,
    ]));
    $assert->statusCodeEquals($status_code);
    $this->drupalGet(Url::fromRoute('entity.group_content_menu.add_page', [
      'group' => 1,
    ]));
    $assert->statusCodeEquals($status_code);
    $this->drupalGet(Url::fromRoute('entity.group_content_menu.delete_form', [
      'group' => 1,
      'group_content_menu' => 1,
    ]));
    $assert->statusCodeEquals($status_code);
  }

  /**
   * Assert menu item CRUD permissions.
   */
  private function assertMenuItemCrudPermissions(int $status_code): void {
    $assert = $this->assertSession();

    $this->drupalGet(Url::fromRoute('entity.group_content_menu.edit_form', [
      'group' => 1,
      'group_content_menu' => 1,
    ]));
    $assert->statusCodeEquals($status_code);
    $this->drupalGet(Url::fromRoute('entity.group_content_menu.add_menu_link', [
      'group' => 1,
      'group_content_menu' => 1,
    ]));
    $assert->statusCodeEquals($status_code);
    $this->drupalGet(Url::fromRoute('entity.group_content_menu.edit_menu_link', [
      'group' => 1,
      'group_content_menu' => 1,
      'menu_link_content' => 1,
    ]));
    $assert->statusCodeEquals($status_code);
    $this->drupalGet(Url::fromRoute('entity.group_content_menu.delete_menu_link', [
      'group' => 1,
      'group_content_menu' => 1,
      'menu_link_content' => 1,
    ]));
    $assert->statusCodeEquals($status_code);

    $this->drupalGet('/group/1/content/create/group_node:article');
    if ($status_code === 200) {
      $assert->pageTextContains('Menu settings');
      $assert->pageTextContains('Parent link');
      $assert->fieldExists('menu[enabled]');
    }
    else {
      $assert->pageTextNotContains('Menu settings');
      $assert->fieldNotExists('menu[enabled]');
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getGlobalPermissions(): array {
    return [
      'administer blocks',
      'administer group content menu types',
      'administer group',
      'bypass group access',
    ] + parent::getGlobalPermissions();
  }

}
