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
          '#markup' => date('g:h a', strtotime($player->reserve_time)),
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
    }

    // Only add submit button if there is a list.
    if (count($list) > 0) {

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

  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Get the list from the form state.
    $list = $values['list'];

    // Get the list using the players service.
    $old_list = $this->playersService->getList($values['nid'], $values['game_type']);

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
        }
        else {
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
        $this->messenger->addStatus($this->t('@player_name has been marked as @status', ['@player_name' => $player_name, '@status' => $status]));

        // Increment the counter.
        $count++;
      }
    }
  }

}
