<?php


namespace Drupal\adimeo_events\Plugin\Block;

use Drupal;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Provides a custom Block to display linked event.
 *
 * @Block(
 *   id = "linked_events",
 *   admin_label = @Translation("Linked events block"),
 *   category = @Translation("Linked events"),
 * )
 */
class LinkedEvents extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array
  {

    $similarEvents = [];

    // Get event type from current route.
    $node = Drupal::routeMatch()->getParameter('node');
    $eventType = $node->get('field_event_type')->target_id;

   // Get Similar Event not ended related to event type.
    if (!empty($eventType)) {
      $similarEvents = $this->getSimilarEvents($eventType);
    }

    if (empty($similarEvents)) {
      return [];
    }

    // Load and build them all together with appropriated view mode.
    $node_storage = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($similarEvents);
    return \Drupal::entityTypeManager()->getViewBuilder('node')->viewMultiple($node_storage, 'linked_events');
  }

  private function getSimilarEvents(int $eventType)
  {

    // Get current date and format it.
    $currentDate = new DrupalDateTime('now');
    $formatted = $currentDate->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Query similar Events based on type and date
    $query = Drupal::entityQuery('node');
    $query->condition('type', 'event');
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
    $query = Drupal::entityQuery('node');
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
