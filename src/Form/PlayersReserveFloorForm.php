<?php
/**
 * @file
 * Contains \Drupal\players_reserve\Form\PlayersAddReserveForm.
 */
namespace Drupal\players_reserve\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\players_reserve\Service\PlayersService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;

class PlayersReserveFloorForm extends FormBase {

  /**
   * The players service.
   *
   * @var \Drupal\players_reserve\Service\PlayersService
   */
  protected $playersService;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param \Drupal\players_reserve\Service\PlayersService $playersService
   *  The players service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    PlayersService $playersService,
    MessengerInterface $messenger,
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager
  ) {

    $this->playersService = $playersService;
    $this->messenger = $messenger;
    $this->database = $database;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('players_reserve.players_service'),
      $container->get('messenger'),
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Checks access to the block add page for the block type.
   */
  public function access(AccountInterface $account) {

    // Get the user roles.
    $roles = $account->getRoles();

    // The list of allowed roles for the route.
    $allowed_roles = [
      'administrator',
      'floor',
    ];

    // Return access if user has correct role.
    if(array_intersect($roles, $allowed_roles)) {
      return AccessResult::allowed();
    }
    else {
      return AccessResult::forbidden();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'players_add_reserve_form';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $date = NULL
  ) {

    // Ensure that we have a tree for the form.
    $form['#tree'] = TRUE;

    // If there is no date supplied, get the current date.
    if (!$date) {
      $date = $this->playersService->getCorrectDate();
    }

    // Load the node based on the date.
    $node = current(
      $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['field_game_date' => $date]
        )
    );

    // Load the games for the date.
    $games = $this->playersService->getGames($node);

    // Flag to show the submit button.
    $show_submit_button = FALSE;

    // Wrapper for the date form.
    $form['date_wrapper'] = [
      '#type' => 'details',
      '#title' => $this->t('Date'),
      '#attributes' => [
        'class' => ['players-contained-width'],
      ],
    ];

    // The date element.
    $form['date_wrapper']['date'] = [
      '#type' => 'date',
      '#default_value' => $date
    ];

    // The button for the date.
    $form['date_wrapper']['button'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => ['players-button', 'players-button--blue']
      ],
      '#name' => 'btnDate',
    ];

    // Wrapper for the list.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-contained-width', 'players-reserve'],
      ],
    ];

    // Set the date.
    $form['wrapper']['date'] = [
      '#markup' => '<h3>' . date('l M j, Y', strtotime($date)) . '</h3>',
    ];

    // Step through each of the games and get the form.
    foreach ($games as $game) {

      // The game type.
      $form['wrapper'][$game['title']] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['players-contained-width', 'players-reserve'],
        ],
      ];

      // Info about the game.
      $form['wrapper'][$game['title']]['game'] = [
        '#markup' => '<h4>' . $game['title'] . '</h4>',
      ];

      // If there is no list, set message.
      // If there is a list, process it.
      if (!$game['list']) {
        $form['wrapper'][$game['title']]['list'][$game['title']] = [
          '#type' => 'markup',
          '#markup' => 'There are currently no players reserved for this game.'
        ];
      }
      else {

        // We have a list so show the submit button.
        $show_submit_button = TRUE;

        // The type of game, hidden so we can use it
        // in the submit.
        $form['wrapper'][$game['title']]['game_type'] = [
          '#type' => 'hidden',
          '#value' => $game['title'],
        ];

        // The nid, hidden so we can use it
        // in the submit.
        $form['wrapper'][$game['title']]['nid'] = [
          '#type' => 'hidden',
          '#value' => $node->id(),
        ];

        $form['wrapper'][$game['title']]['details'] = [
          '#type' => 'details',
          '#title' => $this->t('List'),
        ];

        // The header for the table.
        $header = [
          ['data' => t('First Name')],
          ['data' => t('Last Name')],
          ['data' => t('Reserve Time')],
          ['data' => t('Options')],
        ];

        // The table for the list.
        $form['wrapper'][$game['title']]['details']['list'] = [
          '#type' => 'table',
          '#title' => 'Sample Table',
          '#header' => $header,
        ];

        // Count for the number of players.
        $count = 0;

        // Step through and add players to list.
        foreach ($game['list'] as $player) {

          // Player first name.
          $form['wrapper'][$game['title']]['details']['list'][$count]['first_name'] = [
            '#type' => 'markup',
            '#markup' => $player->first_name,
          ];

          // Player last name.
          $form['wrapper'][$game['title']]['details']['list'][$count]['last_name'] = [
            '#type' => 'markup',
            '#markup' => $player->last_name,
          ];

          // Player last name.
          $form['wrapper'][$game['title']]['details']['list'][$count]['reserve_time'] = [
            '#type' => 'markup',
            '#markup' => date('g:i a (M d)', strtotime($player->reserve_time)),
          ];

          // The fieldset for the options.
          $form['wrapper'][$game['title']]['details']['list'][$count]['options'] = [
            '#type' => 'container',
          ];

          // The seated checkbox.
          $form['wrapper'][$game['title']]['details']['list'][$count]['options']['seated'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Seated'),
          ];

          // The remove checkbox.
          $form['wrapper'][$game['title']]['details']['list'][$count]['options']['remove'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Remove'),
          ];

          // The reserve id, this is needed so we can reference
          // the entry in the DB.
          $form['wrapper'][$game['title']]['details']['list'][$count]['options']['reserve_id'] = [
            '#type' => 'hidden',
            '#value' => $player->reserve_id,
          ];

          // Get the url and date, not it can be reserve.
          $url = explode('/', \Drupal::request()->getRequestUri());
          $url_date = end($url);

          // Add the current list to the form.
          $this->getCurrentListForm(
            $form,
            $url_date,
            $date,
            $node->id(),
            $game['title']
          );

          // Increment the counter.
          $count++;
        }
      }

      // Show the submit button if the flag is set.
      if ($show_submit_button) {

        // The submit button.
        $form['wrapper']['actions']['#type'] = 'actions';
        $form['wrapper']['actions']['submit'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Submit'),
          '#button_type' => 'primary',
          '#attributes' => [
            'class' => ['players-button', 'players-button--blue']
          ],
        );
      }

      // The button for the date.
      $form['wrapper']['actions']['button'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add player to reserve'),
        '#attributes' => [
          'class' => ['players-button', 'players-button--blue']
        ],
        '#name' => 'btnAddPlayer',
      ];
    }

    return $form;
  }

  /**
   * Function to get the current list.
   *
   * @param array &$form
   *   The form.
   * @param string $url_date
   *   The url date.
   * @param string $date
   *   The current date.
   * @param int $nid
   *   The node id.
   * @param string $game_type
   *   The type of game.
   */
  private function getCurrentListForm(
    array &$form,
    string $url_date,
    string $date,
    int $nid,
    string $game_type
  ) {

    // Set the current list.
    $current_list = [];

    // If we are on todays date or just /reserve,
    // get the list of current players.
    if ($url_date == 'reserve' || $url_date == $date || $url_date == 'floor') {
      $current_list = $this->playersService->getCurrentList($nid, $game_type);
    }

    // If there is a list of current players, get the
    // form element for it.
    if (count($current_list) > 0) {

      // Set the details.
      $form['wrapper'][$game_type]['details']['current_list'] = [
        '#type' => 'markup',
        '#markup' => '<details class="players-details" data-once="details" open="true">',
        '#prefix' => '<p>',
        '#suffix' => '</details></p>',
      ];

      // Set the summary.
      $form['wrapper'][$game_type]['details']['current_list']['summary'] = [
        '#type' => 'markup',
        '#markup' => ' <summary class="players-summary" aria-expanded="true" aria-pressed="true">Seated players<span class="summary"></span></summary>',
      ];

      // The header for the table.
      $header = [
        t('First Name'),
        t('Last Name'),
        t('Seated/Left')
      ];

      // The table for the list.
      $form['wrapper'][$game_type]['details']['current_list']['clist'] = [
        '#type' => 'table',
        '#title' => 'Current list',
        '#header' => $header,
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];

      // The counter for the table.
      $count = 0;

      // Set the player left flag.
      $player_left_flag = FALSE;

      // Step through and add players to list.
      foreach ($current_list as $player) {

        // If this players has left and the flag is not set,
        // then add a blank row for display.
        if ($player->pleft && !$player_left_flag) {

          // Reset the rows array.
          $rows = [];

          // Add the row with blank and the class.
          for ($i = 0; $i < 3; $i++) {
            $rows[] = [
              'data' => [
                '#type' => 'markup',
                '#markup' => '',
              ],
              '#wrapper_attributes' => [
                'class' => ['players-reserve__black'],
              ],
            ];
          }

          // Add the rows to the list.
          $form['wrapper'][$game_type]['details']['current_list']['clist'][$count] = $rows;

          // Set the player left flag.
          $player_left_flag = TRUE;

          // Increment the counter.
          $count++;
        }


        // Player first name.
        $form['wrapper'][$game_type]['details']['current_list']['clist'][$count]['first_name'] = [
          '#type' => 'markup',
          '#markup' => $player->first_name,
        ];

        // Player last name.
        $form['wrapper'][$game_type]['details']['current_list']['clist'][$count]['last_name'] = [
          '#type' => 'markup',
          '#markup' => $player->last_name,
        ];

        // The list of options.
        $form['wrapper'][$game_type]['details']['current_list']['clist'][$count]['options'] = [
          '#type' => 'container',
        ];

        // Get the default value for the seated/left.
        $default_value = NULL;
        if ($player->seated) {
          $default_value = 'seated';
        }
        if ($player->pleft) {
          $default_value = 'left';
        }

        // The seated/left element.
        $form['wrapper'][$game_type]['details']['current_list']['clist'][$count]['options']['seated-left'] = [
          '#type' => 'radios',
          '#options' => [
            'seated' => $this->t('Seated'),
            'left' => $this->t('Left'),
          ],
          '#default_value' => $default_value,
        ];

        // The reserve id element.
        $form['wrapper'][$game_type]['details']['current_list']['clist'][$count]['options']['reserve_id'] = [
          '#type' => 'hidden',
          '#default_value' => $player->reserve_id,
        ];

        // Increment the counter.
        $count++;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Get the triggering submit button.
    $submit_button = $form_state->getTriggeringElement();

    // If this is a date change, the set the redirect.
    if (
      isset($submit_button['#name']) &&
      $submit_button['#name'] == 'btnDate'
    ) {

      // Get the date from the values.
      $date = $values['date_wrapper']['date'];

      // Redirect the form to include the date.
      $form_state->setRedirect('players_reserve.floor_date', ['date' => $date]);

      // Need return here so that the form stops processing.
      return;
    }

    // If this is a date change, the set the redirect.
    if (
      isset($submit_button['#name']) &&
      $submit_button['#name'] == 'btnAddPlayer'
    ) {

      // Get the date from the values.
      $date = $values['date_wrapper']['date'];

      // Redirect the form to include the date.
      $form_state->setRedirect('players_reserve.floor_add_player', ['date' => $date]);

      // Need return here so that the form stops processing.
      return;
    }

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Get all the game types.
    $game_types = _players_cfg_game_types();

    // Step through and get the values of the games.
    foreach ($values['wrapper'] as $index => $value) {

      // If the game type is in the allowed game types,
      // set the values for the game.
      if (in_array($index, $game_types)) {
        $games[$index] = [
          'game_type' => $value['game_type'],
          'nid' => $value['nid'] ?? NULL,
          'list' => $value['details']['list'] ?? NULL,
          'current_list' => $value['details']['current_list']['clist'] ?? NULL,
        ];
      }
    }

    // If there are games, then process them.
    if ($games) {

      // Step through each game and set in the database.
      foreach ($games as $game) {

        // Get the list from the form state.
        $list = $game['list'];

        // Get the list using the players service.
        $old_list = $this->playersService->getList($game['nid'], $game['game_type']);

        // Counter for the number of players.
        $count = 0;

        // Step through the list and update any players info.
        foreach ($list as $player) {

          // If the player is marked as seated or removed, update the DB.
          if (
            (
              isset($player['options']['seated']) &&
              $player['options']['seated']
            ) ||
            (
              isset($player['options']['remove']) &&
              $player['options']['remove']
            )
          ) {

            // Start the query.
            $query = $this->database->update('players_reserve');

            // Add the field to query based on the selection.
            if ($player['options']['seated']) {
              $query->fields([
                'seated' => 1,
              ]);
              $status = 'seated';
            } else {
              $query->fields([
                'removed' => 1,
              ]);
              $status = 'removed';
            }

            // Add the reserve id to the query.
            $query->condition('reserve_id', $player['options']['reserve_id']);

            // Execute the query.
            $query->execute();

            // Add the message.
            $this->messenger->addStatus($this->t('The player(s) has been marked as @status', ['@status' => $status]));

            // Increment the counter.
            $count++;
          }
        }

        // If there is a current list, update it.
        if (
          isset($game['current_list']) &&
          count($game['current_list']) > 0
        ) {

          // Step through each of the current list and update it.
          foreach ($game['current_list'] as $clist) {

            // Start the query.
            $query = $this->database->update('players_reserve');

            // Add the field to query based on the selection.
            if ($clist['options']['seated-left'] == 'seated') {
              $query->fields([
                'seated' => 1,
                'pleft' => 0,
              ]);
            } else {
              $query->fields([
                'seated' => 0,
                'pleft' => 1,
              ]);
            }

            // Add the reserve id to the query.
            $query->condition('reserve_id', $clist['options']['reserve_id']);

            // Execute the query.
            $query->execute();
          }

          // Add the message.
          $this->messenger->addStatus($this->t('The current list has been updated'));
        }
      }
    }
  }

}
