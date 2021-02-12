<?php

namespace Drupal\Tests\group_content_menu\Functional;

use Drupal\Core\Url;
use Drupal\group\Entity\GroupType;
use Drupal\Tests\group\Functional\GroupBrowserTestBase;

/**
 * Test description.
 *
 * @group group_content_menu
 */
class GroupContentMenuTest extends GroupBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'group_content_menu',
    'gnode',
    'menu_ui',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add group permissions.
    $role = GroupType::load('default')->getMemberRole();
    $role->grantPermissions([
      'access group content menu overview',
      'create group_content_menu:group_menu content',
      'manage group_content_menu',
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
  public function testNodeGroupContentMenu() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    // Create a group.
    $this->drupalGet('/group/add/default');
    $page->fillField('label[0][value]', 'Group page');
    $page->pressButton('Create Default label and complete your membership');
    $page->pressButton('Save group and membership');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Default label Group page has been created.');

    // Visit the group menu page.
    $this->drupalGet('/group/1/menus');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('There are no group content menu entities yet.');

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $page->fillField('label', 'Group Menu');
    $page->fillField('id', 'group_menu');
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('The group menu type Group Menu has been added.');

    // Enable the gnode content plugin for basic page.
    $this->drupalGet('/admin/group/content/install/default/group_node:page');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type.');

    // Enable the gnode content plugin for article.
    $this->drupalGet('/admin/group/content/install/default/group_node:article');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type.');

    // Enable the group content plugin.
    $this->drupalGet('/admin/group/content/install/default/group_content_menu:group_menu');
    $page->pressButton('Install plugin');
    $assert->pageTextContains('The content plugin was installed on the group type.');

    // Verify the menu settings render even when no group menu has been created.
    $this->drupalGet('/group/1/content/create/group_node:page');
    $assert->pageTextContains('Menu settings');
    $assert->pageTextContains('Parent item');
    $page->fillField('title[0][value]', 'Group node');
    $page->pressButton('Save');
    $this->drupalGet('/node/1/edit');
    $assert->statusCodeEquals(200);

    // Verify the menu settings do not display if no menus are available.
    $this->drupalGet('/group/1/content/create/group_node:article');
    $assert->pageTextNotContains('Menu settings');

    // Create new group content menu.
    $this->drupalGet('/group/1/menu/add');
    $menu_label = $this->randomString();
    $page->fillField('label[0][value]', $menu_label);
    $page->pressButton('Save');

    // Only one group content menu instance is created.
    $this->drupalGet('/group/1/content');
    $assert->pageTextContainsOnce($menu_label);

    // Verify menu settings render when a group menu has been created.
    $this->drupalGet('/group/1/content/create/group_node:page');
    $assert->pageTextContains('Menu settings');
    $assert->pageTextContains('Parent item');
    $assert->optionExists('menu[menu_parent]', $menu_label);
    $page->fillField('title[0][value]', 'Group node');
    $page->pressButton('Save');
    $this->drupalGet('/node/2/edit');
    $assert->statusCodeEquals(200);

    // Verify the menu settings display, even if no default menu selected.
    $this->drupalGet('/group/1/content/create/group_node:article');
    $assert->pageTextContains('Menu settings');
    $assert->pageTextContains('Parent item');
  }

  /**
   * Test creation of a group content menu.
   */
  public function testCreateGroupContentMenu() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $page->fillField('label', 'Group Menu');
    $page->fillField('id', 'group_menu');
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('The group menu type Group Menu has been added.');

    // Place a group content menu block.
    $default_theme = $this->config('system.theme')->get('default');
    $options = [
      'query' => [
        'region' => 'sidebar_first',
        'weight' => 0,
      ],
    ];
    $this->drupalGet(Url::fromRoute('block.admin_library', ['theme' => $default_theme], $options));
    $block_name = 'group_content_menu:group_menu';
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
    $this->drupalGet('/admin/group/content/install/default/group_content_menu:group_menu');
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
    $this->drupalGet('/group/1');
    $assert->linkExists($link_title);
    $this->drupalGet('/group/1/menu/1/edit');
    $assert->statusCodeEquals(200);

    // Delete menu.
    $this->drupalGet('/group/1/menu/1/delete');
    $page->pressButton('Delete');
    $assert->pageTextContains('The group content menu Group Menu has been deleted.');

    // Re-add menu.
    $this->drupalGet('/group/1/content/create/group_content_menu:group_menu');
    $menu_title = $this->randomString();
    $page->fillField('label[0][value]', $menu_title);
    $page->pressButton('Save');
    $assert->pageTextContains("New group menu $menu_title has been created. ");
  }

  /**
   * Test adding the group content menu item manually.
   */
  public function testAddMenuManually() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $page->fillField('label', 'Group Menu');
    $page->fillField('id', 'group_menu');
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('The group menu type Group Menu has been added.');

    // Enable the group content plugin.
    $this->drupalGet('/admin/group/content/install/default/group_content_menu:group_menu');
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
  public function testMultipleMenus() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

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
  public function testExpandAllItems() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    /** @var \Behat\Mink\Element\DocumentElement $page */
    $page = $this->getSession()->getPage();

    // Generate a group content menu type.
    $this->drupalGet('admin/structure/group_content_menu_types');
    $page->clickLink('Add group menu type');
    $assert->statusCodeEquals(200);
    $page->fillField('label', 'Group Menu');
    $page->fillField('id', 'group_menu');
    $page->pressButton('Save');
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('The group menu type Group Menu has been added.');

    // Place group content menu block.
    $default_theme = $this->config('system.theme')->get('default');
    $group_menu_block = $this->drupalPlaceBlock('group_content_menu:group_menu', [
      'id' => $default_theme . '_groupmenu',
      'context_mapping' => [
        'group' => '@group.group_route_context:group',
      ],
    ]);
    // Get the block ID so we can reference it later for edits.
    $group_menu_block_id = $group_menu_block->id();

    // Enable the group content plugin.
    $this->drupalGet('/admin/group/content/install/default/group_content_menu:group_menu');
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
   * {@inheritdoc}
   */
  protected function getGlobalPermissions() {
    return [
      'administer blocks',
      'administer group content menu types',
      'administer group',
      'administer menu',
      'bypass group access',
    ] + parent::getGlobalPermissions();
  }

}
