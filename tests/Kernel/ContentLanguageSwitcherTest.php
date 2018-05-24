<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_theme\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\DomCrawler\Crawler;
use Drupal\node\Entity\Node;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Test content language switcher rendering.
 */
class ContentLanguageSwitcherTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'administration_language_negotiation',
    'content_translation',
    'locale',
    'language',
    'oe_multilingual',
    'oe_multilingual_demo',
    'system',
    'user',
    'node',
  ];

  /**
   * The condition plugin manager.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  protected $pluginManager;
  /**
   * The request stack used for testing.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installSchema('locale', [
      'locales_location',
      'locales_source',
      'locales_target',
    ]);

    $this->installConfig([
      'locale',
      'language',
      'content_translation',
      'administration_language_negotiation',
      'oe_multilingual',
    ]);

    $this->container->get('theme_installer')->install(['oe_theme']);
    $this->container->get('theme_handler')->setDefault('oe_theme');
    $this->container->set('theme.registry', NULL);

    $this->container->get('module_handler')->loadInclude('oe_multilingual', 'install');
    oe_multilingual_install();
  }

  /**
   * Test language switcher rendering.
   */
  public function testLanguageSwitcherRendering(): void {
    $node = Node::create([
      'title' => 'Hello, world!',
      'type' => 'oe_demo_translatable_page',
    ]);
    $node->save();
    $translation = $node->addTranslation('es', ['title' => '¡Hola mundo!']);
    $translation->save();

    // Simulate a request to a node canonical route with a language prefix.
    $request = Request::create('/bg/node/1');
    // Let the Drupal router populate all the request parameters.
    $parameters = \Drupal::service('router.no_access_checks')->matchRequest($request);
    $request->attributes->add($parameters);
    // Set the prepared request as current.
    \Drupal::requestStack()->push($request);
    // Reset any discovered language. KernelTestBase creates a request to the
    // root of the website for legacy purposes, so the language is set by
    // default to the default one.
    // @see \Drupal\KernelTests\KernelTestBase::bootKernel()
    \Drupal::languageManager()->reset();

    // Setup and render language switcher block.
    $block_manager = \Drupal::service('plugin.manager.block');
    $config = [
      'id' => 'oe_multilingual_content_language_switcher',
      'label' => 'Content language switcher',
      'provider' => 'oe_multilingual',
      'label_display' => '0',
    ];

    /** @var \Drupal\Core\Block\BlockBase $plugin_block */
    $plugin_block = $block_manager->createInstance('oe_multilingual_content_language_switcher', $config);
    $render = $plugin_block->build();

    $html = (string) $this->container->get('renderer')->renderRoot($render);
    $crawler = new Crawler($html);
  }

  /**
   * Adds a request to the request stack.
   *
   * @param string $route_name
   *   The request route name.
   * @param string $path
   *   The request route path.
   */
  protected function addRequest($route_name, $path) {
    $request = Request::create($path);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route($path));
    $this->requestStack->push($request);
  }

}
