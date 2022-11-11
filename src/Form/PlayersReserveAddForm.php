<?php
/**
 * @file
 * Contains \Drupal\players_reserve\Form\PlayersAddReserveForm.
 */
namespace Drupal\players_reserve\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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

    $form['#prefix'] = '<div class="players-contained-width">';
    $form['#suffix'] = '</div>';

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
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('User names'),
        '#target_type' => 'user',
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

    // Need to allow for only single selections for players
    // on Fridays.
    if (
      count($options) > 1 &&
      date("l", strtotime($date)) == "Friday" &&
      !$this->playersService->isFloor()
    ) {
      // Fieldset for the list of games.
      $form['games'] = [
        '#type' => 'radios',
        '#options' => $options,
        '#title' => $this->t('Game types'),
        '#required' => TRUE,
      ];
    }
    else {
      // Fieldset for the list of games.
      $form['games'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#title' => $this->t('Game types'),
        '#required' => TRUE,
      ];
    }

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

    // Get the nid and the reserve time.
    $nid = $values['nid'];
    $reserve_time = date('Y-m-d H:I:s');

    // If the user is a floor then get the info from the
    // values entered.
    // If user is not floor then get values from user object.
    if ($this->playersService->isFloor()) {

      // Get if the player is already a user.
      $uid = $values['player_is_user'] == 'yes' ? $values['uid'] : NULL;

      // If the player is a user get their name from user object.
      // If not get name from values entered.
      if ($uid) {

        // Load the user from the uid supplied.
        $user = $this->entityTypeManager->getStorage('user')->load($uid);

        // Get the first and last name from the user object.
        $first_name = $user->field_user_first_name->value;
        $last_name = $user->field_user_last_name->value;
      }
      else {

        // Get the first and last name form the values entered.
        $first_name = $values['first_name'];
        $last_name = $values['last_name'];
      }
    }
    else {

      // Get the current user.
      $user = $this->playersService->getCurrentUser();

      // Get the user id, first name and last name
      // from the user object.
      $uid = $user->id();
      $first_name = $user->field_user_first_name->value;
      $last_name = $user->field_user_last_name->value;
    }

    // Need to check if we are using multiple selections or not.
    if (is_array($values['games'])) {

      // Step through each of the game types and insert
      // the details for the user/game.
      foreach ($values['games'] as $game_type) {

        // If there is a game selected, then add it
        // to the reserve.
        if ($game_type !== 0) {
          $this->database
            ->insert('players_reserve')
            ->fields([
              'uid' => $uid,
              'nid' => $nid,
              'first_name' => $first_name,
              'last_name' => $last_name,
              'game_type' => $game_type,
              'reserve_time' => $reserve_time,
            ])
            ->execute();
        }
      }
    }
    else {

      // If there is a game selected, then add it
      // to the reserve.
      if ($values['games'] !== 0) {
        $this->database
          ->insert('players_reserve')
          ->fields([
            'uid' => $uid,
            'nid' => $nid,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'game_type' => $values['games'],
            'reserve_time' => $reserve_time,
          ])
          ->execute();
      }
    }

    if ($this->playersService->isFloor()) {
      $this->messenger->addStatus('This player has been added to the reserve.');
    }
    else {
      $this->messenger->addStatus('You have been added to the reserve.');
    }

    $form_state->setRedirect('players_reserve.reserve');
  }

}
