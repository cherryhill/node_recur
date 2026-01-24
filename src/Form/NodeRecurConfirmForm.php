<?php

namespace Drupal\node_recur\Form;

use Drupal;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form to copy a node based on a repeating date.
 */
class NodeRecurConfirmForm extends ConfirmFormBase {

  /**
   * The working node.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $node;

  /**
   * The private tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * NodeRecurConfirmForm constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private tempstore factory.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'node_recur_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('node_recur.recur', ['node' => $this->node->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Submit');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    $type = strtolower($this->node->getType());
    return '<strong>' . $this->t('This action cannot be undone. Please confirm that the dates above are accurate and that the %type information is correct. Editing this %type afterwards will not edit every %type generated here.', ['%type' => $type]) . '</strong>';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to generate these items?');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $node = NULL, $dates = NULL) {
    $this->node = $node;
    $tempstore = $this->tempStoreFactory->get('node_recur');
    $dates = $tempstore->get('dates');

    // Make sure we have dates to work with
    if (empty($dates['start'])) {
      $this->messenger()->addWarning($this->t('No dates were generated with the information you supplied.'));
      return new RedirectResponse($this->getCancelUrl()->setAbsolute()->toString());
    }

    // Store the dates and node
    $form['#start_dates'] = $dates['start'];
    $form['#end_dates'] = $dates['end'];
    $form['#node'] = $node;

    // Display the dates to the user
    $form['message'] = array(
      '#markup' => $this->t('The following dates will be generated. Please review them before continuing.'),
    );
    $form['dates']['#markup'] = '<ul>';
    foreach ($dates['start'] as $key => $start_date) {
      $end_date = $dates['end'][$key] ?? NULL;
      $form['dates']['#markup'] .= '<li>' . node_recur_format_date($start_date, $end_date) . '</li>';
    }
    $form['dates']['#markup'] .= '</ul>';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $node = $this->node;
    // Log this action
    $this->logger('node_recur')->notice('Recurring the %type "%title" %count times.',
      array(
        '%type' => $node->getType(),
        '%title' => $node->getTitle(),
        '%count' => count($form['#start_dates'])
      )
    );
    // Start the batch
    \Drupal::moduleHandler()->loadInclude('node_recur', 'inc', 'node_recur.batch');
    node_recur_node_batch_start($node, $form['#start_dates'], $form['#end_dates']);
  }
}
