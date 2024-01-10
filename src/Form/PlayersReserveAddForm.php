<?php

/**
 * @file
 * Contains \Drupal\players_reserve\Form\PlayersReserveAddForm.php.
 */

namespace Drupal\players_reserve\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\players_reserve\Service\PlayersService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PlayersReserveAddForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database
   * @param \Drupal\players_reserve\Service\PlayersService $playersService
   *   The players service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   * *   The messenger.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    Connection $database,
    PlayersService $playersService,
    MessengerInterface $messenger
  ) {

    $this->entityTypeManager = $entityTypeManager;
    $this->database = $database;
    $this->playersService = $playersService;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {

    // Instantiates this form class.
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('players_reserve.players_service'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'players_reserve_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $date = NULL
  ) {

    // If the date is incorrect then redirect back to the reserve.
    if (!preg_match('/202[4-9]{1}[-][0-9]{1}[1-9]{1}-[0-3]{1}[0-9]{1}/', $date)) {
      return new RedirectResponse('/reserve');
    }

    if (
      $form_state->has('page_num') &&
      $form_state->get('page_num') == 2
    ) {

      return $this->playersReservePageTwo($form, $form_state);
    }

    if (
      $form_state->has('page_num') &&
      $form_state->get('page_num') == 3
    ) {

      return $this->playersReservePageThree($form, $form_state);
    }

    // Set the form state page num, since if we arrive
    // here it is the first page of the form.
    $form_state->set('page_num', 1);

    // The form wrapper.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-games-block'],
      ],
    ];

    $display_date = date('D M j, Y', strtotime($date));

    // The form wrapper.
    $form['wrapper']['title'] = [
      '#markup' => '<h1>Reserve: ' . $display_date . '</h1>',
    ];

    // The date of the game.
    $form['wrapper']['date'] = [
      '#type' => 'hidden',
      '#default_value' => $date,
    ];

    // The phone number element.
    $form['wrapper']['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone number'),
      '#description' => $this->t('Enter your phone number, with no spaces or dashes, ex. 4165556666.'),
      '#required' => TRUE,
    ];

    // Group submit handlers in an actions element with a key of "actions" so
    // that it gets styled correctly, and so that other modules may add actions
    // to the form. This is not required, but is convention.
    $form['wrapper']['actions'] = [
      '#type' => 'actions',
    ];

    $form['wrapper']['actions']['next'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Next'),
      '#submit' => ['::playersReserveNextSubmit'],
      '#validate' => ['::playersReserveNextValidate'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $page_values = $form_state->get('page_values');

    $this->messenger()->addMessage($this->t('The form has been submitted. name="@first @last", year of birth=@year_of_birth', [
      '@first' => $page_values['first_name'],
      '@last' => $page_values['last_name'],
      '@year_of_birth' => $page_values['birth_year'],
    ]));

    $this->messenger()->addMessage($this->t('And the favorite color is @color', ['@color' => $form_state->getValue('color')]));
  }

  /**
   * Provides custom validation handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextValidate(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the phone number from the form state.
    $phone = $form_state->getValue('phone');

    // Check that it is in the proper format.
    if (
      isset($form['phone']) &&
      !preg_match('/^[0-9]{10}$/', $phone)
    ) {

      $form_state->setError(
        $form['phone'],
        $this->t('Phone number is not in proper format, please use just digits, ex. 4165556666.')
      );
    }
  }

  /**
   * Provides custom submission handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Set the values in the form state, setting
    // the page number to the second step.
    $form_state
      ->set('page_values', [
        'phone' => $values['phone'],
        'date' => $values['date'],
      ])
      ->set('page_num', 2)
      ->setRebuild(TRUE);
  }

  /**
   * Builds the second step form (page 2).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function playersReservePageTwo(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();
    $page_values = $form_state->get('page_values');

    // Get the phone number from the form state.
    $phone = $values['phone'];

    // The query to get the info about the player.
    $query = $this->database
      ->select('user__field_user_phone', 'ufp')
      ->fields('ufp', ['entity_id'])
      ->condition('ufp.field_user_phone_local_number', $phone);

    // Get the uid of the player.
    $uid = $query->execute()->fetchAll();

    // Reset the user to null.
    $user = NULL;

    // The wrapper for the form.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-games-block'],
      ],
    ];

    $display_date = date('D M j, Y', strtotime($page_values['date']));

    // The form wrapper.
    $form['wrapper']['title'] = [
      '#markup' => '<h1>Reserve: ' . $display_date . '</h1>',
    ];

    // If there is a user, set the form element,
    // and load the user object.
    if (count($uid) > 0) {
      $form['wrapper']['uid'] = [
        '#type' => 'hidden',
        '#default_value' => $uid[0]->entity_id,
      ];

      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($uid[0]->entity_id);
    }

    // The form header for title.
    $form['wrapper']['header'] = [
      '#markup' => '<h2>Player Information</h2>',
    ];

    // The first name of the user.
    $form['wrapper']['first_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('First name'),
      '#default_value' => $user ? $user->field_user_first_name->value : NULL,
      '#required' => TRUE,
    ];

    // The last name of the user.
    $form['wrapper']['last_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Last name'),
      '#default_value' => $user ? $user->field_user_last_name->value : NULL,
      '#required' => TRUE,
    ];

    // The email of the user.
    $form['wrapper']['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email'),
      '#default_value' => $user ? $user->mail->value : NULL,
      '#required' => TRUE,
    ];

    // The form button to the next step in the form.
    $form['wrapper']['actions']['next_page_two'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Next'),
      '#submit' => ['::playersReserveNextSubmitPageTwo'],
      '#validate' => ['::playersReserveNextValidatePageTwo'],
    ];

    return $form;
  }

  /**
   * Provides custom validation handler for page 2.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextValidatePageTwo(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Ensure that email is correct.
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
      $form_state->setError(
        $form['email'],
        $this->t('Email is in invalid.')
      );
    }
  }

  /**
   * Provides custom submission handler for page 2.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveNextSubmitPageTwo(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Get the page values, from previous page.
    $page_values = $form_state->get('page_values');

    // If there is a user id, set the updated values.
    if (isset($values['uid']) && $values['uid'] !== '') {

      // Load the user.
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($values['uid']);

      // Set the user values.
      $user->set('mail', $values['email']);
      $user->set('field_user_first_name', $values['first_name']);
      $user->set('field_user_last_name', $values['last_name']);

      // Save the user.
      $user->save();

      // Get the uid from the values.
      $uid = $values['uid'];
    }
    else {

      // Begin to create a user.
      $user = User::create();

      // Get the username based on the email address.
      $username = explode('@', $values['email']);

      // Set all the values of the user.
      $user->setUsername($username[0]);
      $user->setPassword(password_generate(12));
      $user->enforceIsNew();
      $user->setEmail($values['email']);
      $user->set('field_user_last_name', $values['last_name']);
      $user->set('field_user_first_name', $values['first_name']);

      // Get the values required for the phone number.
      $phone_number = [
        'value' => $page_values['phone'],
        'country' => 'CA',
        'local_number' => $page_values['phone'],
        'extension' => NULL,
      ];

      // Set the phone number.
      $user->set('field_user_phone', $phone_number);

      // Activate and save the user.
      $user->activate();
      $user->save();

      // Get the uid from the newly created user.
      $uid = $user->id();
    }

    // Set the values in the form state, setting
    // the page number to the third step.
    $form_state
      ->set('page_values', [
        'uid' => $uid,
        'phone' => $page_values['phone'],
        'first_name' => $values['first_name'],
        'last_name' => $values['last_name'],
        'date' => $page_values['date'],
      ])
      ->set('page_num', 3)
      ->setRebuild(TRUE);
  }

  /**
   * Builds the second step form (page 3).
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function playersReservePageThree(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Get the values and page values from the form state.
    $values = $form_state->getValues();
    $page_values = $form_state->get('page_values');

    // Get the node for the current game.
    $node = current(
      $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties(['title' => $page_values['date']])
    );

    // Load the games.
    $games = $this->playersService->getGames($node, TRUE, $page_values['uid']);

    // Reset the options and default values array.
    $options = [];
    $default_values = [];

    // Step through each of the games and add the
    // game title if the flag is set and get the
    // options.
    foreach ($games as $game) {

      // Set the options.
      $options[$game['title']] = $game['title'] . ': ' . $game['start_time'] . ' - ' . $game['end_time'];

      // If the flag for the user as being reserved is
      // set then add to the default values.
      if ($game['reserved_flag']) {
        $default_values[] = $game['title'];
      }
    }

    // The wrapper for the form.
    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['players-games-block'],
      ],
    ];

    // The node id.
    $form['wrapper']['nid'] = [
      '#type' => 'hidden',
      '#default_value' => $node->id(),
    ];

    $display_date = date('D M j, Y', strtotime($page_values['date']));

    // The form wrapper.
    $form['wrapper']['title'] = [
      '#markup' => '<h1>Reserve: ' . $display_date . '</h1>',
    ];

    // The games element for everything other than
    // Friday nights.
    $form['wrapper']['games'] = [
      '#type' => 'checkboxes',
      '#options' => $options,
      '#title' => $this->t('Game types'),
      '#default_value' => $default_values,
    ];

    // The submit button.
    $form['wrapper']['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Reserve'),
      '#submit' => ['::playersReserveSubmit'],
    ];

    return $form;
  }

  /**
   * Provides custom submission handler for page 1.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function playersReserveSubmit(
    array &$form,
    FormStateInterface $form_state
  ) {

    // Set the reserve time.
    $reserve_time = date('Y-m-d H:i:s');

    // Get the values from the form state.
    $values = $form_state->getValues();
    $page_values = $form_state->get('page_values');

    // Clear the user entries in the reserve.
    $delete = $this->database->delete('players_reserve')
      ->condition('nid', $values['nid'])
      ->condition('uid', $page_values['uid'])
      ->execute();

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
              'uid' => $page_values['uid'],
              'nid' => $values['nid'],
              'first_name' => $page_values['first_name'],
              'last_name' => $page_values['last_name'],
              'game_type' => $game_type,
              'reserve_time' => $reserve_time,
              'seated' => 0,
            ])
            ->execute();
        }
      }
    }

    // Add the message.
    $this->messenger->addStatus($this->t('You reservation has been successfully updated.'));

    $form_state->setRedirect('players_reserve.reserve');
  }

}
