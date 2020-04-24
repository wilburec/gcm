<?php

namespace Drupal\Tests\group_content_menu\Functional;

use Drupal\Core\Url;
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
    $title = $this->randomString();
    $page->fillField('label[0][value]', $title);
    $page->pressButton('Create Default label and complete your membership');
    $page->pressButton('Save group and membership');
    $assert->linkExists('Group home page');
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
