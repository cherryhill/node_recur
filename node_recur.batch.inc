<?php

use Drupal\Core\Url;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Start the batch to generate recurring nodes
 */
function node_recur_node_batch_start($node, $start_dates, $end_dates) {
  // Generate the batch operations
  $operations = array();
  if ($start_dates) {
    foreach ($start_dates as $key => $start_date) {
      $operations[] = array('node_recur_node_batch_create_node', array(
        $node,
        $start_date,
        !empty($end_dates) ? $end_dates[$key] : NULL,
      ));
    }
  }

  // Generate the batch
  $batch = array(
    'title' => t('Generating items'),
    'operations' => $operations,
    'finished' => 'node_recur_node_batch_finished',
    'file' => \Drupal::service('extension.list.module')->getPath('node_recur') . '/node_recur.batch.inc',
  );

  // Store working node to redirect back to when finished.
  $tempstore = Drupal::service('tempstore.private')->get('node_recur');
  $tempstore->set('node', $node);

  batch_set($batch);
}

/**
 * Create the recurring nodes
 */
function node_recur_node_batch_create_node($node, $start_date, $end_date, &$context) {
  // Set the progress message
  $context['message'] = t('Date: @date', array(
    '@date' => node_recur_format_date($start_date, $end_date)
  ));

  // Clone the node
  $clone = $node->createDuplicate();

  // Add some helpful markers
  $clone->is_recurring = TRUE;
  $clone->recurring_node = $node;

  // Inject the new dates
  $field_name = node_recur_get_date_field_name($node->getType());
  $field_info = FieldStorageConfig::loadByName('node', $field_name);
  switch ($field_info->getType()) {
    case 'daterange':
      $value = [
        'value' => Drupal::service('date.formatter')->format($start_date, 'custom', 'Y-m-d\TH:i:s'),
        'end_value' => Drupal::service('date.formatter')->format($end_date, 'custom', 'Y-m-d\TH:i:s'),
      ];
      break;
    case 'datetime':
    default:
      $value = Drupal::service('date.formatter')->format($start_date, 'custom', 'Y-m-d\TH:i:s');
      break;
  }

  $clone->set($field_name, $value);

  // Store the start dates
  $context['results'][] = $start_date;

  // Save the new node
  $clone->save();
}

/**
 * Batch ending
 */
function node_recur_node_batch_finished($success, $results, $operations) {
  if ($success) {
    $count = count($results);
    Drupal::messenger()->addMessage(t('%count %items have been generated.',
      array(
        '%count' => $count,
        '%items' => Drupal::translation()->formatPlural($count, 'item', 'items')
      )
    ));
  }
  else {
    Drupal::messenger()->addError(t('An error occurred. Please try again.'));
  }

  // Determine where to redirect to
  $tempstore = Drupal::service('tempstore.private')->get('node_recur');
  $url = URL::fromRoute('entity.node.canonical', ['node' => $tempstore->get('node')->id()]);
  // Allow modules to alter the path
  Drupal::moduleHandler()->alter('node_recur_batch_redirect', $url, $node);
  return new RedirectResponse($url->toString());
}
