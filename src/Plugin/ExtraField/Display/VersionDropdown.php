<?php

namespace Drupal\apigee_api_catalog_versioning\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Version selector for custom field.
 *
 * @ExtraFieldDisplay(
 *   id = "version_selector",
 *   label = @Translation("Version Selector"),
 *   description = @Translation("Version selector for API Doc."),
 *   bundles = {
 *     "node.apidoc"
 *   },
 *   visible = false
 * )
 */
class VersionDropdown extends ExtraFieldDisplayBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  protected const OAS_FIELD_NAME = 'field_oas_file_specification';

  protected const MAX_VERSIONS_TO_DISPLAY = 5;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $build = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dropdown'],
      ],
    ];

    $build['button'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Select version'),
      '#attributes' => [
        'class' => 'btn btn-secondary dropdown-toggle',
        'type' => 'button',
        'id' => 'dropdownVersionMenu',
        'data-toggle' => 'dropdown',
        'aria-haspopup' => 'true',
        'aria-expanded' => 'false',
      ],
    ];

    $build['menu'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['dropdown-menu'],
        'aria-labelledby' => ['dropdownVersionMenu'],
      ],
    ];

    $versions = $this->getVersions($entity);
    $requested = $this->getRequestedVersion($versions);

    if (empty($versions)) {
      return [];
    }

    $count = 0;
    foreach ($versions as $index => $version) {
      if ($count++ < static::MAX_VERSIONS_TO_DISPLAY) {
        $build['menu']['link_' . $index] = [
          '#type' => 'link',
          '#title' => $version,
          '#url' => $entity->toUrl('canonical', [
            'query' => [
              'version' => $version,
            ],
          ]),
          '#options' => [
            'attributes' => [
              'class' => ['dropdown-item'],
            ],
          ],
        ];
      }

      if ($version == $requested) {
        $build['button']['#value'] = $version;
      }
    }

    $build['#cache']['tags'] = $entity->getCacheTags();
    $build['#cache']['contexts'][] = 'url.query_args:version';

    return $build;
  }

  /**
   * Get the field's available versions as an array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The node entity.
   *
   * @return array
   *   The versions as an array.
   */
  protected function getVersions(ContentEntityInterface $entity):array {
    $value = $entity->get(static::OAS_FIELD_NAME)->getValue();

    $versions = [];
    foreach ($value as $item) {
      $versions[] = $item['version'];
    }
    arsort($versions);

    return $versions;
  }

  /**
   * Get a valid requested version, or the first item of the versions if null.
   *
   * @param array $versions
   *   The versions array.
   *
   * @return string
   *   A valid requested version.
   */
  protected function getRequestedVersion(array $versions):string {
    $requestedVersion = $this->requestStack->getCurrentRequest()->query->get('version');
    $requestedVersion = urldecode($requestedVersion);
    return (!empty($requestedVersion) && in_array($requestedVersion, $versions)) ?
      $requestedVersion :
      reset($versions);
  }

}
