<?php

namespace Drupal\apigee_api_catalog_versioning\Plugin\Field\FieldType;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\TypedData\EntityDataDefinition;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\DataReferenceDefinition;
use Drupal\Core\TypedData\DataReferenceTargetDefinition;
use Drupal\file\Plugin\Field\FieldType\FileItem;

/**
 * Field type "spec versioning".
 *
 * @FieldType(
 *   id = "spec_versioning",
 *   label = @Translation("Spec Versioning"),
 *   description = @Translation("Provide versioning for a Spec File."),
 *   category = @Translation("Custom"),
 *   default_widget = "spec_versioning_generic",
 *   default_formatter = "spec_versioning_default",
 *   constraints = {"ReferenceAccess" = {}, "FileValidation" = {}},
 *   list_class = "\Drupal\file\Plugin\Field\FieldType\FileFieldItemList"
 * )
 */
class SpecVersioning extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = [
      'uri_scheme' => \Drupal::config('system.file')->get('default_scheme'),
      'target_type' => 'file',
    ] + parent::defaultStorageSettings();

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'file_extensions' => 'yml yaml json',
      'file_directory' => '[date:custom:Y]-[date:custom:m]',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'target_id' => [
          'description' => 'The ID of the file entity.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'version' => [
          'description' => 'The version of the file.',
          'type' => 'text',
        ],
      ],
      'indexes' => [
        'target_id' => ['target_id'],
      ],
      'foreign keys' => [
        'target_id' => [
          'table' => 'file_managed',
          'columns' => ['target_id' => 'fid'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['target_id'] = DataReferenceTargetDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('File ID'))
      ->setSetting('unsigned', TRUE);

    $properties['entity'] = DataReferenceDefinition::create('entity')
      ->setLabel('File')
      ->setDescription(new TranslatableMarkup('The referenced entity'))
      // The entity object is computed out of the entity ID.
      ->setComputed(TRUE)
      ->setReadOnly(FALSE)
      ->setTargetDefinition(EntityDataDefinition::create('file'))
      ->addConstraint('EntityType', 'file');

    $properties['version'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Version'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $element = [];

    $element['#attached']['library'][] = 'file/drupal.file';

    $scheme_options = \Drupal::service('stream_wrapper_manager')->getNames(StreamWrapperInterface::WRITE_VISIBLE);
    $element['uri_scheme'] = [
      '#type' => 'radios',
      '#title' => $this->t('Upload destination'),
      '#options' => $scheme_options,
      '#default_value' => $this->getSetting('uri_scheme'),
      '#description' => $this->t('Select where the final files should be stored. Private file storage has significantly more overhead than public files, but allows restricted access to files within this field.'),
      '#disabled' => $has_data,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $settings = $this->getSettings();

    $element['file_directory'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File directory'),
      '#default_value' => $settings['file_directory'],
      '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
      '#element_validate' => [[static::class, 'validateDirectory']],
      '#weight' => 3,
    ];

    // Make the extension list a little more human-friendly by comma-separation.
    $extensions = str_replace(' ', ', ', $settings['file_extensions']);
    $element['file_extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed file extensions'),
      '#default_value' => $extensions,
      '#description' => $this->t("Separate extensions with a comma or space. Each extension can contain alphanumeric characters, '.', and '_', and should start and end with an alphanumeric character."),
      '#element_validate' => [[static::class, 'validateExtensions']],
      '#weight' => 1,
      '#maxlength' => 256,
      // By making this field required, we prevent a potential security issue
      // that would allow files of any type to be uploaded.
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateDirectory($element, FormStateInterface $form_state) {
    FileItem::validateDirectory($element, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function validateExtensions($element, FormStateInterface $form_state) {
    FileItem::validateExtensions($element, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getUploadLocation($data = []) {
    return static::doGetUploadLocation($this->getSettings(), $data);
  }

  /**
   * {@inheritdoc}
   */
  protected static function doGetUploadLocation(array $settings, $data = []) {
    $destination = trim($settings['file_directory'], '/');

    // Replace tokens. As the tokens might contain HTML we convert it to plain
    // text.
    $destination = PlainTextOutput::renderFromHtml(\Drupal::token()->replace($destination, $data));
    return $settings['uri_scheme'] . '://' . $destination;
  }

  /**
   * {@inheritdoc}
   */
  public function getUploadValidators() {
    $validators = [];
    $settings = $this->getSettings();

    // Add the extension check if necessary.
    if (!empty($settings['file_extensions'])) {
      $validators['file_validate_extensions'] = [$settings['file_extensions']];
    }

    return $validators;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
    $fileRepository = \Drupal::service('file.repository');
    $random = new Random();
    $settings = $field_definition->getSettings();

    // Prepare destination.
    $dirname = static::doGetUploadLocation($settings);
    $fileSystem->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY);

    // Generate a file entity.
    $destination = $dirname . '/' . $random->name(10, TRUE) . '.yml';
    $data = $random->paragraphs(3);
    $file = $fileRepository->writeData($data, $destination, FileSystemInterface::EXISTS_ERROR);
    $values = [
      'target_id' => $file->id(),
      'version' => random_int(1, 10) . '.' . random_int(1, 10),
    ];

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function isDisplayed() {
    return TRUE;
  }

}
