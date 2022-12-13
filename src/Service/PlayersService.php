<?php

namespace Drupal\players_reserve\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Database\Connection;
use Drupal\node\Entity\Node;

/**
 * Class PlayersService.
 */
class PlayersService  {

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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTyperManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $account,
    MessengerInterface $messenger,
    Connection $database
  ) {

    $this->entityTypeManager = $entityTypeManager;
    $this->account = $account;
    $this->messenger = $messenger;
    $this->database = $database;
  }

  /**
   * Function to get the correct date based on the time.
   *
   * @return string
   *   The correct date based on the time.
   */
  public function getCorrectDate(): string {

    // Todays time, need this to get what is the current
    // date.  If we are less than 4 am into the next day
    // the current date is yesterday.
    $time = date('G');

    // Get the correct date based on the time.
    if ($time >= 0 && $time <= 4) {
      return date('Y-m-d', strtotime("-1 days"));
    }
    else {
      return date('Y-m-d');
    }
  }

  /**
   * Function to get the node of the game by date.
   *
   * @param string $date
   *   The date for the node.
   * @return false|mixed|null
   *   The game node.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getGameNodeByDate(string $date) {

    // Get the node based on the current date.
    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['field_game_date' => $date]);

    if ($nodes) {
      return current($nodes);
    }
    else {
      return NULL;
    }
  }

  /**
   * Function to get the current user.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The user object.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getCurrentUser() {

    // Get the user id and the user object.
    $user_id = $this->account->id();
    return $this->entityTypeManager
      ->getStorage('user')
      ->load($user_id);
  }

  /**
   * Function to check if user is reserved.
   *
   * @param int $uid
   *   The user id.
   * @param int $nid
   *   The node id.
   *
   * @return mixed
   *  The user reservations.
   */
  public function checkUserReserved(int $uid, int $nid) {
    $query = $this->database->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id'])
      ->condition('pr.nid', $nid)
      ->condition('pr.uid', $uid);

    return $query->execute()->fetchAssoc();
  }

  /**
   * Function to get the games for a specific node.
   *
   * @param $node
   *   The node.
   * @param bool $remove_tourney_flag
   *   Flag to remove tournaments.
   *
   * @return array
   *   Array of games.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getGames($node, $remove_tourney_flag = FALSE) {

    if (!$node) {
      return [];
    }

    $games = [];

    // Get data from field.
    if ($paragraph_field_items = $node->get('field_game_types')->getValue()) {

      // Get storage. It very useful for loading a small number of objects.
      $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');

      // Collect paragraph field's ids.
      $ids = array_column($paragraph_field_items, 'target_id');

      // Load all paragraph objects.
      $paragraphs_objects = $paragraph_storage->loadMultiple($ids);

      // Step through each of the paragraph items and get
      // the game info.
      foreach ($paragraphs_objects as $paragraph) {

        // Get the game type field.
        $game_type = $paragraph->field_game_type->value;

        // If this has a remove tourney flag and it is a
        // tourney just skip it.
        if (
          $remove_tourney_flag &&
          str_contains($game_type, 'Tournament')
        ) {
          continue;
        }

        // Get the notes of the game.
        $notes = $paragraph->field_game_notes->getValue();

        // If there are notes, then get the value and
        // the format, if not leave as null.
        if ($notes) {
          $notes = [
            '#type' => 'processed_text',
            '#text' => $notes[0]['value'],
            '#format' => $notes[0]['format'],
          ];
        }

        $list = '';
        $reserved_flag = FALSE;

        // If user is floor get the list.
        if ($this->isFloor()) {
          $list = $this->getList($node->id(), $game_type);
        }
        else if ($this->account->isAuthenticated()) {

          $query = $this->database->select('players_reserve', 'pr')
            ->fields('pr', ['reserve_id'])
            ->condition('pr.nid', $node->id())
            ->condition('pr.uid', $this->account->id())
            ->condition('pr.game_type', $game_type);

          $result = $query->execute()->fetchAssoc();

          if ($result) {
            $reserved_flag = TRUE;
          }
        }

        // Add the game to the games array.
        $games[] = [
          'title' => $game_type,
          'start_time' => $paragraph->field_start_time->value . ' ' . $paragraph->field_start_time_am_pm->value,
          'end_time' => $paragraph->field_end_time->value . ' ' . $paragraph->field_end_time_am_pm->value,
          'notes' => $notes,
          'list' => $list,
          'reserved_flag' => $reserved_flag,
        ];
      }
    }

    return $games;
  }

  /**
   * Function to get the list of players.
   *
   * @param int $nid
   *   The node id.
   * @param string $game_type
   *   The game type.
   *
   * @return mixed
   *   The list of players.
   */
  public function getList(int $nid, string $game_type) {
    $query = $this->database
      ->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id', 'uid', 'first_name', 'last_name', 'reserve_time'])
      ->condition('pr.nid', $nid)
      ->condition('pr.game_type', $game_type)
      ->condition('pr.seated', 0)
      ->condition('pr.removed', 0)
      ->orderBy('pr.reserve_time');

    return $query->execute()->fetchAll();
  }

  /**
   * Function to get if user has floor or admin role.
   *
   * @return bool
   *   If floor of admin.
   */
  public function isFloor(): bool {

    // Roles to check for.
    $roles = [
      'administrator',
      'floor',
    ];

    // Get the current user roles.
    $user_roles = $this->account->getRoles();

    // If user is floor or admin return TRUE.
    if(!empty(array_intersect($this->account->getRoles(), $roles))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Function to get the list of players.
   *
   * @return array
   *   An array of tournaments.
   */
  public function getTournaments(): array {

    // Ensure that we have something to return.
    $tourneys = [];

    // Get the query object.
    $query = $this->entityTypeManager->getStorage('node')->getQuery();

    // Get the nids that are greater than today.
    $nids = $query->condition('title', date('Y-m-d', strtotime('now')), '>')
      ->condition('status', '1')
      ->condition('type', 'pi_ct_games')
      ->execute();

    // Load all the nodes based on the nids.
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Step through each of the nodes and check if it
    // has a tournament.
    foreach ($nodes as $node) {

      // Get the games for that node.
      $games = $this->getGames($node);

      // Step through all the games and check if it
      // has a tournament.
      foreach ($games as $game) {

        // If the node has a tournament then add to array.
        if (str_contains($game['title'], 'Tournament')) {

          $query = $this->database->select('players_reserve', 'pr')
            ->fields('pr', ['reserve_id'])
            ->condition('pr.nid', $node->id())
            ->condition('pr.uid', $this->account->id())
            ->condition('pr.game_type', $game['title']);

          $result = $query->execute()->fetchAssoc();

          // Add the tournament to the array.
          $tourneys[] = [
            'display_date' => date('l F j, Y', strtotime($node->label())),
            'date' => $node->label(),
            'title' => $game['title'],
            'start_time' => $game['start_time'],
            'end_time' => $game['end_time'],
            'reserved_flag' => $result ? TRUE : FALSE,
          ];
        }
      }
    }

    return $tourneys;
  }

}
