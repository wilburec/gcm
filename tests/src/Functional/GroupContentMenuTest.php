<?php

namespace Drupal\Tests\group_content_menu\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group group_content_menu
 */
class GroupContentMenuTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'group',
    'group_content_menu',
    'menu_ui',
  ];

  /**
   * Test callback.
   */
  public function testGroupMenuType() {
    $admin_user = $this->drupalCreateUser([
      'access administration pages',
      'administer group content menu types',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/structure/group_content_menu_types');
    $this->assertSession()->linkExists('Add group menu type');
  }

}
