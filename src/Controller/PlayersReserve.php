<?php

namespace Drupal\players_reserve\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\players_reserve\Service\PlayersService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reserve page for players games.
 */
class PlayersReserve extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The players service.
   *
   * @var \Drupal\players_reserve\Service\PlayersService
   */
  protected $playersService;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTyperManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\players_reserve\Service $playersService
   *   The players service.
   * @param
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $account,
    MessengerInterface $messenger,
    PlayersService $playersService
  ) {

    $this->entityTypeManager = $entityTypeManager;
    $this->account = $account;
    $this->messenger = $messenger;
    $this->playersService = $playersService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('players_reserve.players_service')
    );
  }

  /**
   * Returns a page for players reserve.
   *
   * @return array
   *   A simple renderable array.
   */
  public function reserve($date = NULL) {

    // Array to hold the game info.
    $games = [];

    if (!$date) {
      // Get the correct date.
      $date = $this->playersService->getCorrectDate($date);
    }

    // Get the node based on the current date.
    $node = $this->playersService->getGameNodeByDate($date);

    // If there is node, get the info about the games.
    if ($node) {

      // Flag for if floor.
      $games['floor'] = $this->playersService->isFloor();

      // Add the nid to the variables.
      $games['nid'] = $node->id();

      // Get the current user.
      $user = $this->playersService->getCurrentUser();

      // Set the user status to false,
      // change only if logged in and not
      // registered for the games.
      $games['status'] = FALSE;

      // If they are logged in, check if reserved.
      // If they are not, set the message about being
      // logged in and registered.
      if ($this->account->isAuthenticated()) {

//        // Get if user is reserved.
//        $games['reserve'] = $this->playersService->checkUserReserved($user->id(), $node->id());

//        // If they are not reserved, set flag to show button.
//        // If they are reserved, set the message that
//        // they are already reserved.
//        if (!$reserve) {
//          $games['status'] = TRUE;
//        }
//        else {
//
//          // Get the current status messages.
//          $messages = $this->messenger->messagesByType('status');
//
//          // If there is no messages or there is no message
//          // about already been added, add the message about
//          // they have already reserved.
//          if (
//            $messages == NULL ||
//            (
//              isset($messages[0]) &&
//              $messages[0] !== 'You have been added to the reserve.'
//            )
//          ) {
//
//            // Add the message about already being reserved.
//            $this->messenger()->addStatus('You have already reserved for todays game.');
//          }
//        }
      }
      else {

        // Add the message that you must be logged in.
        $this->messenger()->addError('You must be logged in or registered with Players Inc to reserve a game.');
      }
    }

    // Get the games.
    $games['games'] = $this->playersService->getGames($node);

    // Set the display date and actual date in the games array.
    $games['display_date'] = date('l F j, Y', strtotime($date));
    $games['date'] = $date;

    // The roles not get tournaments for.
    $roles = [
      'administrator',
      'owner',
      'floor',
    ];

    // If the user is just a user, get the tournaments.
    // If not return NULL array so the site doesn't complain.
    if(empty(array_intersect($this->account->getRoles(), $roles))) {
      $games['tourneys'] = $this->playersService->getTournaments();
    }
    else {
      $games['tourneys'] = [];
    }

    $next_six_dates = [];

    for ($i = 1; $i < 7; $i++) {
      $next_six_dates[] = date('Y-m-d', strtotime('now +' . $i . ' day'));
    }

    // Get the next week of games.
    foreach ($next_six_dates as $next_date) {

      // Try and load the game node.
      $node = current($this->entityTypeManager->getStorage('node')->loadByProperties(['title' => $next_date]));

      // If there is a game node add it to the future games.
      if ($node) {
        $games['future_games'][] = [
          'display_date' => date('l F j, Y', strtotime($next_date)),
          'date' => $next_date,
          'games' => $this->playersService->getGames($node, TRUE),
        ];
      }
    }

    // Adding if logged in flag.
    $games['is_logged_in'] = $this->account->isAuthenticated();

    // Set the render array.
    return [
      '#theme' => 'players_reserve',
      '#games' => $games,
    ];
  }

}
