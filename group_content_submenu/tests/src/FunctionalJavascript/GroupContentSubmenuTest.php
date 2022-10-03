<?php

namespace Drupal\Tests\group_content_submenu\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\group_content_menu\Entity\GroupContentMenu;
use Drupal\group_content_menu\Entity\GroupContentMenuType;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Test description.
 *
 * @group group_content_menu
 */
class GroupContentSubmenuTest extends WebDriverTestBase  {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'group',
    'group_content_menu',
    'group_content_submenu',
    'menu_link_content',
    'menu_ui',
    'options'
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected $gcmType;

  protected $coreMenuName;

  protected $coreMenuLinkTitle;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser(['access administration pages', 'administer menu']));
    $this->gcmType = $this->randomMachineName();
    $plugin_id = "group_content_menu:$this->gcmType";
    // Create a group content menu type.
    GroupContentMenuType::create([
      'id' => $this->gcmType,
    ])->save();
    // Add our fields to this group content menu type.
    foreach (['parent_menu_name', 'parent_menu_link'] as $field_name) {
      FieldConfig::create([
        'entity_type' => 'group_content_menu',
        'bundle' => $this->gcmType,
        'field_name' => $field_name,
      ])->save();
    }
    // Create a group type.
    $group_type = GroupType::create([
      'id' => $this->randomMachineName(),
    ]);
    $group_type->save();
    // Install the plugin.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $gct_storage */
    $gct_storage = \Drupal::entityTypeManager()->getStorage('group_content_type');
    $gct_storage
      ->createFromPlugin($group_type, $plugin_id, ['auto_create_group_menu' => TRUE])
      ->save();
    // Add group permission.
    $group_type
      ->getMemberRole()
      ->grantPermissions(['manage group_content_menu', 'manage group_content_menu menu items', "create $plugin_id content"])
      ->save();
    // Create a group. This will create a group content menu as well.
    Group::create(['type' => $group_type->id()])->save();

    // Create a core menu.
    $this->coreMenuName = $this->randomMachineName();
    Menu::create(['id' => $this->coreMenuName])->save();
    // Add a link to the core menu.
    $this->coreMenuLinkTitle = $this->randomString();
    MenuLinkContent::create([
      'link' => ['uri' => 'internal:/admin'],
      'title' => $this->coreMenuLinkTitle,
      'menu_name' => $this->coreMenuName,
    ])->save();
  }

  public function testGroupContentSubmenu() {
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $links = MenuLinkContent::loadMultiple(NULL);
    $core_link = reset($links);

    // Load and edit the automatically created group content menu.
    $gcms = GroupContentMenu::loadMultiple(NULL);
    $this->assertNotEmpty($gcms);
    /** @var GroupContentMenu $gcm */
    $gcm = reset($gcms);
    $group_menu_link_title = $this->randomString();
    $this->drupalGet($gcm->toUrl('add-menu-link'));//'/group/1/menu/1/add-link'
    $page->fillField('title[0][value]', $group_menu_link_title);
    $page->fillField('link[0][uri]', '/user');
    $page->pressButton('Save');

    $this->drupalGet($gcm->toUrl('edit-form'));
    $page->fillField('label[0][value]', $this->randomString());
    // @todo: assert textfield here vs select.
    $assert->optionNotExists('parent_menu_link', $this->coreMenuLinkTitle);
    $page->selectFieldOption('parent_menu_name', $this->coreMenuName);
    $assert->assertWaitOnAjaxRequest();
    $page->selectFieldOption('parent_menu_link', $this->coreMenuLinkTitle);
    $page->pressButton('Save');
    file_put_contents('/tmp/log.html', $page->getOuterHtml());
    $this->drupalGet($core_link->toUrl('edit-form'));
    $links = MenuLinkContent::loadMultiple(NULL);
    unset($links[$core_link->id()]);
    $group_link = array_shift($links);
    $this->assertEmpty($links);;
    $value = implode(':', [
      // This comes from the parent selector element.
      $core_link->getMenuName(),
      // These two form the plugin ID of the "shadow" link.
      'gcm',
      $group_link->getPluginId(),
    ]);
    $option = $page->find('xpath', sprintf('//select[@name="menu_parent"]/option[@value="%s"]', $value));
    // '--' means it's a child.
    $this->assertSame('-- ' . $group_link->getTitle(), $option->getText());
  }
}
