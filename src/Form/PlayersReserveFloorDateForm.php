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

class PlayersReserveFloorDateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'players_floor_date_form';
  }

  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $date = NULL
  ) {

    // If there is no date, get the current date.
    if (!$date) {
      $date = date('Y-m-d');
    }

    // The date element.
    $form['date'] = [
      '#type' => 'date',
      '#default_value' => $date
    ];

    // The submit button.
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#button_type' => 'primary',
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the values from the form state.
    $values = $form_state->getValues();

    // Redirect to the form with the new date.
    $form_state->setRedirect('players_reserve.reserve_date', ['date' => $values['date']]);
  }

}
