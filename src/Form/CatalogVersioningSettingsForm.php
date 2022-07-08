<?php

namespace Drupal\apigee_api_catalog_versioning\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Apigee API Catalog Versioning settings for this site.
 */
class CatalogVersioningSettingsForm extends ConfigFormBase {

  const SETTINGS = 'apigee_api_catalog_versioning.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'apigee_api_catalog_versioning_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [static::SETTINGS];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config(static::SETTINGS);

    $form['max_items'] = [
      '#type' => 'number',
      '#min' => 0,
      '#step' => 1,
      '#title' => $this->t('Maximum number of versions to display in dropdown'),
      '#description' => $this->t('Only this number of versions will be displayed in the dropdown version selector. Other versions are still directly accessible by URL.'),
      '#default_value' => $settings->get('max_items') ?: NULL,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::SETTINGS)
      ->set('max_items', $form_state->getValue('max_items'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
