<?php

namespace Drupal\perls_learner_browse\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Block to provide the next available course item within a course.
 *
 * @Block(
 *   id = "next_course_content_block",
 *   admin_label = @Translation("Next course content block"),
 * )
 */
class NextCourseContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Current request route;.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $node = (!empty($config) && !empty($config['nid'])) ? $this->entityTypeManager->getStorage('node')->load($config['nid']) : $this->routeMatch->getParameter('node');
    // "Hide" the block if a user is forbidden from viewing the current node.
    if (!$node->access('view')) {
      return [];
    }

    $associated_courses = $this->getAssociatedCourses($node);
    // Add related course links for a given node.
    return [
      '#theme' => 'next_course_content_block',
      '#course_content' => $this->getNextCourseContent($associated_courses, $node->id()),
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * Provides the a specified number of proceeding next course items.
   *
   * @param Drupal\Core\Field\EntityReferenceFieldItemList $course_objects
   *   The course learning objects for a course.
   * @param int $nid
   *   The node id of the current course item being viewed.
   * @param int $number_of_items
   *   The maximum number of items you would like to have returned.
   *
   * @return array
   *   Array of proceeding course items after the current item.
   */
  private function getNextCourseLearningObject(EntityReferenceFieldItemList $course_objects, $nid, $number_of_items) {
    $course_content_list = $course_objects->referencedEntities();
    $next_content = [];
    foreach ($course_content_list as $key => $learning_object) {
      if ($learning_object->id() == $nid) {
        // Get the next course item(s) after the current item.
        $possible_content = array_slice($course_content_list, $key + 1);
        $p_count = count($possible_content);
        $added_items = 0;
        for ($i = 0; $i < $p_count; $i++) {
          // Only get content the user can access.
          if ($possible_content[$i]->access('view')) {
            $next_content[] = $possible_content[$i];
            $added_items++;
          }
          // If desired number of items reached, stop looking for content.
          if ($added_items == $number_of_items) {
            break;
          }
        }

        return $next_content;
      }
    }

    return NULL;
  }

  /**
   * Helper function to get the content to render for a given learning object.
   *
   * @param Drupal\node\NodeInterface $learning_object
   *   The learning object to get the renderable display for.
   *
   * @return array
   *   The render data available for a given learning object.
   */
  private function getLearningObjectRenderData(NodeInterface $learning_object) {
    $valid_bundles = [
      'learn_article',
      'learn_file',
      'learn_package',
      'test',
      'learn_link',
    ];
    // Only provide renderable data specific supported types.
    if (in_array($learning_object->bundle(), $valid_bundles)) {
      $builder = $this->entityTypeManager->getViewBuilder($learning_object->getEntityTypeId());
      $renderable['content'] = $builder->view($learning_object, 'tile');
      return $renderable;
    }

    return NULL;
  }

  /**
   * Get the courses associated with a given node.
   *
   * @param Drupal\node\NodeInterface $node
   *   The node to find course information for.
   *
   * @return array
   *   The array of courses associated with the given node.
   */
  private function getAssociatedCourses(NodeInterface $node) {
    $course_field = ($node->bundle() == 'test') ? 'field_test_course' : 'field_course';
    if (!$node->hasField($course_field)) {
      return [];
    }
    $associated_courses = $node->get($course_field)->referencedEntities();
    return $associated_courses;
  }

  /**
   * Get all the content proceeding a given course item in a course.
   *
   * @param array $associated_courses
   *   An array of associated course nodes.
   * @param int $nid
   *   The node id of the course item being evaluated for proceeding content.
   *
   * @return array
   *   Empty or an array of render-centric data of next learning objects.
   */
  private function getNextCourseContent(array $associated_courses, $nid) {
    $course_content = [];
    // Loop all courses the content is associated to.
    foreach ($associated_courses as $course) {
      if (!$course->access('view')) {
        continue;
      }
      /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $next_object */
      $learning_content = $course->field_learning_content;
      if (!$learning_content->isEmpty()) {
        // Get a renderable result for all related course content.
        if ($next_object_list = $this->getNextCourseLearningObject($learning_content, $nid, 3)) {
          $course_content[$course->id()] = [
            'course_title' => $course->getTitle(),
            'course_link' => Link::fromTextAndUrl(t('View course'), $course->toUrl())->toRenderable(),
          ];
          $items = [];
          foreach ($next_object_list as $next_object) {
            $items[] = $this->getLearningObjectRenderData($next_object);
          }
          if (!empty($items)) {
            $course_content[$course->id()]['items'] = $items;
          }
        }
      }
    }

    return $course_content;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $node = $this->routeMatch->getParameter('node');
    $courses = $this->getAssociatedCourses($node);
    $tags_to_invalidate = array_map(function ($course) {
      return $course->getCacheTagsToInvalidate();
    }, $courses);
    $tags_to_invalidate[] = $node->getCacheTagsToInvalidate();
    return array_merge(...$tags_to_invalidate);
  }

}
