<?php
/**
 * @file
 * Contains \Drupal\players_reserve\Form\PlayersAddReserveForm.
 */
namespace Drupal\players_reserve\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;

class PlayersSelectReportForm extends FormBase {

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\players_reserve\Service\PlayersService $playersService
   *  The players service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    MessengerInterface $messenger,
    EntityTypeManagerInterface $entityTypeManager
  ) {

    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'players_select_report_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $date = NULL) {

    $form['report_type_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Report Type'),
    ];

    $form['report_type_wrapper']['report_type'] = [
      '#type' => 'select',
      '#title' => 'Report type',
      '#options' => [
        '' => 'Select',
        'single_game_report' => 'Single game report',
        'multiple_game_report' => 'Multiple game report',
        'players_list' => 'Players list'
      ],
      '#required' => TRUE,
    ];

    $form['report_details_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Report details'),
      '#states' => [
        'invisible' => [
          ':input[name="report_type"]' => [
            ['value' => ''],
            ['value' => 'players_list'],
          ],
        ],
      ],
    ];

    $form['report_details_wrapper']['game_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Game date'),
      '#states' => [
        'visible' => [
          ':input[name="report_type"]' => ['value' => 'single_game_report'],
        ],
        'required' => [
          ':input[name="report_type"]' => ['value' => 'single_game_report'],
        ],
      ],
    ];

    $form['report_details_wrapper']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'both' => 'Both',
        'removed' => 'Removed',
        'seated' => 'Seated',
      ],
      '#default_value' => 'both',
      '#states' => [
        'visible' => [
          ':input[name="report_type"]' => [
            ['value' => 'single_game_report'],
            ['value' => 'multiple_game_report']
          ],
        ],
        'required' => [
          ':input[name="report_type"]' => [
            ['value' => 'single_game_report'],
            ['value' => 'multiple_game_report']
          ],
        ],
      ],
    ];

    $form['report_details_wrapper']['game_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Game start date'),
      '#states' => [
        'visible' => [
          ':input[name="report_type"]' => ['value' => 'multiple_game_report'],
        ],
        'required' => [
          ':input[name="report_type"]' => ['value' => 'multiple_game_report'],
        ],
      ],
    ];

    $form['report_details_wrapper']['game_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Game end date'),
      '#states' => [
        'visible' => [
          ':input[name="report_type"]' => ['value' => 'multiple_game_report'],
        ],
        'required' => [
          ':input[name="report_type"]' => ['value' => 'multiple_game_report'],
        ],
      ],
    ];

    // Submit buttons.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // If we are on a multiple game report, ensure that the
    // start date is greater than the end date.
    if ($values['report_type'] == 'multiple_game_report') {

      // If the start date is less than the end date,
      // set the form error.
      if ($values['game_start_date'] > $values['game_end_date']) {
        $form_state->setError($form['report_details_wrapper']['game_start_date'], 'The start date must be before the end date.');
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Set the query parameters for the selected report.
    switch ($values['report_type']) {

      case 'single_game_report':
        // url to redirect
        $path = '/admin/reserve/report';

        $path_param = [
          'report_type' => $values['report_type'],
          'date' => $values['game_date'],
          'status' => $values['status'],
        ];
        break;

      case 'multiple_game_report':
        // url to redirect
        $path = '/admin/reserve/report';

        $path_param = [
          'report_type' => $values['report_type'],
          'start_date' => $values['game_start_date'],
          'end_date' => $values['game_end_date'],
          'status' => $values['status'],
        ];

      case 'players_list':
        $path = '/admin/reports/players-list';
        $path_param = [];
        break;
    }

    // use below if you have to redirect on your known url
    $url = Url::fromUserInput($path, ['query' => $path_param]);

    $form_state->setRedirectUrl($url);
  }

}
