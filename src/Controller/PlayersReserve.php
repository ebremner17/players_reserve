<?php

namespace Drupal\players_reserve\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;
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
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTyperManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Returns a page for players reserve.
   *
   * @return array
   *   A simple renderable array.
   */
  public function reserve() {

    // Array to hold the game info.
    $games = [];

    // Todays time, need this to get what is the current
    // date.  If we are less than 4 am into the next day
    // the current date is yesterday.
    $time = date('G');

    // Get the correct date based on the time.
    if ($time >= 0 && $time <= 4) {
      $date = date('Y-m-d', strtotime("-1 days"));
    }
    else {
      $date = date('Y-m-d');
    }

    // Get the node based on the current date.
    $node = $this->entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'field_game_date' => $date
      ]);

    // If there is node, get the info about the games.
    if ($node) {

      // Get the current node, using entity type manager
      // puts all the nodes in array, we are only concerned
      // with the first and should be only node.
      $node = current($node);

      // Get data from field.
      if ($paragraph_field_items = $node->get('field_game_types')->getValue()) {

        // Get storage. It very useful for loading a small number of objects.
        $paragraph_storage = $this->entityTypeManager()->getStorage('paragraph');

        // Collect paragraph field's ids.
        $ids = array_column($paragraph_field_items, 'target_id');

        // Load all paragraph objects.
        $paragraphs_objects = $paragraph_storage->loadMultiple($ids);

        // Step through each of the paragraph items and get
        // the game info.
        foreach ($paragraphs_objects as $paragraph) {

          // Get the game type field.
          $game_type = $paragraph->field_game_type->value;

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

          // Add the game to the games array.
          $games['games'][] = [
            'title' => $game_type,
            'start_time' => $paragraph->field_start_time->value . ' ' . $paragraph->field_start_time_am_pm->value,
            'end_time' => $paragraph->field_end_time->value . ' ' . $paragraph->field_end_time_am_pm->value,
            'notes' => $notes,
          ];
        }
      }
    }

    // Set the display date and actual date in the games array.
    $games['display_date'] = date('l F j, Y', strtotime($date));
    $games['date'] = $date;

    // Set the render array.
    return [
      '#theme' => 'players_reserve',
      '#games' => $games,
    ];
  }

}
