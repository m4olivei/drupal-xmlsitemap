<?php

/**
 * @file
 * Contains \Drupal\xmlsitemap\Controller\XmlSitemapController.
 */

namespace Drupal\xmlsitemap\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Returns responses for xmlsitemap.sitemap_xml and xmlsitemap.sitemap_xsl
 * routes.
 */
class XmlSitemapController extends ControllerBase {

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The twig loader object.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * The entity manager object.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a new XmlSitemapController object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state, TwigEnvironment $twig, EntityManagerInterface $entity_manager) {
    $this->state = $state;
    $this->twig = $twig;
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('state'), $container->get('twig'), $container->get('entity.manager')
    );
  }

  /**
   * Provides the sitemap in XML format.
   *
   * @throws NotFoundHttpException
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *  The sitemap in XML format or plain text if xmlsitemap_developer_mode flag
   *  is set.
   */
  public function renderSitemapXml() {
    $sitemap = $this->entityManager->getStorage('xmlsitemap')->loadByContext();
    if (!$sitemap) {
      throw new NotFoundHttpException();
    }
    $chunk = xmlsitemap_get_current_chunk($sitemap);
    $file = xmlsitemap_sitemap_get_file($sitemap, $chunk);

    // Provide debugging information if enabled.
    if ($this->state->get('xmlsitemap_developer_mode')) {
      $module_path = drupal_get_path('module', 'xmlsitemap');
      $template = $this->twig->loadTemplate($module_path . '/templates/sitemap-developer-mode.html.twig');
      $elements = array(
        'current_context' => print_r($context, TRUE),
        'sitemap' => print_r($sitemap, TRUE),
        'chunk' => $chunk,
        'cache_file_location' => $file,
        'cache_file_exists' => file_exists($file) ? 'Yes' : 'No'
      );
      return new Response($template->render($elements));
    }
    $response = new Response();
    return xmlsitemap_output_file($response, $file);
  }

  /**
   * Provides the sitemap in XSL format.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *  Response object in XSL format.
   */
  public function renderSitemapXsl() {
    // Read the XSL content from the file.
    $module_path = drupal_get_path('module', 'xmlsitemap');
    $xsl_content = file_get_contents($module_path . '/xsl/xmlsitemap.xsl');

    // Make sure the strings in the XSL content are translated properly.
    $replacements = array(
      'Sitemap file' => t('Sitemap file'),
      'Generated by the <a href="http://drupal.org/project/xmlsitemap">Drupal XML sitemap module</a>.' => t('Generated by the <a href="@link-xmlsitemap">Drupal XML sitemap module</a>.', array('@link-xmlsitemap' => 'http://drupal.org/project/xmlsitemap')),
      'Number of sitemaps in this index' => t('Number of sitemaps in this index'),
      'Click on the table headers to change sorting.' => t('Click on the table headers to change sorting.'),
      'Sitemap URL' => t('Sitemap URL'),
      'Last modification date' => t('Last modification date'),
      'Number of URLs in this sitemap' => t('Number of URLs in this sitemap'),
      'URL location' => t('URL location'),
      'Change frequency' => t('Change frequency'),
      'Priority' => t('Priority'),
      '[jquery]' => base_path() . 'misc/jquery.js',
      '[jquery-tablesort]' => base_path() . $module_path . '/xsl/jquery.tablesorter.min.js',
      '[xsl-js]' => base_path() . $module_path . '/xsl/xmlsitemap.xsl.js',
      '[xsl-css]' => base_path() . $module_path . '/xsl/xmlsitemap.xsl.css',
    );
    $xsl_content = strtr($xsl_content, $replacements);

    // Output the XSL content.
    $response = new Response($xsl_content);
    $response->headers->set('Content-type', 'application/xml; charset=utf-8');
    $response->headers->set('X-Robots-Tag', 'noindex, follow');
    return $response;
  }

}
