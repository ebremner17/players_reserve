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

class PlayersReserveAddForm extends FormBase {

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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $date = NULL) {

    // Array to hold the game info.
    $games = [];

    // Get the node based on the current date.
    $node = $this->playersService->getGameNodeByDate($date);

    // If there is node, get the info about the games.
    if ($node) {

      // Get the current user.
      $user = $this->playersService->getCurrentUser();

      // Set the user status to false,
      // change only if logged in and not
      // registered for the games.
      $games['status'] = FALSE;

      // If they are logged in, check if reserved.
      // If they are not, set the message about being
      // logged in and registered.
      if ($user->isAuthenticated()) {

        // Get if user is reserved.
        $reserve = $this->playersService->checkUserReserved($user->id(), $node->id());

        // If they are not reserved, set flag to show button.
        // If they are reserved, set the message that
        // they are already reserved.
        if ($reserve) {
          $this->messenger()->addStatus('You have already reserved for todays game.');
          return [];
        }
      }
      else {
        $this->messenger()->addError('You must be logged in or registered with Players Inc to reserve a game.');
        return [];
      }
    }
    else {
      $this->messenger()->addError('The are currently no games schedule for this date.');
      return [];
    }

    // Get the games.
    $games['games'] = $this->playersService->getGames($node);

    // Get the display date.
    $form['display_date'] = [
      '#markup' => '<h3>' . date('l F j, Y', strtotime($date)) . '</h3>',
    ];

    // Get the options for the type of games.
    foreach ($games['games'] as $game) {
      $options[$game['title']] = $game['title'];
    }

    // If the user is floor, give option to add a player.
    if ($this->playersService->isFloor()) {

      // Choose if player is a user on the website.
      $form['player_is_user'] = [
        '#type' => 'select',
        '#title' => $this->t('Is player a user of the website?'),
        '#options' => [
          '' => '-- Select --',
          'yes' => 'Yes',
          'no' => 'No',
        ],
      ];

      // Fieldset for the player uid.
      $form['player_uid'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('User'),
        '#states' => [
          'visible' => [
            ':input[name="player_is_user"]' => ['value' => 'yes'],
          ],
        ],
      ];

      // The player uid, using autocomplete.
      $form['player_uid']['uid'] = [
        '#type' => 'textfield',
        '#title' => $this->t('User names'),
        '#autocomplete_route_name' => 'players_reserve.autocomplete.users',
      ];

      // Fieldset for the player info, if they are not
      // a registered user.
      $form['player_info'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Player Info'),
        '#states' => [
          'visible' => [
            ':input[name="player_is_user"]' => ['value' => 'no'],
          ],
        ],
      ];

      // The player first name.
      $form['player_info']['first_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('First Name'),
      ];

      // The player last name.
      $form['player_info']['last_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Last Name'),
      ];

      // Option to add a player to the website.
      $form['player_info']['add'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Add to website'),
      ];

      // The player email address.
      $form['player_info']['email'] = [
        '#type' => 'email',
        '#title' => $this->t('Email'),
        '#states' => [
          'visible' => [
            ':input[name="add"]' => ['checked' => TRUE],
          ],
        ],
      ];

      // The player phone number.
      $form['player_info']['phone'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Phone'),
        '#states' => [
          'visible' => [
            ':input[name="add"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    // Fieldset for the list of games.
    $form['games'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Game types'),
      '#required' => TRUE,
    ];

    // Hidden value for the nid.
    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    // Submit buttons.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Reserve'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

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
