<?php

namespace Drupal\players_reserve;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Database\Connection;

class EntityAutocompleteMatcher extends \Drupal\Core\Entity\EntityAutocompleteMatcher {

  /**
   * The entity reference selection handler plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs an EntityAutocompleteMatcher object.
   *
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The entity reference selection handler plugin manager.
   */
  public function __construct(
    SelectionPluginManagerInterface $selection_manager,
    Connection $database
  ) {
    $this->selectionManager = $selection_manager;
    $this->database = $database;
  }

  /**
   * Gets matched labels based on a given search string.
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {

    if ($target_type !== 'user') {
      return;
    }

    $query = $this->database->select('users', 'u');
    $query->join('users_field_data', 'ufd', 'u.uid = ufd.uid');
    $query->join('user__field_user_first_name', 'fn', 'u.uid = fn.entity_id');
    $query->join('user__field_user_last_name', 'ln', 'u.uid = ln.entity_id');
    $query->fields('u', ['uid'])
      ->fields('fn', ['field_user_first_name_value'])
      ->fields('ln', ['field_user_last_name_value'])
      ->fields('ufd', ['mail']);

    $orGroup = $query->orConditionGroup()
      ->condition('fn.field_user_first_name_value', '%' . $string . '%', 'LIKE')
      ->condition('ln.field_user_last_name_value', '%' . $string . '%', 'LIKE');

    $query->condition($orGroup);

    $users = $query->execute()->fetchAll();

    if ($users) {
      foreach ($users as $user) {
        $label = $user->field_user_first_name_value;
        $label .= ' ';
        $label .= $user->field_user_last_name_value;
        $label .= ' (';
        $label .= $user->uid;
        $label .= ')';
        $label .= ' [' . $user->mail . ']';
        $matches[] = ['value' => $label, 'label' => $label];
      }
    }

    return $matches;
  }

}
