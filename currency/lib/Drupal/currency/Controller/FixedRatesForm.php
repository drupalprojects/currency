<?php

/**
 * @file
 * Contains \Drupal\currency\Controller\FixedRatesForm.
 */

namespace Drupal\currency\Controller\Exchanger;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\currency\Entity\Currency;
use Drupal\currency\Plugin\Currency\ExchangeRateProvider\ExchangeRateProviderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the configuration form for the currency_fixed_rates plugin.
 */
class FixedRatesForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFacory;

  /**
   * The currency storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  protected $currencyStorage;

  /**
   * The currency exchange rate provider manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $currencyExchangeRateProviderManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $currency_storage
   *   The currency storage.
   * @param \Drupal\currency\Plugin\Currency\ExchangeRateProvider\ExchangeRateProviderManagerInterface $currency_exchange_rate_provider_manager
   *   The currency exchange rate provider plugin manager.
   */
  public function __construct(ConfigFactory $configFactory, EntityStorageControllerInterface $currency_storage, ExchangeRateProviderManagerInterface $currency_exchange_rate_provider_manager) {
    $this->configFactory = $configFactory;
    $this->currencyStorage = $currency_storage;
    $this->currencyExchangeRateProviderManager = $currency_exchange_rate_provider_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityManagerInterface $entity_manager */
    $entity_manager = $container->get('entity.manager');
    return new static($container->get('config.factory'), $entity_manager->getStorageController('currency'), $container->get('plugin.manager.currency.exchange_rate_provider'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'currency_exchange_rate_provider_fixed_rates';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $currency_code_from = 'XXX', $currency_code_to = 'XXX') {
    $plugin = $this->currencyExchangeRateProviderManager->createInstance('currency_fixed_rates');
    $rate = $plugin->load($currency_code_from, $currency_code_to);

    $options = Currency::options();
    $form['currency_code_from'] = array(
      '#default_value' => $currency_code_from,
      '#disabled' => !is_null($rate) && $currency_code_from != 'XXX',
      '#empty_value' => 'XXX',
      '#options' => $options,
      '#required' => TRUE,
      '#title' => $this->t('Source currency'),
      '#type' => 'select',
    );
    $form['currency_code_to'] = array(
      '#default_value' => $currency_code_to,
      '#disabled' => !is_null($rate) && $currency_code_to != 'XXX',
      '#empty_value' => 'XXX',
      '#options' => $options,
      '#required' => TRUE,
      '#title' => $this->t('Destination currency'),
      '#type' => 'select',
    );
    $form['rate'] = array(
      '#limit_currency_codes' => array($currency_code_to),
      '#default_value' => array(
        'amount' => $rate,
        'currency_code' => $currency_code_to,
      ),
      '#required' => TRUE,
      '#title' => $this->t('Exchange rate'),
      '#type' => 'currency_amount',
    );
    $form['actions'] = array(
      '#type' => 'actions',
    );
    $form['actions']['save'] = array(
      '#button_type' => 'primary',
      '#name' => 'save',
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    );
    if (!is_null($rate)) {
      $form['actions']['delete'] = array(
        '#button_type' => 'danger',
        '#limit_validation_errors' => array(array('currency_code_from'), array('currency_code_to')),
        '#name' => 'delete',
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    /** @var \Drupal\currency\Plugin\Currency\ExchangeRateProvider\FixedRates $plugin */
    $plugin = $this->currencyExchangeRateProviderManager->createInstance('currency_fixed_rates');
    $values = $form_state['values'];
    $currency_from = $this->currencyStorage->load($values['currency_code_from']);
    $currency_to = $this->currencyStorage->load($values['currency_code_to']);

    switch ($form_state['triggering_element']['#name']) {
      case 'save':
        $plugin->save($currency_from->id(), $currency_to->id(), $values['rate']['amount']);
        drupal_set_message($this->t('The exchange rate for @currency_title_from to @currency_title_to has been saved.', array(
          '@currency_title_from' => $currency_from->label(),
          '@currency_title_to' => $currency_to->label(),
        )));
        break;
      case 'delete':
        $plugin->delete($currency_from->id(), $currency_to->id());
        drupal_set_message($this->t('The exchange rate for @currency_title_from to @currency_title_to has been deleted.', array(
          '@currency_title_from' => $currency_from->label(),
          '@currency_title_to' => $currency_to->label(),
        )));
        break;
    }
    $form_state['redirect'] = 'admin/config/regional/currency-exchange/fixed';
  }
}