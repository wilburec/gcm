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
    'menu_ui',
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
  }

  /**
   * Test creation of a group content menu.
   */
  public function testGroupContentMenu() {
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

    // Add a menu link to the newly created menu, then edit it.
    $this->drupalGet('/group/1/menu/1/edit');
    $assert->statusCodeEquals(200);
    $this->drupalGet('/group/1/menu/1/add-link');
    $assert->statusCodeEquals(200);
    $link_title = $this->randomString();
    $page->fillField('title[0][value]', $link_title);
    $page->fillField('link[0][uri]', '<front>');
    $page->pressButton('Save');
    $this->drupalGet('/group/1/menu/1/link/2');
    $page->pressButton('Save');
    $assert->pageTextContains('The menu link has been saved. ');
    $this->drupalGet('/group/1');
    $assert->linkExists($link_title);

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
   * {@inheritdoc}
   */
  protected function getGlobalPermissions() {
    return [
      'administer blocks',
      'administer group content menu types',
      'administer group',
      'bypass group access',
    ] + parent::getGlobalPermissions();
  }

}
