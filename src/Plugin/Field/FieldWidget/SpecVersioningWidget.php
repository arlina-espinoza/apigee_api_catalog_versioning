<?php

namespace Drupal\apigee_api_catalog_versioning\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;

/**
 * Plugin implementation of the 'file_generic' widget.
 *
 * @FieldWidget(
 *   id = "spec_versioning_generic",
 *   label = @Translation("Spec Versioning"),
 *   field_types = {
 *     "spec_versioning"
 *   }
 * )
 */
class SpecVersioningWidget extends FileWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['#title'] = $this->t('Specification file');

    $element['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#value' => $items[$delta]->version ?? NULL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function value($element, $input, FormStateInterface $form_state) {
    $return = parent::value($element, $input, $form_state);
    $return['version'] = $input['version'] ?? NULL;

    return $return;
  }

}
