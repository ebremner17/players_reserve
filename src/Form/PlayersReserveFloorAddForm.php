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
use Drupal\Core\Url;
use Drupal\players_reserve\Service\PlayersService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;

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

    return new static(
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
    return 'players_floor_add_player_form';
  }

  /**
   * Build the form.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $form_state
   *   The form state.
   * @param string $date
   *   The date to add a player too.
   *
   * @return array
   *   Array of form elements.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    string $date = NULL
  ) {

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

      if (!$user->isAuthenticated()) {
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

    // If there are no games, meaning that we are on the wrong
    // date for a regular users, then return to the reserve page.
    // This can only happen when a user manually change the link
    // in the browser.
    if (empty($games['games'])) {
      $url = Url::fromUri('internal:/floor')->toString();
      $response = new RedirectResponse($url);
      $response->send();
      return;
    }

    $form['#prefix'] = '<div class="players-contained-width">';
    $form['#suffix'] = '</div>';

    // Get the display date.
    $form['display_date'] = [
      '#markup' => '<h3>' . date('l F j, Y', strtotime($date)) . '</h3>',
    ];

    // Get the options for the type of games.
    foreach ($games['games'] as $game) {
      $options[$game['title']] = $game['title'] . ': ' . $game['start_time'] . ' - ' . $game['end_time'];
    }

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
      '#selection_handler' => 'views',
      '#selection_settings' => [
        'view' => [
          'view_name' => 'pi_view_users_by_name',
          'display_name' => 'member',
          'arguments' => [],
        ],
        'match_operator' => 'CONTAINS'
      ],
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

    $form['player_seated'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Seated'),
      '#states' => [
        'invisible' => [
          ':input[name="uid"]' => ['value' => ''],
          'or',
          ':input[name="first_name"]' => ['value' => ''],
          'or',
          ':input[name="last_name"]' => ['value' => ''],
        ],
      ],
    ];

    // The player is seated for element.
    $form['player_seated']['seated'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Player is seated'),
    ];

    // Array to store the default values for the games.
    $default_values = [];

    // The Saturday dates that are exempt from the radio
    // buttons, doenst happen often.
    $saturday_exempt_dates = [
      '2022-12-30',
    ];

    // Need to allow for only single selections for players
    // on Saturdays.
    if (
      count($options) > 1 &&
      date("l", strtotime($date)) == "Saturday" &&
      !in_array(date("Y-m-d", strtotime($date)), $saturday_exempt_dates) &&
      !$this->playersService->isFloor()
    ) {

      $tourney_options = [];
      $cash_options = [];
      $default_values_tourney = [];
      $default_values_cash = [];

      foreach ($options as $index => $option) {
        if (str_starts_with($index, 'Tournament')) {
          $tourney_options[$index] = $option;
        }
        else {
          $cash_options[$index] = $option;
        }
      }

      // Step through each of the games and add the
      // game title if the flag is set.
      foreach ($games['games'] as $game) {


        // If the flag for the user as being reserved is
        // set then add to the default values.
        if ($game['reserved_flag']) {
          if (str_starts_with($game['title'], 'Tournament')){
            $default_values_tourney[] = $game['title'];
          }
          else {
            $default_values_cash = $game['title'];
          }
        }
      }

      if (!empty($tourney_options)) {
        $form['tourney_games'] = [
          '#type' => 'checkboxes',
          '#options' => $tourney_options,
          '#title' => $this->t('Tournament(s)'),
          '#required' => $this->playersService->isFloor() ? TRUE : FALSE,
          '#default_value' => $default_values_tourney,
        ];
      }

      if (!empty($cash_options)) {

        // The games element for Saturday nights.
        $form['games'] = [
          '#type' => 'radios',
          '#options' => $cash_options,
          '#title' => $this->t('Cash Game(s)'),
          '#required' => $this->playersService->isFloor() ? TRUE : FALSE,
          '#default_value' => $default_values_cash,
        ];
      }
    }
    else {

      // Step through each of the games and add the
      // game title if the flag is set.
      foreach ($games['games'] as $game) {

        // If the flag for the user as being reserved is
        // set then add to the default values.
        if ($game['reserved_flag']) {
          $default_values[] = $game['title'];
        }
      }

      // The games element for everything other than
      // Friday nights.
      $form['games'] = [
        '#type' => 'checkboxes',
        '#options' => $options,
        '#title' => $this->t('Game types'),
        '#required' => $this->playersService->isFloor() ? TRUE : FALSE,
        '#default_value' => $default_values,
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
   * Submit form.
   *
   * @param array $form
   *   The form.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Get the nid and the reserve time.
    $nid = $values['nid'];
    $reserve_time = date('Y-m-d H:i:s');

    // Player is seated flag.
    $player_is_seated = $values['seated'] ?? 0;

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

    // If there are tourney games at them to the reserve.
    if (isset($values['tourney_games'])) {

      // Step through each of the game types and insert
      // the details for the user/game.
      foreach ($values['tourney_games'] as $game_type) {

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
              'seated' => $player_is_seated,
            ])
            ->execute();
        }
      }
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
              'seated' => $player_is_seated,
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
            'seated' => $player_is_seated,
          ])
          ->execute();
      }
    }

    // Add the message about successully adding the player.
    $this->messenger->addStatus('This player has been added to the reserve.');

    // Get the node and the date.
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($nid);
    $date = $node->label();

    $form_state->setRedirect('players_reserve.floor_date', ['date' => $date]);
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

}
