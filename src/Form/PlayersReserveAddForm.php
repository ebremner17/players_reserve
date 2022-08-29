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

    $games['games'] = $this->playersService->getGames($node);

    $form['display_date'] = [
      '#markup' => '<h3>' . date('l F j, Y', strtotime($date)) . '</h3>',
    ];

    foreach ($games['games'] as $game) {
      $options[$game['title']] = $game['title'];
    }

    $form['games'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Game types'),
      '#required' => TRUE,
    ];

    $form['nid'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Reserve'),
      '#button_type' => 'primary',
    );
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
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
