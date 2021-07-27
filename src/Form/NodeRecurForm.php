<?php

namespace Drupal\node_recur\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to copy a node based on a repeating date.
 */
class NodeRecurForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_recur_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL) {
    // Display this node's date
    $start = node_recur_get_node_date_field_value($node);
    $end = node_recur_get_node_date_field_value($node, FALSE);

    $form['node_date'] = array(
      '#type' => 'item',
      '#title' => $this->t('Date'),
      '#markup' => node_recur_format_date($start, $end),
    );

    $form = array_merge($form, $this->_buildForm($node->getType()));

    // Remove the "no repeat" option
    unset($form['option']['#options']['none']);

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
    );

    $form['#node'] = $node;

    return $form;
  }

  /**
   * Helper function to provide the basics of the form
   *
   * This helps make it available for other modules to use when we might
   * not have a full node yet
   *
   * @param $type
   *   The node type
   */
  public function _buildForm(string $type = NULL) {
    $form = array();

    $form['option'] = array(
      '#type' => 'radios',
      '#options' => array(
        'none' => $this->t('Do not repeat'),
        'days' => $this->t('Pick days of the week'),
        'rules' => $this->t('Every day, every 2 weeks, etc...'),
      ),
      '#default_value' => 'days',
      '#description' => $this->t('Selecting a choice above will reveal more options.'),
    );

    $form['days'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Days of the week'),
      '#options' => array(
        'monday' => $this->t('Monday'),
        'tuesday' => $this->t('Tuesday'),
        'wednesday' => $this->t('Wednesday'),
        'thursday' => $this->t('Thursday'),
        'friday' => $this->t('Friday'),
        'saturday' => $this->t('Saturday'),
        'sunday' => $this->t('Sunday'),
      ),
    );

    $form['rules'] = array(
      '#type' => 'container',
    );

    $options = array();
    for ($i = 1; $i < 11; $i++) {
      $options[$i] = $this->t('Every @i', array('@i' => ($i == 1) ? '' : $i));
    }
    $form['rules']['frequency'] = array(
      '#type' => 'select',
      '#title' => $this->t('Repeat'),
      '#options' => $options,
    );

    $form['rules']['period'] = array(
      '#type' => 'select',
      '#options' => array(
        'day' => $this->t('Day(s)'),
        'week' => $this->t('Week(s)'),
        'month' => $this->t('Month(s)'),
      ),
    );

    $form['rules']['exclude_weekends'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude weekends'),
      '#description' => $this->t('If checked, weekends will not be included.'),
    );

    $form['until'] = array(
      '#type' => 'date',
      '#title' => $this->t('Recur until'),
      '#description' => $this->t('Repeat this class until the specified date.'),
      '#required' => TRUE,
    );
    if ($max = node_recur_max_future_date_span($type)) {
      $form['until']['#description'] .= '&nbsp;' . $this->t('This date can only be up to %max in the future.', array('%max' => $max));
    }

    $form['#attached']['library'][] = 'node_recur/node_recur';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    return _node_recur_form_validate_form($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    return _node_recur_form_submit_form($form, $form_state);
  }
}
