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
        ['data' => t('Seated')],
        ['data' => t('Remove')],
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

        // If player is seated.
        $form['list'][$count]['seated'] = [
          '#type' => 'checkbox',
        ];

        // To remove a player.
        $form['list'][$count]['remove'] = [
          '#type' => 'checkbox',
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

    $old_list = $this->playersService->getList($values['nid'], $values['game_type']);

    $list = $values['list'];

    $count = 0;
    foreach ($list as $player) {
      if ($player['seated']) {

        $player_info = $old_list[$count];


        $num_updated = $this->database
          ->update('players_reserve')
          ->fields([
            'seated' => 1,
          ])
          ->condition('reserve_id', $player_info->reserve_id)
          ->execute();
      }
    }

    // Get the current user.
    $user = $this->playersService->getCurrentUser();

    // Step through each of the game types and insert
    // the details for the user/game.
    foreach ($values['games'] as $game_type) {
      $this->database
        ->insert('players_reserve')
        ->fields([
          'uid' => $user->id(),
          'nid' => $values['nid'],
          'first_name' => $user->field_user_first_name->value,
          'last_name' => $user->field_user_last_name->value,
          'game_type' => $game_type,
          'reserve_time' => date('Y-m-d H:I:s'),
        ])
        ->execute();
    }
  }

}
