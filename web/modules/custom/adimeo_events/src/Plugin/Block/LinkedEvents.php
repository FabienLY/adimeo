<?php


namespace Drupal\adimeo_events\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom Block to display linked event.
 *
 * @Block(
 *   id = "linked_events",
 *   admin_label = @Translation("Linked events block"),
 *   category = @Translation("Linked events"),
 * )
 */
class LinkedEvents extends BlockBase implements ContainerFactoryPluginInterface{

  /**
   * The entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entity_type_manager;

  /**
   * Retrieves the currently active route match object.
   *
   * @var CurrentRouteMatch
   */
  protected $current_route;

  /**
   * Constructs a new block.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param CurrentRouteMatch $current_route
   *   The current route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, CurrentRouteMatch $current_route) {
    $this->entity_type_manager = $entity_type_manager;
    $this->current_route = $current_route;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }


  /**
   * Creates an instance of the plugin.
   *    *
   * @param ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): LinkedEvents {

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    $similarEvents = [];

    // Get event type from current route.
    $node = $this->current_route->getParameter('node');
    $eventType = $node->get('field_event_type')->target_id;
    $nid = $node->id();

   // Get Similar Event not ended related to event type.
    if (!empty($eventType)) {
      $similarEvents = $this->getSimilarEvents($eventType, $nid);
    }

    if (empty($similarEvents)) {
      return [];
    }

    // Load and build them all together with appropriated view mode.
    $node_storage = $this->entity_type_manager->getStorage('node')->loadMultiple($similarEvents);
    return $this->entity_type_manager->getViewBuilder('node')->viewMultiple($node_storage, 'linked_events');
  }

  /**
   *  Query for node entity
   *
   * @param int $eventType
   *  Tid of the main event.
   * @param int $nid
   *  Nid of the main event.
   *
   * @return array|int
   *  Array of nid base on the query.
   *
   */
  private function getSimilarEvents(int $eventType, int $nid) {

    // Get current date and format it.
    $currentDate = new DrupalDateTime('now');
    $formatted = $currentDate->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Query similar Events based on type and date
    $query = $this->entity_type_manager->getStorage('node')->getQuery();
    $query->condition('type', 'event');
    $query->condition('nid', [$nid], 'NOT IN');
    $query->condition('field_event_type', $eventType);
    $query->condition('field_date_end', $formatted, '>');
    $query->range(0, 3);
    $query->sort('field_date_start', 'ASC');

    $events = $query->execute();

    $EventsCount = count($events);
    if ($EventsCount == 3) {
      return $events;
      }

    // If not enough events found complete with others type.
    $query = $this->entity_type_manager->getStorage('node')->getQuery();
    $query->condition('type', 'event');
    $query->condition('field_event_type', [$eventType], 'NOT IN');
    $query->condition('field_date_end', $formatted, '>');
    $query->range(0, 3 - $EventsCount);
    $query->sort('field_date_start', 'ASC');
    $othersTypeEvents = $query->execute();

    return array_merge($events, $othersTypeEvents);

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int
  {
    // The Max Age for that block
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array
  {
    return ['url.path', 'url.query_args'];
  }

}
