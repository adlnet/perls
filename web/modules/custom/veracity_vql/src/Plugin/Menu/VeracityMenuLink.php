<?php

namespace Drupal\veracity_vql\Plugin\Menu;

use Drupal\Core\Url;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\veracity_vql\VeracityApiInterface;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Links to the Veracity UI for the currently configured integration.
 */
class VeracityMenuLink extends MenuLinkDefault {
  /**
   * The Veracity API.
   *
   * @var \Drupal\veracity_vql\VeracityApiInterface
   */
  protected $veracityApi;

  /**
   * Constructs a new VeracityMenuLink instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, VeracityApiInterface $veracity_api) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
    $this->veracityApi = $veracity_api;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('veracity_vql.api')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlObject($title_attribute = TRUE) {
    $options = $this->getOptions();
    if ($title_attribute && $description = $this->getDescription()) {
      $options['attributes']['title'] = $description;
    }

    return Url::fromUri($this->getUrlString(), $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['config:veracity_vql.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.permissions'];
  }

  /**
   * Retrieves the URL for Veracity.
   *
   * @return string
   *   The URL for Veracity.
   */
  protected function getUrlString(): string {
    $host = parse_url($this->veracityApi->getEndpoint(), PHP_URL_HOST);
    return "https://$host/ui/users/login";
  }

}
