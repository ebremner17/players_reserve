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

    // Array of the next six dates.
    $next_six_dates = [];

    // Step through each date and get the games.
    for ($i = 0; $i < 7; $i++) {
      $next_six_dates[] = date('Y-m-d', strtotime('now +' . $i . ' day'));
    }

    // Get the next week of games.
    foreach ($next_six_dates as $next_date) {

      // Try and load the game node.
      $node = current(
        $this->entityTypeManager
          ->getStorage('node')
          ->loadByProperties(['title' => $next_date])
      );

      // If there is a game node add it to the future games.
      if ($node) {
        $games['future_games'][] = $this->playersService->getGameInfo($node);
      }
    }

    // Set the render array.
    return [
      '#theme' => 'players_reserve',
      '#games' => $games,
    ];
  }

}
