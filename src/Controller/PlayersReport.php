<?php

namespace Drupal\players_reserve\Controller;

use DateTime;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\players_reserve\Service\PlayersService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Report for Players Inc..
 */
class PlayersReport extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

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
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\players_reserve\Service $playersService
   *   The players service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The current request stack.
   * @param \Drupal\Core\Database\Connection $database
   *   The database.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    MessengerInterface $messenger,
    PlayersService $playersService,
    RequestStack $requestStack,
    Connection $database
  ) {

    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
    $this->playersService = $playersService;
    $this->requestStack = $requestStack;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('players_reserve.players_service'),
      $container->get('request_stack'),
      $container->get('database')
    );
  }

  /**
   * Returns a page for players report.
   *
   * @return array
   *   A simple renderable array.
   */
  public function report(): array {

    // Get the parameters from the URL.
    $parameters = $this->requestStack->getCurrentRequest()->query->all();

    // If there are no parameters, redirect back to the
    // reports page.
    if (empty($parameters)) {
     $this->redirectToReports();
    }

    // Get the correct report based on the report type.
    switch ($parameters['report_type']) {

      // The single game report.
      case 'single_game_report':

        // Ensure that we have the correct parameters.
        $this->checkForParameters($parameters, ['date']);

        // Get the report.
        $report_info['reservations'] = $this->getSingleGameReport($parameters);

        // Set the title.
        $report_info['title'] = 'Single Game Report';

        break;

      case 'multiple_game_report':

        // Ensure that we have the correct parameters.
        $this->checkForParameters($parameters, ['start_date', 'end_date', 'status']);

        // Get the report.
        $report_info['reservations'] = $this->getMultipleGameReport($parameters);

        // Set the title.
        $report_info['title'] = 'Multiple Game Report';

        break;
    }

    // Set the subtitle.
    $report_info['sub_title'] = 'Generated on ' . date('l F j, Y h:i:s A', strtotime('now'));

    // Set the render array.
    return [
      '#theme' => 'players_report',
      '#report_info' => $report_info,
    ];
  }

  /**
   * Function to check for the correct parameters.
   *
   * @param array $parameters
   *   The array of parameters sent.
   * @param array $params_to_check
   *   The parameters to check for.
   */
  public function checkForParameters(array $parameters, array $params_to_check): void {

    // If there are no parameters that intersect, send back
    // to the select reports page.
    if (empty(array_intersect(array_keys($parameters), $params_to_check))) {
      $this->redirectToReports();
      exit;
    }
  }

  /**
   * Function to redirect back to the reports page.
   */
  public function redirectToReports(): void {

    $url = Url::fromUri('internal:/admin/reserve/reports')->toString();
    $response = new RedirectResponse($url);
    $response->send();
  }
  /**
   * Function to get the single game report.
   *
   * @param string $date
   *   The date of the game.
   *
   * @return array
   *   The array of data about the game.
   */
  public function getSingleGameReport(array $parameters): array {

    // Load the node based on the date selection.
    $node = current($this->entityTypeManager->getStorage('node')->loadByProperties(['title' => $parameters['date']]));

    // If there is no node, redirect back to the select reports page,
    // with a message.
    if (!$node) {
      $display_date = date('l F j, Y', strtotime($parameters['date']));
      $this->messenger->addError('There is no game data for ' . $display_date . '.');
      $this->redirectToReports();
    }

    $reservations[] = $this->getReservationTable($node->id(), $parameters['date'], $parameters);

    return $reservations;
  }

  /**
   * Function to get the multiple game report.
   *
   * @param array $parameters
   *   Array of parameters.
   *
   * @return array
   *   Array of reservations.
   */
  public function getMultipleGameReport(array $parameters): array {

    $earlier = new DateTime($parameters['start_date']);
    $later = new DateTime($parameters['end_date']);
    $dates_diff = $later->diff($earlier)->format("%a");

    for ($i = 0; $i <= $dates_diff; $i++) {
      $dates[] = date('Y-m-d', strtotime($parameters['start_date'] . ' +' . $i . ' day'));
    }

    foreach ($dates as $date) {

      // Load the node based on the date selection.
      $node = current($this->entityTypeManager->getStorage('node')->loadByProperties(['title' => $date]));

      // If there is no node, redirect back to the select reports page,
      // with a message.
      if (!$node) {
        $display_date = date('l F j, Y', strtotime($parameters['date']));
        // The table for the list.
        $reservations[] = [
          'date' => date('l F j, Y', strtotime($parameters['date'])),
          'open' => FALSE,
          'table' => [
            '#type' => 'markup',
            '#markup' => 'There is no game data for ' . $display_date . '.',
          ],
        ];
      }
      else {

        $reservations[] = $this->getReservationTable($node->id(), $date, $parameters, FALSE);
      }
    }

    return $reservations;//3
  }

  /**
   * Function to get the table of reservations.
   *
   * @param int $nid
   *   The node id.
   * @param string $date
   *   The date to get the game data.
   * @param array $parameters
   *   The array of parameters.
   * @param bool $open_flag
   *   Flag to show the details as open or closed.
   *
   * @return array
   *   The array for reservations.
   */
  public function getReservationTable(int $nid, string $date, array $parameters, bool $open_flag = TRUE): array {

    // The header for the table.
    $header = [
      ['data' => t('Last Name'), 'field' => 'last_name', 'sort' => 'asc'],
      ['data' => $this->t('First Name'), 'field' => 'first_name', 'sort' => 'asc'],
      ['data' => $this->t('Phone'), 'field' => 'phone'],
      ['data' => $this->t('Email'), 'field' => 'email'],
      ['data' => $this->t('Game'), 'field' => 'game', 'sort' => 'asc'],
      ['data' => t('Status')],
    ];

    // Get the reservations for the game.
    $results = $this->getReservationsByGame($nid, $parameters);

    if (empty($results)) {
      return [
        'date' => date('l F j, Y', strtotime($date)),
        'count' => NULL,
        'open' => $open_flag,
        'table' => [
          '#type' => 'markup',
          '#markup' => 'There is no game data for ' . date('l F j, Y', strtotime($date)) . '.',
        ],
      ];
    }

    // Step through each result and get the rows.
    foreach ($results as $result) {

      // Add in the correct thing for status.
      if ($parameters['status'] == 'both') {
        $status = $result->seated ? 'Seated' : 'Removed';
      }
      else {
        if ($parameters['status'] == 'seated') {
          $status = 'Seated';
        }
        else {
          $status = 'Removed';
        }
      }

      // Set the rows.
      $rows[] = [
        'data' => [
          'first_name' => $result->last_name,
          'last_name' => $result->first_name,
          'phone' => $result->phone,
          'email' => $result->email,
          'game' => $result->game_type,
          'status' => $status,
        ],
      ];
    }

    // The table for the list.
    return [
      'date' => date('l F j, Y', strtotime($date)),
      'count' => count($results),
      'open' => $open_flag,
      'table' => [
        '#type' => 'table',
        '#title' => 'Game data',
        '#header' => $header,
        '#rows' => $rows,
      ],
    ];
  }

  /**
   * Function to get the reservations by the game.
   *
   * @param int $nid
   *   The node id.
   * @param array $parameters
   *   The parameters for the page.
   *
   * @return array
   *   Array of reservations.
   */
  public function getReservationsByGame(int $nid, array $parameters): array {

    // The query to get the reservations for the game.
    $query = $this->database->select('players_reserve', 'pr')
      ->fields('pr', ['reserve_id', 'uid', 'first_name', 'last_name', 'game_type', 'seated', 'removed'])
      ->condition('pr.nid', $nid);

    // If the status parameters is seated, add the condition.
    if ($parameters['status'] == 'seated') {
      $query->condition('pr.seated', 1);
    }

    // If the status parameter is removed, add the condition.
    if ($parameters['status'] == 'removed') {
      $query->condition('pr.removed', 1);
    }

    // If the order is set, add to the query.
    if (isset($parameters['order'])) {

      // If the order is by game, set to proper field.
      // If not just use the order parameter.
      if ($parameters['order'] == 'Game') {
        $query->orderBy('game_type', $parameters['sort']);
        $query->orderBy('last_name', 'asc');
      }
      else {
        $order_by_name = strtolower($parameters['order']);
        $order_by_name = str_replace(' ', '_', $order_by_name);
        $query->orderBy($order_by_name, $parameters['sort']);
      }
    }
    else {

      // By default sort by last name.
      $query->orderBy('last_name', 'asc');
    }

    $results = $query->execute()->fetchAll();

    foreach ($results as $index => $result) {
      if ($result->uid) {
        $user = $this->entityTypeManager->getStorage('user')->load($result->uid);
        $results[$index]->phone = $user->field_user_phone->value;
        $results[$index]->email = $user->mail->value;
      }
      else {
        $results[$index]->phone = '';
        $results[$index]->email = '';
      }
    }
    // Return the results.
    return $results;
  }

}
