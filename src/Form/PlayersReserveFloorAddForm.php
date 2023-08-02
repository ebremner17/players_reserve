<?php
/**
 * @file
 * Contains \Drupal\players_reserve\Form\PlayersAddReserveForm.
 */
namespace Drupal\players_reserve\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\players_reserve\Service\PlayersService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;

class PlayersReserveFloorAddForm extends FormBase {

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
   * @param \Drupal\players_reserve\Service\PlayersService $playersService
   *  The players service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database
   */
  public function __construct(
    PlayersService $playersService,
    MessengerInterface $messenger,
    Connection $database
  ) {

    $this->playersService = $playersService;
    $this->messenger = $messenger;
    $this->database = $database;
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
      $container->get('database')
    );
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
    $nid = NULL,
    $game_type = NULL
  ) {

    // Get the list of players.
    $list = $this->playersService->getList($nid, $game_type);

    // If there is no list, then add message about no
    // players reserved.
    // If there is a list, then get the list.
    if (!$list) {
      $form['list'][$game_type] = [
        '#type' => 'markup',
        '#markup' => 'There are currently no players reserved for this game.'
      ];

      // Get the url and date, not it can be reserve.
      $url = explode('/', \Drupal::request()->getRequestUri());
      $url_date = end($url);

      // Get the corrected date.
      $date = $this->playersService->getCorrectDate();

      $this->getCurrentListForm($form, $url_date, $date, $nid, $game_type);
    }
    else {

      // The type of game, hidden so we can use it
      // in the submit.
      $form['game_type'] = [
        '#type' => 'hidden',
        '#value' => $game_type,
      ];

      // The nid, hidden so we can use it
      // in the submit.
      $form['nid'] = [
        '#type' => 'hidden',
        '#value' => $nid,
      ];

      // The header for the table.
      $header = [
        ['data' => t('First Name')],
        ['data' => t('Last Name')],
        ['data' => t('Reserve Time')],
        ['data' => t('Options')],
      ];

      // The table for the list.
      $form['list'] = [
        '#type' => 'table',
        '#title' => 'Sample Table',
        '#header' => $header,
      ];

      // Count for the number of players.
      $count = 0;

      // Step through and add players to list.
      foreach ($list as $player) {

        // Player first name.
        $form['list'][$count]['first_name'] = [
          '#type' => 'markup',
          '#markup' => $player->first_name,
        ];

        // Player last name.
        $form['list'][$count]['last_name'] = [
          '#type' => 'markup',
          '#markup' => $player->last_name,
        ];

        // Player last name.
        $form['list'][$count]['reserve_time'] = [
          '#type' => 'markup',
          '#markup' => date('g:i a (M d)', strtotime($player->reserve_time)),
        ];

        // The fieldset for the options.
        $form['list'][$count]['options'] = [
          '#type' => 'container',
        ];

        // The seated checkbox.
        $form['list'][$count]['options']['seated'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Seated'),
        ];

        // The remove checkbox.
        $form['list'][$count]['options']['remove'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Remove'),
        ];

        // The reserve id, this is needed so we can reference
        // the entry in the DB.
        $form['list'][$count]['options']['reserve_id'] = [
          '#type' => 'hidden',
          '#value' => $player->reserve_id,
        ];

        // Increment the counter.
        $count++;
      }

      // Get the url and date, not it can be reserve.
      $url = explode('/', \Drupal::request()->getRequestUri());
      $url_date = end($url);

      // Get the corrected date.
      $date = $this->playersService->getCorrectDate();

      $this->getCurrentListForm($form, $url_date, $date, $nid, $game_type);
    }

    // Show submit button flag.
    $show_submit_button = FALSE;

    // If there is a list, set the show submit button flag.
    if (count($list) > 0) {
      $show_submit_button = TRUE;
    }

    // If there is a current list, set the show submit
    // button flag.
    if (
      isset($form['current_list']['clist']) &&
      array_key_exists(0, $form['current_list']['clist'])
    ) {
      $show_submit_button = TRUE;
    }

    // Only add submit button if there is a list.
    if ($show_submit_button) {

      // The submit button.
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#button_type' => 'primary',
      );
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
    if ($url_date == 'reserve' || $url_date == $date) {
      $current_list = $this->playersService->getCurrentList($nid, $game_type);
    }

    // If there is a list of current players, get the
    // form element for it.
    if (count($current_list) > 0) {

      // Set the details.
      $form['current_list'] = [
        '#type' => 'markup',
        '#markup' => '<details class="players-details" data-once="details" open="true">',
        '#prefix' => '<p>',
        '#suffix' => '</details></p>',
      ];

      // Set the summary.
      $form['current_list']['summary'] = [
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
      $form['current_list']['clist'] = [
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
          $form['current_list']['clist'][$count] = $rows;

          // Set the player left flag.
          $player_left_flag = TRUE;

          // Increment the counter.
          $count++;
        }


        // Player first name.
        $form['current_list']['clist'][$count]['first_name'] = [
          '#type' => 'markup',
          '#markup' => $player->first_name,
        ];

        // Player last name.
        $form['current_list']['clist'][$count]['last_name'] = [
          '#type' => 'markup',
          '#markup' => $player->last_name,
        ];

        // The list of options.
        $form['current_list']['clist'][$count]['options'] = [
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
        $form['current_list']['clist'][$count]['options']['seated-left'] = [
          '#type' => 'radios',
          '#options' => [
            'seated' => $this->t('Seated'),
            'left' => $this->t('Left'),
          ],
          '#default_value' => $default_value,
        ];

        // The reserve id element.
        $form['current_list']['clist'][$count]['options']['reserve_id'] = [
          '#type' => 'hidden',
          '#default_value' => $player->reserve_id,
        ];

        // Increment the counter.
        $count++;
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // If ther is a list process it.
    if (isset($values['list']) && count($values['list']) > 0) {

      // Get the list from the form state.
      $list = $values['list'];

      // Get the list using the players service.
      $old_list = $this->playersService->getList($values['nid'], $values['game_type']);

      // Counter for the number of players.
      $count = 0;

      // Step through the list and update any players info.
      foreach ($list as $player) {

        // If the player is marked as seated or removed, update the DB.
        if ($player['options']['seated'] || $player['options']['remove']) {

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

          // Get the player info from the old list.
          $player_info = $old_list[$count];

          // Get the player name.
          $player_name = $player_info->first_name . ' ' . $player_info->last_name;

          // Add the message.
          $this->messenger->addStatus($this->t('The player(s) has been marked as @status', ['@status' => $status]));

          // Increment the counter.
          $count++;
        }
      }
    }

    // If there is a current list, update it.
    if (isset($values['clist']) && count($values['clist']) > 0) {

      // Step through each of the current list and update it.
      foreach ($values['clist'] as $clist) {

        // Start the query.
        $query = $this->database->update('players_reserve');

        // Add the field to query based on the selection.
        if ($clist['options']['seated-left'] == 'seated') {
          $query->fields([
            'seated' => 1,
            'pleft' => 0,
          ]);
        }
        else {
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

//    drupal_flush_all_caches();
  }

}
