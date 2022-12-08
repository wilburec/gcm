<?php

namespace Drupal\Tests\group_content_menu\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\group\Entity\GroupType;
use Drupal\group\PermissionScopeInterface;
use Drupal\group_content_menu\Entity\GroupContentMenu;
use Drupal\group_content_menu\Entity\GroupContentMenuType;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;
use Drupal\user\RoleInterface;

/**
 * Test description.
 *
 * @group group_content_menu
 */
class GroupContentSubmenuTest extends WebDriverTestBase {

  use GroupTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'group',
    'group_content_menu',
    'menu_link_content',
    'menu_ui',
    'menu_link_reference',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->groupSetUp();
    $this->drupalLogin($this->groupCreator);

    $group_content_menu_type = $this->randomMachineName();
    $plugin_id = "group_content_menu:$group_content_menu_type";

    // Create a group content menu type.
    GroupContentMenuType::create([
      'id' => $group_content_menu_type,
    ])->save();
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'group_content_menu',
      'bundle' => $group_content_menu_type,
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $component = $display->getComponent('parent');
    $component['region'] = 'content';
    $display->setComponent('parent', $component);
    $display->save();
    // Create a group type.
    $group_type = GroupType::create([
      'id' => $this->randomMachineName(),
    ]);
    $group_type->save();
    // Install the plugin.
    /** @var \Drupal\group\Entity\Storage\GroupContentTypeStorageInterface $gct_storage */
    $gct_storage = $this->container->get('entity_type.manager')->getStorage('group_relationship_type');
    $gct_storage
      ->createFromPlugin($group_type, $plugin_id, ['auto_create_group_menu' => TRUE])
      ->save();
    // Add group permission.
    $this->createGroupRole([
      'group_type' => $group_type->id(),
      'scope' => PermissionScopeInterface::INSIDER_ID,
      'global_role' => RoleInterface::AUTHENTICATED_ID,
      'permissions' => [
        'manage group_content_menu',
        'manage group_content_menu menu items',
        "create $plugin_id content",
      ],
    ]);

    // Create a group. This will create a group content menu as well.
    $this->createGroup([
      'type' => $group_type->id(),
      'uid' => $this->groupCreator->id(),
    ]);

    // Create a core menu.
    $menu_name = $this->randomMachineName();
    Menu::create([
      'id' => $menu_name,
      'label' => $this->randomString(),
    ])->save();
    // Add a link to the core menu.
    MenuLinkContent::create([
      'link' => ['uri' => 'internal:/admin'],
      'title' => $this->randomString(),
      'menu_name' => $menu_name,
    ])->save();
  }

  /**
   *
   */
  public function testGroupContentSubmenu() {
    $this->drupalGet('/user');
    $this->drupalGet($this->groupCreator->toUrl()->toString());
    /** @var \Drupal\Tests\WebAssert $assert */
    $assert = $this->assertSession();
    $page = $this->getSession()->getPage();
    $links = MenuLinkContent::loadMultiple(NULL);
    $core_link = reset($links);

    // Load and edit the automatically created group content menu.
    $group_content_menus = GroupContentMenu::loadMultiple(NULL);
    $this->assertNotEmpty($group_content_menus);
    /** @var \Drupal\group_content_menu\Entity\GroupContentMenu $group_content_menu */
    $group_content_menu = reset($group_content_menus);
    $group_menu_link_title = $this->randomString();
    // '/group/1/menu/1/add-link'
    $this->drupalGet($group_content_menu->toUrl('add-menu-link'));
    $page->fillField('title[0][value]', $group_menu_link_title);
    $page->fillField('link[0][uri]', '/user');
    $page->pressButton('Save');

    $this->drupalGet($group_content_menu->toUrl('edit-form'));
    $page->fillField('label[0][value]', $this->randomString());
    $value = implode(':', [
      // This comes from the parent selector.
      $core_link->getMenuName(),
      // These two come from the MenuLinkContentDeriver.
      'menu_link_content',
      $core_link->uuid(),
    ]);
    $assert->optionNotExists('parent[0][id]', $value);
    $page->selectFieldOption('parent[0][menu_name]', $core_link->getMenuName());
    $assert->assertWaitOnAjaxRequest();
    $page->selectFieldOption('parent[0][id]', $value);
    $page->pressButton('Save');
    foreach (Menu::loadMultiple(NULL) as $menu) {
      if ($menu->id() === $core_link->getMenuName()) {
        $this->drupalGet($menu->toUrl('edit-form'));
        break;
      }
    }
    $links = MenuLinkContent::loadMultiple(NULL);
    unset($links[$core_link->id()]);
    $group_link = array_shift($links);
    $this->assertEmpty($links);

    $cell = $page->find('xpath', '//table/tbody/tr[2]/td[1]');
    $this->assertSame(1, count($cell->findAll('css', 'div.indentation')));
    $this->assertTrue($cell->hasLink($group_link->getTitle()));
  }

  /**
   * {@inheritdoc}
   */
  protected function getGlobalPermissions(): array {
    return [
      'view the administration theme',
      'access administration pages',
      'access group overview',
      'administer menu',
    ];
  }

}
