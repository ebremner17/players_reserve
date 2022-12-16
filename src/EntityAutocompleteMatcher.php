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

    // If not user, do not modify.
    if ($target_type !== 'user') {
      return;
    }

    // Get the escaped value to search for.
    $escaped = $this->database->escapeLike($string);

    // Get the query for the users.
    $query = $this->database->select('users', 'u');
    $query->join('users_field_data', 'ufd', 'u.uid = ufd.uid');
    $query->join('user__field_user_first_name', 'fn', 'u.uid = fn.entity_id');
    $query->join('user__field_user_last_name', 'ln', 'u.uid = ln.entity_id');
    $query->fields('u', ['uid'])
      ->fields('fn', ['field_user_first_name_value'])
      ->fields('ln', ['field_user_last_name_value'])
      ->fields('ufd', ['mail']);

    // Add the or conditions for first and last name.
    $orGroup = $query->orConditionGroup()
      ->condition('fn.field_user_first_name_value', '%' . $escaped . '%', 'LIKE')
      ->condition('ln.field_user_last_name_value', '%' . $escaped . '%', 'LIKE');

    // Add the or condition to the query.
    $query->condition($orGroup);

    // Set the order by.
    $query->orderBy('ln.field_user_last_name_value', 'ASC');

    // Perform the query.
    $users = $query->execute()->fetchAll();

    // If there are users, modify the output.
    if ($users) {

      // Step through each user and set the output.
      foreach ($users as $user) {
        $label = $user->field_user_first_name_value;
        $label .= ' ';
        $label .= $user->field_user_last_name_value;
        $label .= ' (';
        $label .= $user->uid;
        $label .= ')';
        $matches[] = ['value' => $label, 'label' => $label];
      }
    }

    return $matches;
  }

}
